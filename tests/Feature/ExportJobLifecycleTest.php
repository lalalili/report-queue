<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Events\ReportExportCompleted;
use Lalalili\ReportQueue\Events\ReportExportFailed;
use Lalalili\ReportQueue\Events\ReportExportStarted;
use Lalalili\ReportQueue\Exceptions\UnknownReportTypeException;
use Lalalili\ReportQueue\Jobs\RunReportExportJob;
use Lalalili\ReportQueue\Tests\Fixtures\FailingExportHandler;
use Lalalili\ReportQueue\Tests\Fixtures\RecordingExportHandler;

it('runs an export through to a downloadable file', function (): void {
    $this->registry()->register('Widget export', RecordingExportHandler::class);

    $owner = $this->user();
    $report = $this->report(['user_id' => $owner->getKey(), 'params' => ['filename' => 'widgets.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();

    $report->refresh();

    expect($report->status)->toBe(ReportStatusEnum::FINISHED)
        ->and($report->progress)->toBe(100)
        ->and($report->file_path)->toBe('reports/widgets.xlsx')
        ->and($report->file_disk)->toBe('reports')
        ->and($report->started_at)->not->toBeNull()
        ->and($report->finished_at)->not->toBeNull()
        ->and($report->isDownloadable())->toBeTrue();

    Storage::disk('reports')->assertExists('reports/widgets.xlsx');
});

it('fires lifecycle events', function (): void {
    Event::fake([ReportExportStarted::class, ReportExportCompleted::class]);

    $this->registry()->register('Widget export', RecordingExportHandler::class);
    $report = $this->report(['params' => ['filename' => 'widgets.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();

    Event::assertDispatched(ReportExportStarted::class);
    Event::assertDispatched(ReportExportCompleted::class);
});

it('notifies the owner when the export is ready', function (): void {
    $this->registry()->register('Widget export', RecordingExportHandler::class);

    $owner = $this->user();
    $report = $this->report(['user_id' => $owner->getKey(), 'params' => ['filename' => 'widgets.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();

    expect($owner->notifications()->count())->toBe(1);
});

it('can be told not to notify on completion', function (): void {
    config()->set('report-queue.notifications.on_finished', false);

    $this->registry()->register('Widget export', RecordingExportHandler::class);

    $owner = $this->user();
    $report = $this->report(['user_id' => $owner->getKey(), 'params' => ['filename' => 'widgets.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();

    expect($owner->notifications()->count())->toBe(0);
});

it('records the failure on the report and notifies the owner', function (): void {
    $this->registry()->register('Widget export', FailingExportHandler::class);

    $owner = $this->user();
    $report = $this->report(['user_id' => $owner->getKey(), 'params' => ['filename' => 'widgets.xlsx']]);

    $job = new RunReportExportJob($report->getKey());

    try {
        $job->handle();
    } catch (RuntimeException $exception) {
        $job->failed($exception);
    }

    $report->refresh();

    expect($report->status)->toBe(ReportStatusEnum::FAILED)
        ->and($report->error)->toBe(FailingExportHandler::MESSAGE)
        ->and($report->isDownloadable())->toBeFalse()
        ->and($owner->notifications()->count())->toBe(1);
});

it('fires the failure event', function (): void {
    Event::fake([ReportExportFailed::class]);

    $this->registry()->register('Widget export', FailingExportHandler::class);
    $report = $this->report(['params' => ['filename' => 'widgets.xlsx']]);

    (new RunReportExportJob($report->getKey()))->failed(new RuntimeException('boom'));

    Event::assertDispatched(ReportExportFailed::class);
});

it('fails loudly for a type nobody registered', function (): void {
    $report = $this->report(['type' => 'Never registered', 'params' => ['filename' => 'x.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();
})->throws(UnknownReportTypeException::class);

it('takes its retry policy from config', function (): void {
    config()->set('report-queue.queue.tries', 5);
    config()->set('report-queue.queue.timeout', 900);
    config()->set('report-queue.queue.backoff', [30, 90]);

    $job = new RunReportExportJob(1);

    expect($job->tries)->toBe(5)
        ->and($job->timeout)->toBe(900)
        ->and($job->backoff)->toBe([30, 90]);
});

it('routes itself to the configured connection and queue', function (): void {
    config()->set('report-queue.queue.connection', 'redis-long-running');
    config()->set('report-queue.queue.queue', 'long-running-queue');

    $job = new RunReportExportJob(1);

    expect($job->connection)->toBe('redis-long-running')
        ->and($job->queue)->toBe('long-running-queue');
});

it('honours a disk already pinned on the report', function (): void {
    config()->set('filesystems.disks.archive', ['driver' => 'local', 'root' => sys_get_temp_dir().'/rq-archive']);
    Storage::fake('archive');

    $this->registry()->register('Widget export', RecordingExportHandler::class);

    $report = $this->report(['file_disk' => 'archive', 'params' => ['filename' => 'widgets.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();

    Storage::disk('archive')->assertExists('reports/widgets.xlsx');
    expect($report->refresh()->file_disk)->toBe('archive');
});
