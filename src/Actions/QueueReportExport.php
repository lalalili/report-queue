<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Actions;

use Illuminate\Support\Facades\DB;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Jobs\RunReportExportJob;
use Lalalili\ReportQueue\Models\Report;
use Lalalili\ReportQueue\Support\Config;
use Lalalili\ReportQueue\Support\HostUser;
use Lalalili\ReportQueue\Support\ReportModel;
use Lalalili\ReportQueue\Support\ReportNotifier;
use Lalalili\ReportQueue\Support\ReportStorage;

/**
 * Entry point every export action calls: records the task, guards against
 * duplicates, and hands off to the queue.
 */
class QueueReportExport
{
    /**
     * @param  string  $type  Registry key; also the label shown in the table.
     * @param  array<string, mixed>  $extraParams  Merged into report->params.
     * @return Report|null Null when an identical export is already in flight.
     */
    public static function dispatch(
        string $type,
        string $filenamePrefix,
        array $extraParams = [],
        int $count = 0,
        string $extension = 'xlsx',
    ): ?Report {
        $userId = HostUser::currentId();
        $notifier = app(ReportNotifier::class);
        $requestKey = self::requestKey($type, $extraParams);

        $report = self::persist($type, $filenamePrefix, $extraParams, $count, $extension, $userId, $requestKey);

        if ($report === null) {
            return null;
        }

        // Dispatched after the transaction commits so the worker cannot pick up
        // a row that is not visible yet.
        RunReportExportJob::dispatch($report->reportKey());

        $notifier->queued($report);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $extraParams
     */
    private static function persist(
        string $type,
        string $filenamePrefix,
        array $extraParams,
        int $count,
        string $extension,
        int|string|null $userId,
        ?string $requestKey,
    ): ?Report {
        return DB::transaction(function () use ($type, $filenamePrefix, $extraParams, $count, $extension, $userId, $requestKey): ?Report {
            $existing = self::findInFlight($type, $userId, $requestKey);

            if ($existing !== null) {
                app(ReportNotifier::class)->duplicate($existing);

                return null;
            }

            $filename = app(ReportStorage::class)->filename($filenamePrefix, $extension);

            $params = array_merge($extraParams, ['filename' => $filename]);

            if ($requestKey !== null) {
                $params['request_key'] = $requestKey;
            }

            return ReportModel::create([
                HostUser::foreignKey() => $userId,
                'type' => $type,
                'params' => $params,
                'count' => $count,
                'status' => ReportStatusEnum::PENDING,
                'progress' => 0,
            ]);
        });
    }

    private static function findInFlight(string $type, int|string|null $userId, ?string $requestKey): ?Report
    {
        if (! Config::bool('idempotency.enabled', true)) {
            return null;
        }

        $strategy = Config::string('idempotency.strategy', 'type_per_user');

        $query = ReportModel::query()->whereIn('status', ReportStatusEnum::active());

        if ($strategy === 'type_per_user') {
            $query->where(HostUser::foreignKey(), $userId)->where('type', $type);
        } elseif ($strategy === 'request_key' && $requestKey !== null) {
            $query->where('type', $type)->where('params->request_key', $requestKey);
        } else {
            return null;
        }

        if (Config::bool('idempotency.lock_for_update', true)) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Stable fingerprint of "the same export request", ignoring params that
     * differ between two otherwise identical submissions.
     *
     * @param  array<string, mixed>  $extraParams
     */
    private static function requestKey(string $type, array $extraParams): ?string
    {
        if (Config::string('idempotency.strategy', 'type_per_user') !== 'request_key') {
            return null;
        }

        $volatile = Config::stringList('idempotency.volatile_params');

        $significant = array_diff_key($extraParams, array_flip($volatile));

        ksort($significant);

        return hash('sha256', $type.'|'.json_encode($significant, JSON_THROW_ON_ERROR));
    }
}
