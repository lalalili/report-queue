<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lalalili\ReportQueue\Tests\TestCase;

/**
 * The configuration is read while the package boots, so this scenario uses a
 * dedicated Pest base class instead of changing it inside a test body.
 */
class RoutesDisabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('report-queue.routes.enabled', false);
    }
}

uses(RoutesDisabledTestCase::class);

it('registers no download route when disabled', function (): void {
    $this->assertFalse(Route::has('report-queue.download'));
});

it('reports no download URL when routes are disabled', function (): void {
    $this->assertNull($this->downloadableReport()->downloadUrl());
});
