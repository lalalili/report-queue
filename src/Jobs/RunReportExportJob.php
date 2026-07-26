<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Jobs;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Events\ReportExportCompleted;
use Lalalili\ReportQueue\Events\ReportExportFailed;
use Lalalili\ReportQueue\Events\ReportExportStarted;
use Lalalili\ReportQueue\Models\Report;
use Lalalili\ReportQueue\Support\Config;
use Lalalili\ReportQueue\Support\ReportExportContext;
use Lalalili\ReportQueue\Support\ReportExportRegistry;
use Lalalili\ReportQueue\Support\ReportModel;
use Lalalili\ReportQueue\Support\ReportNotifier;
use Lalalili\ReportQueue\Support\ReportStorage;
use Throwable;

/**
 * Runs one registered export handler and keeps the report row in step with it.
 *
 * The job carries only the report id: the row is the single source of truth for
 * status, so a retry always resumes from the persisted state rather than from a
 * serialized snapshot.
 */
class RunReportExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    /** @var list<int> */
    public array $backoff;

    public function __construct(public readonly int|string $reportId)
    {
        $this->tries = Config::int('queue.tries', 3);
        $this->timeout = Config::int('queue.timeout', 1800);
        $this->backoff = Config::intList('queue.backoff', [60, 300]);

        $connection = Config::nullableString('queue.connection');

        if ($connection !== null) {
            $this->onConnection($connection);
        }

        $queue = Config::nullableString('queue.queue');

        if ($queue !== null) {
            $this->onQueue($queue);
        }
    }

    public function handle(): void
    {
        $report = ReportModel::findOrFail($this->reportId);

        $report->update([
            'status' => ReportStatusEnum::RUNNING,
            'started_at' => now(),
            'heartbeat_at' => now(),
            'progress' => 10,
        ]);

        event(new ReportExportStarted($report));

        $storage = app(ReportStorage::class);
        $disk = filled($report->file_disk) ? (string) $report->file_disk : $storage->disk();

        $filename = $report->param('filename');
        $filename = is_string($filename) && $filename !== ''
            ? $filename
            : $storage->filename($report->type.'_');

        $path = $storage->path($report, $filename);

        $context = ReportExportContext::for($report, $disk, $filename, $path, $this->progressWriter($report));

        $storedPath = app(ReportExportRegistry::class)->resolve($report->type)->handle($context);

        $report->update([
            'status' => ReportStatusEnum::FINISHED,
            'progress' => 100,
            'file_path' => $storedPath,
            'file_disk' => $disk,
            'heartbeat_at' => now(),
            'finished_at' => now(),
        ]);

        event(new ReportExportCompleted($report));

        app(ReportNotifier::class)->finished($report);
    }

    public function failed(Throwable $exception): void
    {
        $report = ReportModel::find($this->reportId);

        ReportModel::query()->whereKey($this->reportId)->update([
            'status' => ReportStatusEnum::FAILED,
            'error' => $exception->getMessage(),
        ]);

        $channel = Config::nullableString('notifications.log_channel');

        if ($channel !== null) {
            Log::channel($channel)->critical(
                'Report export failed [id='.$this->reportId.']: '.$exception->getMessage()
            );
        }

        if ($report === null) {
            return;
        }

        $report->refresh();

        event(new ReportExportFailed($report, $exception));

        app(ReportNotifier::class)->failed($report);
    }

    /**
     * Throttled progress writer: handlers may report progress freely, but the
     * row is only touched when progress advances by the configured step or the
     * heartbeat interval has elapsed.
     *
     * @return Closure(int): void
     */
    private function progressWriter(Report $report): Closure
    {
        // The caller has just written progress, so that write is the baseline
        // both throttles measure from.
        $lastWriteAt = now();
        $lastProgress = $report->progress;

        return function (int $percent) use ($report, &$lastWriteAt, &$lastProgress): void {
            $percent = max(0, min(100, $percent));

            $step = Config::int('heartbeat.progress_step', 10);
            $interval = Config::int('heartbeat.interval_seconds', 60);
            $now = now();

            $dueByProgress = $percent >= $lastProgress + $step;
            $dueByTime = $lastWriteAt->diffInSeconds($now) >= $interval;

            if (! $dueByProgress && ! $dueByTime) {
                return;
            }

            $attributes = ['progress' => $percent];

            if (Config::bool('heartbeat.enabled', true)) {
                $attributes['heartbeat_at'] = $now;
            }

            $report->update($attributes);

            $lastWriteAt = $now;
            $lastProgress = $percent;
        };
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['report-queue', 'report:'.$this->reportId];
    }
}
