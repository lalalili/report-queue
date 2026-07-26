<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lalalili\ReportQueue\Contracts\ReportExportHandler;
use Lalalili\ReportQueue\Support\ReportExportContext;

class RecordingExportHandler implements ReportExportHandler
{
    /** @var list<ReportExportContext> */
    public static array $calls = [];

    /** Progress percentages the handler should report, in order. @var list<int> */
    public static array $progressReports = [];

    /** Persisted progress observed after each report, for throttling assertions. @var list<int> */
    public static array $observedProgress = [];

    public function handle(ReportExportContext $context): string
    {
        self::$calls[] = $context;

        foreach (self::$progressReports as $percent) {
            $context->progress($percent);

            // Read straight from the database: the point is what was persisted,
            // not what the in-memory model thinks.
            self::$observedProgress[] = (int) DB::table($context->report->getTable())
                ->where('id', $context->report->getKey())
                ->value('progress');
        }

        Storage::disk($context->disk)->put($context->path, 'generated');

        return $context->path;
    }

    public static function reset(): void
    {
        self::$calls = [];
        self::$progressReports = [];
        self::$observedProgress = [];
    }
}
