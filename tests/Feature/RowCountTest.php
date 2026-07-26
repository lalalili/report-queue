<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Jobs\RunReportExportJob;
use Lalalili\ReportQueue\Support\ReportExportContext;

it('lets a handler replace the queued estimate with the real row count', function (): void {
    $this->registry()->register('Widget export', function (ReportExportContext $context): string {
        $context->rowCount(42);

        Storage::disk($context->disk)->put($context->path, 'generated');

        return $context->path;
    });

    $report = $this->report(['count' => 100, 'params' => ['filename' => 'widgets.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();

    $report->refresh();

    expect($report->count)->toBe(42)
        // The job's own completion write must not clobber the reported count.
        ->and($report->status)->toBe(ReportStatusEnum::FINISHED)
        ->and($report->progress)->toBe(100);
});

it('keeps the queued count when a handler reports nothing', function (): void {
    $this->registry()->register('Widget export', function (ReportExportContext $context): string {
        Storage::disk($context->disk)->put($context->path, 'generated');

        return $context->path;
    });

    $report = $this->report(['count' => 7, 'params' => ['filename' => 'widgets.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();

    expect($report->refresh()->count)->toBe(7);
});

it('never records a negative row count', function (): void {
    $this->registry()->register('Widget export', function (ReportExportContext $context): string {
        $context->rowCount(-5);

        Storage::disk($context->disk)->put($context->path, 'generated');

        return $context->path;
    });

    $report = $this->report(['count' => 3, 'params' => ['filename' => 'widgets.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();

    expect($report->refresh()->count)->toBe(0);
});
