<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Closure;
use Lalalili\ReportQueue\Models\Report;

/**
 * Everything a handler needs to produce one export file.
 */
final class ReportExportContext
{
    /**
     * @param  array<string, mixed>  $params
     * @param  list<mixed>  $ids
     * @param  Closure(int): void  $onProgress
     */
    public function __construct(
        public readonly Report $report,
        public readonly array $params,
        public readonly array $ids,
        public readonly string $disk,
        public readonly string $filename,
        public readonly string $path,
        private readonly Closure $onProgress,
    ) {}

    /**
     * @param  Closure(int): void  $onProgress
     */
    public static function for(
        Report $report,
        string $disk,
        string $filename,
        string $path,
        Closure $onProgress,
    ): self {
        $params = $report->params ?? [];
        $ids = $params['selected_ids'] ?? [];

        return new self(
            report: $report,
            params: $params,
            ids: is_array($ids) ? array_values($ids) : [],
            disk: $disk,
            filename: $filename,
            path: $path,
            onProgress: $onProgress,
        );
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return data_get($this->params, $key, $default);
    }

    /**
     * Report progress as a percentage. Writes are throttled by the job, so
     * handlers may call this as often as is convenient.
     */
    public function progress(int $percent): void
    {
        ($this->onProgress)($percent);
    }

    /**
     * Record how many rows the export actually produced.
     *
     * The count given when the export was queued is only an estimate — a
     * selection size, say — so handlers that know the real figure should report
     * it here. Writes only the count column, leaving the job in charge of
     * status and progress.
     */
    public function rowCount(int $count): void
    {
        $this->report->update(['count' => max(0, $count)]);
    }
}
