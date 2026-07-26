<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Tests\Boot;

use Illuminate\Support\Facades\Route;
use Lalalili\ReportQueue\Tests\TestCase;

/**
 * Most adopting hosts mount the download route at the root rather than under
 * `admin/`, so an explicitly empty prefix has to survive as a value instead of
 * falling back to the package default.
 */
class RootPrefixRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('report-queue.routes.prefix', '');
        $app['config']->set('report-queue.routes.download_name', 'reports.download');
    }

    public function test_it_mounts_the_download_route_at_the_root(): void
    {
        $this->assertTrue(Route::has('reports.download'));

        $this->assertSame(
            'reports/{report}/download',
            Route::getRoutes()->getByName('reports.download')?->uri(),
        );
    }

    public function test_a_report_links_through_the_root_mounted_route(): void
    {
        $report = $this->downloadableReport();

        $this->assertStringContainsString(
            '/reports/'.$report->getKey().'/download',
            (string) $report->downloadUrl(),
        );
    }
}
