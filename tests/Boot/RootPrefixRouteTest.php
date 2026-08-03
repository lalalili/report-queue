<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lalalili\ReportQueue\Tests\TestCase;

/**
 * Most adopting hosts mount the download route at the root rather than under
 * `admin/`, so an explicitly empty prefix has to survive as a value instead of
 * falling back to the package default.
 */
class RootPrefixRouteTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('report-queue.routes.prefix', '');
        $app['config']->set('report-queue.routes.download_name', 'reports.download');
    }
}

uses(RootPrefixRouteTestCase::class);

it('mounts the download route at the root', function (): void {
    $this->assertTrue(Route::has('reports.download'));

    $this->assertSame(
        'reports/{report}/download',
        Route::getRoutes()->getByName('reports.download')?->uri(),
    );
});

it('links a report through the root mounted route', function (): void {
    $report = $this->downloadableReport();

    $this->assertStringContainsString(
        '/reports/'.$report->getKey().'/download',
        (string) $report->downloadUrl(),
    );
});
