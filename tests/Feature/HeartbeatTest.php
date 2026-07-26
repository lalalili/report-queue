<?php

declare(strict_types=1);

use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Jobs\RunReportExportJob;
use Lalalili\ReportQueue\Tests\Fixtures\RecordingExportHandler;

beforeEach(function (): void {
    $this->registry()->register('Widget export', RecordingExportHandler::class);
});

it('marks a running report stalled once the heartbeat goes quiet', function (): void {
    $report = $this->report([
        'status' => ReportStatusEnum::RUNNING,
        'started_at' => now()->subHour(),
        'heartbeat_at' => now()->subMinutes(11),
    ]);

    expect($report->isStalled())->toBeTrue();
});

it('does not call a report stalled while the heartbeat is fresh', function (): void {
    $report = $this->report([
        'status' => ReportStatusEnum::RUNNING,
        'started_at' => now()->subHour(),
        'heartbeat_at' => now()->subMinutes(9),
    ]);

    expect($report->isStalled())->toBeFalse();
});

it('falls back to started_at when no heartbeat was ever written', function (): void {
    $report = $this->report([
        'status' => ReportStatusEnum::RUNNING,
        'started_at' => now()->subMinutes(11),
    ]);

    expect($report->isStalled())->toBeTrue();
});

it('never calls a terminal report stalled', function (): void {
    $report = $this->report([
        'status' => ReportStatusEnum::FINISHED,
        'started_at' => now()->subDay(),
        'heartbeat_at' => now()->subDay(),
    ]);

    expect($report->isStalled())->toBeFalse();
});

it('never calls a report stalled before it started', function (): void {
    expect($this->report(['status' => ReportStatusEnum::PENDING])->isStalled())->toBeFalse();
});

it('stops detecting stalls when the feature is disabled', function (): void {
    config()->set('report-queue.heartbeat.enabled', false);

    $report = $this->report([
        'status' => ReportStatusEnum::RUNNING,
        'heartbeat_at' => now()->subHours(3),
    ]);

    expect($report->isStalled())->toBeFalse();
});

it('writes a heartbeat when the job starts', function (): void {
    $report = $this->report(['params' => ['filename' => 'widgets.xlsx']]);

    RecordingExportHandler::$progressReports = [];

    (new RunReportExportJob($report->getKey()))->handle();

    expect($report->refresh()->heartbeat_at)->not->toBeNull();
});

it('throttles progress writes to the configured step', function (): void {
    config()->set('report-queue.heartbeat.progress_step', 10);
    config()->set('report-queue.heartbeat.interval_seconds', 3600);

    $report = $this->report(['params' => ['filename' => 'widgets.xlsx']]);

    // The job writes 10 before handing over, so 11 and 15 sit below the next
    // step and are dropped; 20 and 40 cross it and are persisted.
    RecordingExportHandler::$progressReports = [11, 15, 20, 25, 40];

    (new RunReportExportJob($report->getKey()))->handle();

    expect(RecordingExportHandler::$observedProgress)->toBe([10, 10, 20, 20, 40])
        ->and($report->refresh()->progress)->toBe(100);
});

it('writes on the heartbeat interval even when progress barely moves', function (): void {
    config()->set('report-queue.heartbeat.progress_step', 999);
    config()->set('report-queue.heartbeat.interval_seconds', 0);

    $report = $this->report(['params' => ['filename' => 'widgets.xlsx']]);

    RecordingExportHandler::$progressReports = [11, 12];

    (new RunReportExportJob($report->getKey()))->handle();

    expect(RecordingExportHandler::$observedProgress)->toBe([11, 12]);
});

it('clamps progress into 0-100', function (): void {
    config()->set('report-queue.heartbeat.progress_step', 1);

    $report = $this->report(['params' => ['filename' => 'widgets.xlsx']]);

    RecordingExportHandler::$progressReports = [-50, 500];

    (new RunReportExportJob($report->getKey()))->handle();

    expect(RecordingExportHandler::$observedProgress)->toBe([10, 100]);
});
