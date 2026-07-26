<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Tests\Boot;

use Illuminate\Support\Facades\Route;
use Lalalili\ReportQueue\Tests\TestCase;

/**
 * Class-based because the seam is read while the package boots, so it cannot be
 * flipped from inside a test body. Kept out of the Pest `uses()` directories on
 * purpose.
 */
class RoutesDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('report-queue.routes.enabled', false);
    }

    public function test_it_registers_no_download_route_when_disabled(): void
    {
        $this->assertFalse(Route::has('report-queue.download'));
    }

    public function test_a_report_reports_no_download_url_when_routes_are_disabled(): void
    {
        $this->assertNull($this->downloadableReport()->downloadUrl());
    }
}
