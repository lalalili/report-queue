<?php

declare(strict_types=1);

use Lalalili\ReportQueue\Support\ReportStorage;
use Lalalili\ReportQueue\Tests\Fixtures\HostSeams;

it('names files with a timestamp and a random suffix so concurrent exports never collide', function (): void {
    $storage = app(ReportStorage::class);

    $first = $storage->filename('products_', 'xlsx');
    $second = $storage->filename('products_', 'xlsx');

    expect($first)->toMatch('/^products_\d{14}_[A-Za-z0-9]{6}\.xlsx$/')
        ->and($second)->not->toBe($first);
});

it('honours the requested extension', function (): void {
    expect(app(ReportStorage::class)->filename('erp_', 'zip'))->toEndWith('.zip');
});

it('prefixes paths with the configured folder', function (): void {
    $report = $this->report();

    expect(app(ReportStorage::class)->path($report, 'products.xlsx'))->toBe('reports/products.xlsx');
});

it('lets a host resolve paths itself, for example into monthly folders', function (): void {
    config()->set('report-queue.storage.path_resolver', [HostSeams::class, 'monthlyPath']);

    $report = $this->report();

    expect(app(ReportStorage::class)->path($report, 'erp.zip'))
        ->toBe('reports/erp/'.$report->created_at?->format('Y/m').'/'.$report->getKey().'_erp.zip');
});

it('lets a host resolve filenames itself', function (): void {
    config()->set('report-queue.storage.filename_resolver', [HostSeams::class, 'fixedFilename']);

    expect(app(ReportStorage::class)->filename('erp_', 'zip'))->toBe('erp_fixed.zip');
});

it('falls back to the default filesystem disk when none is configured', function (): void {
    config()->set('report-queue.storage.disk', null);
    config()->set('filesystems.default', 'reports');

    expect(app(ReportStorage::class)->disk())->toBe('reports');
});

it('prefers an explicitly configured disk', function (): void {
    config()->set('report-queue.storage.disk', 's3-report');

    expect(app(ReportStorage::class)->disk())->toBe('s3-report');
});
