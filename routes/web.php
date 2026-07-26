<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lalalili\ReportQueue\Http\Controllers\ReportDownloadController;
use Lalalili\ReportQueue\Support\Config;

if (! Config::bool('routes.enabled', true)) {
    return;
}

$middleware = Config::stringList('routes.middleware');
$name = Config::string('routes.download_name', 'report-queue.download');

Route::prefix(Config::stringOrEmpty('routes.prefix', 'admin'))
    ->middleware($middleware === [] ? ['web', 'auth'] : $middleware)
    ->group(function () use ($name): void {
        Route::get('reports/{report}/download', ReportDownloadController::class)->name($name);
    });
