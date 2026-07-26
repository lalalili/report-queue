<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Jobs\RunReportExportJob;
use Lalalili\ReportQueue\Support\ReportNotifier;
use Lalalili\ReportQueue\Tests\Fixtures\RecordingExportHandler;

beforeEach(function (): void {
    $this->registry()->register('Widget export', RecordingExportHandler::class);
});

it('does not regenerate a file a previous attempt already produced', function (): void {
    $report = $this->downloadableReport(['type' => 'Widget export']);

    (new RunReportExportJob($report->getKey()))->handle();

    expect(RecordingExportHandler::$calls)->toHaveCount(0)
        ->and($report->refresh()->status)->toBe(ReportStatusEnum::FINISHED);
});

it('regenerates when the finished file has gone missing', function (): void {
    $report = $this->downloadableReport(['type' => 'Widget export']);

    Storage::disk('reports')->delete('reports/widget-export.xlsx');

    (new RunReportExportJob($report->getKey()))->handle();

    expect(RecordingExportHandler::$calls)->toHaveCount(1);
});

it('regenerates when the skip guard is turned off', function (): void {
    config()->set('report-queue.queue.skip_if_complete', false);

    $report = $this->downloadableReport(['type' => 'Widget export']);

    (new RunReportExportJob($report->getKey()))->handle();

    expect(RecordingExportHandler::$calls)->toHaveCount(1);
});

it('keeps the original start time across a retry', function (): void {
    $startedAt = now()->subMinutes(30);

    $report = $this->report([
        'type' => 'Widget export',
        'status' => ReportStatusEnum::RUNNING,
        'started_at' => $startedAt,
        'params' => ['filename' => 'widgets.xlsx'],
    ]);

    (new RunReportExportJob($report->getKey()))->handle();

    expect($report->refresh()->started_at?->timestamp)->toBe($startedAt->timestamp);
});

it('clears a stale error when retrying', function (): void {
    $report = $this->report([
        'type' => 'Widget export',
        'status' => ReportStatusEnum::FAILED,
        'error' => 'the previous attempt blew up',
        'params' => ['filename' => 'widgets.xlsx'],
    ]);

    (new RunReportExportJob($report->getKey()))->handle();

    expect($report->refresh()->error)->toBeNull();
});

describe('completion notification', function (): void {
    it('tells the owner exactly once even across retries', function (): void {
        $owner = $this->user();

        $report = $this->report([
            'type' => 'Widget export',
            'user_id' => $owner->getKey(),
            'params' => ['filename' => 'widgets.xlsx'],
        ]);

        (new RunReportExportJob($report->getKey()))->handle();
        (new RunReportExportJob($report->getKey()))->handle();

        expect($owner->notifications()->count())->toBe(1)
            ->and($report->refresh()->param(ReportNotifier::NOTIFIED_AT_PARAM))->toBeString();
    });

    it('still notifies on a retry that finished before the notice went out', function (): void {
        $owner = $this->user();

        // Finished, file present, but never announced.
        $report = $this->downloadableReport([
            'type' => 'Widget export',
            'user_id' => $owner->getKey(),
        ]);

        (new RunReportExportJob($report->getKey()))->handle();

        expect($owner->notifications()->count())->toBe(1);
    });

    it('does not stamp the report when there is nobody to notify', function (): void {
        $report = $this->report([
            'type' => 'Widget export',
            'user_id' => null,
            'params' => ['filename' => 'widgets.xlsx'],
        ]);

        (new RunReportExportJob($report->getKey()))->handle();

        expect($report->refresh()->param(ReportNotifier::NOTIFIED_AT_PARAM))->toBeNull();
    });
});

it('does not mark a finished report failed when a late failure callback fires', function (): void {
    $report = $this->downloadableReport(['type' => 'Widget export']);

    (new RunReportExportJob($report->getKey()))->failed(new RuntimeException('too late'));

    expect($report->refresh()->status)->toBe(ReportStatusEnum::FINISHED)
        ->and($report->error)->toBeNull();
});
