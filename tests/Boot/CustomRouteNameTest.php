<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lalalili\ReportQueue\Tests\TestCase;

/**
 * Each adopting host already has `route()` calls against its own name, so the
 * package has to register whatever name it is told to.
 */
class CustomRouteNameTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('report-queue.routes.prefix', 'admin2');
        $app['config']->set('report-queue.routes.download_name', 'admin2.reports.download');
    }
}

uses(CustomRouteNameTestCase::class);

it('registers the hosts own route name and prefix', function (): void {
    $this->assertTrue(Route::has('admin2.reports.download'));
    $this->assertFalse(Route::has('report-queue.download'));

    $this->assertSame(
        'admin2/reports/{report}/download',
        Route::getRoutes()->getByName('admin2.reports.download')?->uri(),
    );
});

it('links a report through the hosts route name', function (): void {
    $report = $this->downloadableReport();

    $this->assertStringContainsString(
        'admin2/reports/'.$report->getKey().'/download',
        (string) $report->downloadUrl(),
    );
});
