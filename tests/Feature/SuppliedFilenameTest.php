<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Lalalili\ReportQueue\Actions\QueueReportExport;
use Lalalili\ReportQueue\Tests\Fixtures\RecordingExportHandler;

beforeEach(function (): void {
    $this->registry()->register('Widget export', RecordingExportHandler::class);
    Bus::fake();
    $this->actingAs($this->user());
});

it('keeps a filename the caller supplied', function (): void {
    $report = QueueReportExport::dispatch('Widget export', 'ignored_', [
        'filename' => 'survey-results-42-20260726120000.xlsx',
    ]);

    expect($report->param('filename'))->toBe('survey-results-42-20260726120000.xlsx');
});

it('generates a filename when the caller supplies none', function (): void {
    $report = QueueReportExport::dispatch('Widget export', 'widgets_');

    expect($report->param('filename'))->toMatch('/^widgets_\d{14}_[A-Za-z0-9]{6}\.xlsx$/');
});

it('ignores an empty supplied filename', function (): void {
    $report = QueueReportExport::dispatch('Widget export', 'widgets_', ['filename' => '']);

    expect($report->param('filename'))->toStartWith('widgets_');
});

it('keeps a separate localized download name alongside the ascii path', function (): void {
    $report = QueueReportExport::dispatch('Widget export', 'ignored_', [
        'filename' => 'survey-results-42.xlsx',
        'download_name' => '問卷回覆-顧客滿意度-20260726.xlsx',
    ]);

    expect($report->param('filename'))->toBe('survey-results-42.xlsx')
        ->and($report->downloadName())->toBe('問卷回覆-顧客滿意度-20260726.xlsx');
});
