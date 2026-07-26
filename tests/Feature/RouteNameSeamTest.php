<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lalalili\ReportQueue\Http\Controllers\ReportDownloadController;

it('registers the download route under the configured name', function (): void {
    expect(Route::has('report-queue.download'))->toBeTrue();
});

it('resolves the download url from config rather than a hardcoded name', function (): void {
    Route::get('legacy/reports/{report}/download', ReportDownloadController::class)->name('admin.reports.download');

    // Names applied after registration are not in the lookup table until the
    // collection is recompiled, which a real request would do for us.
    Route::getRoutes()->refreshNameLookups();

    config()->set('report-queue.routes.download_name', 'admin.reports.download');

    $report = $this->downloadableReport();

    expect($report->downloadUrl())->toContain('legacy/reports/'.$report->getKey().'/download');
});

it('returns no download url when the configured route is not registered', function (): void {
    config()->set('report-queue.routes.download_name', 'nope.missing');

    expect($this->downloadableReport()->downloadUrl())->toBeNull();
});

it('places the route under the configured prefix', function (): void {
    expect(Route::getRoutes()->getByName('report-queue.download')?->uri())
        ->toBe('admin/reports/{report}/download');
});
