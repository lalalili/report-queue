<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Tests\Fixtures\HostSeams;

beforeEach(function (): void {
    config()->set('report-queue.super_admin', [HostSeams::class, 'isSuperAdmin']);
});

it('lets the owner download their report', function (): void {
    $owner = $this->user();
    $report = $this->downloadableReport(['user_id' => $owner->getKey()]);

    $this->actingAs($owner)
        ->get($report->downloadUrl())
        ->assertOk()
        ->assertDownload('widget-export.xlsx');
});

it('refuses another user', function (): void {
    $report = $this->downloadableReport(['user_id' => $this->user()->getKey()]);

    $this->actingAs($this->user())
        ->get($report->downloadUrl())
        ->assertForbidden();
});

it('lets a super admin download somebody else’s report', function (): void {
    $report = $this->downloadableReport(['user_id' => $this->user()->getKey()]);

    $this->actingAs($this->user(['is_super_admin' => true]))
        ->get($report->downloadUrl())
        ->assertOk();
});

it('refuses a guest', function (): void {
    $report = $this->downloadableReport(['user_id' => $this->user()->getKey()]);

    $this->get($report->downloadUrl())->assertRedirect();
});

it('returns 404 once the download window has passed', function (): void {
    $owner = $this->user();
    $report = $this->downloadableReport([
        'user_id' => $owner->getKey(),
        'finished_at' => now()->subHours(25),
    ]);

    $this->actingAs($owner)->get($report->downloadUrl())->assertNotFound();
});

it('returns 404 for a report that has not finished', function (): void {
    $owner = $this->user();
    $report = $this->downloadableReport([
        'user_id' => $owner->getKey(),
        'status' => ReportStatusEnum::RUNNING,
    ]);

    $this->actingAs($owner)->get($report->downloadUrl())->assertNotFound();
});

it('returns 404 when the row points at a file that is gone', function (): void {
    $owner = $this->user();
    $report = $this->downloadableReport(['user_id' => $owner->getKey()]);

    Storage::disk('reports')->delete('reports/widget-export.xlsx');

    $this->actingAs($owner)->get($report->downloadUrl())->assertNotFound();
});

it('returns 404 for an unknown report', function (): void {
    config()->set('report-queue.routes.download_name', 'report-queue.download');

    $this->actingAs($this->user())
        ->get(route('report-queue.download', 999999))
        ->assertNotFound();
});

it('serves a localized download name while keeping the stored path ascii', function (): void {
    $owner = $this->user();
    $report = $this->downloadableReport([
        'user_id' => $owner->getKey(),
        'params' => ['filename' => 'widget-export.xlsx', 'download_name' => '商品匯出.xlsx'],
    ]);

    $response = $this->actingAs($owner)->get($report->downloadUrl())->assertOk();

    // The UTF-8 name travels in the RFC 5987 parameter; `filename=` only ever
    // carries Laravel's ASCII fallback.
    expect(strtolower((string) $response->headers->get('content-disposition')))
        ->toContain("filename*=utf-8''".strtolower(rawurlencode('商品匯出.xlsx')));
});

it('checks ownership before existence, so probing cannot reveal other users’ reports', function (): void {
    // Expired (404 for the owner) but owned by somebody else: still 403.
    $report = $this->downloadableReport([
        'user_id' => $this->user()->getKey(),
        'finished_at' => now()->subHours(25),
    ]);

    $this->actingAs($this->user())
        ->get($report->downloadUrl())
        ->assertForbidden();
});
