<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Models\Report;
use Lalalili\ReportQueue\Support\Config;
use Lalalili\ReportQueue\Support\ReportModel;
use Lalalili\ReportQueue\Support\ReportStorage;

/**
 * Three-phase cleanup, meant to run hourly.
 *
 * Phase 1 enforces the download window, phase 2 stops abandoned rows from
 * spinning forever, phase 3 keeps the table small. Phases 1 and 3 both delete
 * files — phase 3 exists because a row can reach a terminal state while still
 * holding a file (a crash between upload and status write, for instance), and
 * skipping it leaks storage.
 */
class PruneReportsCommand extends Command
{
    protected $signature = 'report-queue:prune
                            {--dry-run : Report candidate counts without making changes}';

    protected $description = 'Expire downloadable report files, reclaim stuck exports, and purge old report records.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $expired = $this->expireFiles($dryRun);
        $reclaimed = $this->reclaimStuck($dryRun);
        $purged = $this->purgeOldRecords($dryRun);

        $this->info($dryRun
            ? "Dry run — expire: {$expired}, reclaim: {$reclaimed}, purge: {$purged}"
            : "Done — expired: {$expired}, reclaimed: {$reclaimed}, purged: {$purged}");

        return self::SUCCESS;
    }

    /**
     * Phase 1: finished reports past the download window lose their file and
     * become EXPIRED, so the UI can explain why the download is gone.
     */
    private function expireFiles(bool $dryRun): int
    {
        $hours = Config::int('retention.download_ttl_hours', 24);

        $query = ReportModel::query()
            ->where('status', ReportStatusEnum::FINISHED)
            ->where('finished_at', '<', now()->subHours($hours));

        $count = $query->count();

        if ($dryRun || $count === 0) {
            return $count;
        }

        $query->chunkById($this->chunkSize(), function (Collection $reports): void {
            /** @var Collection<int, Report> $reports */
            foreach ($reports as $report) {
                $this->deleteFile($report);

                $report->update([
                    'file_path' => null,
                    'file_disk' => null,
                    'status' => ReportStatusEnum::EXPIRED,
                ]);
            }
        });

        return $count;
    }

    /**
     * Phase 2: a pending or running report untouched for long enough has lost
     * its worker. Marking it FAILED frees the idempotency guard so the user can
     * retry.
     */
    private function reclaimStuck(bool $dryRun): int
    {
        $hours = Config::int('retention.stuck_after_hours', 6);

        $query = ReportModel::query()
            ->whereIn('status', ReportStatusEnum::active())
            ->where('updated_at', '<', now()->subHours($hours));

        $count = $query->count();

        if ($dryRun || $count === 0) {
            return $count;
        }

        $attributes = [
            'status' => ReportStatusEnum::FAILED,
            'error' => (string) trans('report-queue::messages.timeout_error'),
        ];

        if (Config::bool('retention.write_finished_at_on_timeout', true)) {
            $attributes['finished_at'] = now();
        }

        $query->update($attributes);

        return $count;
    }

    /**
     * Phase 3: delete terminal rows past the retention window, taking any file
     * still attached to them along.
     */
    private function purgeOldRecords(bool $dryRun): int
    {
        $days = Config::int('retention.purge_after_days', 7);

        $query = ReportModel::query()
            ->whereIn('status', ReportStatusEnum::terminal())
            ->where('created_at', '<', now()->subDays($days));

        $count = $query->count();

        if ($dryRun || $count === 0) {
            return $count;
        }

        if (Config::bool('retention.delete_files_on_purge', true)) {
            $query->clone()->chunkById($this->chunkSize(), function (Collection $reports): void {
                /** @var Collection<int, Report> $reports */
                foreach ($reports as $report) {
                    $this->deleteFile($report);
                }
            });
        }

        $query->delete();

        return $count;
    }

    private function deleteFile(Report $report): void
    {
        if (! filled($report->file_path)) {
            return;
        }

        $disk = filled($report->file_disk)
            ? (string) $report->file_disk
            : app(ReportStorage::class)->disk();

        $path = (string) $report->file_path;

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function chunkSize(): int
    {
        $size = Config::int('retention.chunk_size', 100);

        return $size > 0 ? $size : 100;
    }
}
