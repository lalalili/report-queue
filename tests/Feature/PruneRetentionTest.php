<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Support\ReportModel;

it('expires finished reports past the download window and deletes their files', function (): void {
    $report = $this->downloadableReport(['finished_at' => now()->subHours(25)]);

    $this->artisan('report-queue:prune')->assertSuccessful();

    $report->refresh();

    expect($report->status)->toBe(ReportStatusEnum::EXPIRED)
        ->and($report->file_path)->toBeNull()
        ->and($report->file_disk)->toBeNull();

    Storage::disk('reports')->assertMissing('reports/widget-export.xlsx');
});

it('leaves a finished report inside the download window alone', function (): void {
    $report = $this->downloadableReport(['finished_at' => now()->subHours(23)]);

    $this->artisan('report-queue:prune')->assertSuccessful();

    expect($report->refresh()->status)->toBe(ReportStatusEnum::FINISHED)
        ->and($report->file_path)->not->toBeNull();
});

it('honours a custom download window', function (): void {
    config()->set('report-queue.retention.download_ttl_hours', 1);

    $report = $this->downloadableReport(['finished_at' => now()->subHours(2)]);

    $this->artisan('report-queue:prune')->assertSuccessful();

    expect($report->refresh()->status)->toBe(ReportStatusEnum::EXPIRED);
});

it('reclaims exports stuck in flight', function (): void {
    $report = $this->report(['status' => ReportStatusEnum::RUNNING]);
    $report->forceFill(['updated_at' => now()->subHours(7)])->saveQuietly();

    $this->artisan('report-queue:prune')->assertSuccessful();

    $report->refresh();

    expect($report->status)->toBe(ReportStatusEnum::FAILED)
        ->and($report->error)->toBe(trans('report-queue::messages.timeout_error'))
        ->and($report->finished_at)->not->toBeNull();
});

it('can be told not to stamp finished_at when reclaiming', function (): void {
    config()->set('report-queue.retention.write_finished_at_on_timeout', false);

    $report = $this->report(['status' => ReportStatusEnum::PENDING]);
    $report->forceFill(['updated_at' => now()->subHours(7)])->saveQuietly();

    $this->artisan('report-queue:prune')->assertSuccessful();

    expect($report->refresh()->finished_at)->toBeNull();
});

it('leaves a recently touched running export alone', function (): void {
    $report = $this->report(['status' => ReportStatusEnum::RUNNING]);

    $this->artisan('report-queue:prune')->assertSuccessful();

    expect($report->refresh()->status)->toBe(ReportStatusEnum::RUNNING);
});

it('purges terminal reports past the retention window', function (): void {
    $old = $this->report(['status' => ReportStatusEnum::EXPIRED]);
    $old->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();

    $recent = $this->report(['status' => ReportStatusEnum::EXPIRED]);

    $this->artisan('report-queue:prune')->assertSuccessful();

    expect(ReportModel::find($old->getKey()))->toBeNull()
        ->and(ReportModel::find($recent->getKey()))->not->toBeNull();
});

it('never purges a report that is still in flight, however old the row is', function (): void {
    // Old enough for phase 3, but recently touched so phase 2 leaves it running.
    $report = $this->report(['status' => ReportStatusEnum::RUNNING, 'heartbeat_at' => now()]);
    $report->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

    $this->artisan('report-queue:prune')->assertSuccessful();

    expect(ReportModel::find($report->getKey())?->status)->toBe(ReportStatusEnum::RUNNING);
});

it('deletes files still attached to purged rows instead of leaking them', function (): void {
    $report = $this->downloadableReport(['status' => ReportStatusEnum::FAILED]);
    $report->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();

    $this->artisan('report-queue:prune')->assertSuccessful();

    Storage::disk('reports')->assertMissing('reports/widget-export.xlsx');
    expect(ReportModel::find($report->getKey()))->toBeNull();
});

it('can be told to purge rows without touching their files', function (): void {
    config()->set('report-queue.retention.delete_files_on_purge', false);

    $report = $this->downloadableReport(['status' => ReportStatusEnum::FAILED]);
    $report->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();

    $this->artisan('report-queue:prune')->assertSuccessful();

    Storage::disk('reports')->assertExists('reports/widget-export.xlsx');
});

it('changes nothing on a dry run', function (): void {
    $expiring = $this->downloadableReport(['finished_at' => now()->subHours(25)]);

    $stuck = $this->report(['status' => ReportStatusEnum::RUNNING]);
    $stuck->forceFill(['updated_at' => now()->subHours(7)])->saveQuietly();

    $purgeable = $this->report(['status' => ReportStatusEnum::EXPIRED]);
    $purgeable->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();

    $this->artisan('report-queue:prune --dry-run')
        ->expectsOutputToContain('expire: 1, reclaim: 1, purge: 1')
        ->assertSuccessful();

    expect($expiring->refresh()->status)->toBe(ReportStatusEnum::FINISHED)
        ->and($stuck->refresh()->status)->toBe(ReportStatusEnum::RUNNING)
        ->and(ReportModel::find($purgeable->getKey()))->not->toBeNull();

    Storage::disk('reports')->assertExists('reports/widget-export.xlsx');
});
