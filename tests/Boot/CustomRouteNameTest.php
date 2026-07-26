<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Tests\Boot;

use Illuminate\Support\Facades\Route;
use Lalalili\ReportQueue\Tests\TestCase;

/**
 * Each adopting host already has `route()` calls against its own name, so the
 * package has to register whatever name it is told to.
 */
class CustomRouteNameTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('report-queue.routes.prefix', 'admin2');
        $app['config']->set('report-queue.routes.download_name', 'admin2.reports.download');
    }

    public function test_it_registers_the_hosts_own_route_name_and_prefix(): void
    {
        $this->assertTrue(Route::has('admin2.reports.download'));
        $this->assertFalse(Route::has('report-queue.download'));

        $this->assertSame(
            'admin2/reports/{report}/download',
            Route::getRoutes()->getByName('admin2.reports.download')?->uri(),
        );
    }

    public function test_a_report_links_through_the_hosts_route_name(): void
    {
        $report = $this->downloadableReport();

        $this->assertStringContainsString(
            'admin2/reports/'.$report->getKey().'/download',
            (string) $report->downloadUrl(),
        );
    }
}
