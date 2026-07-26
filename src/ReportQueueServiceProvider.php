<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue;

use Illuminate\Support\Facades\Gate;
use Lalalili\ReportQueue\Commands\PruneReportsCommand;
use Lalalili\ReportQueue\Models\Report;
use Lalalili\ReportQueue\Policies\ReportPolicy;
use Lalalili\ReportQueue\Support\ReportAccess;
use Lalalili\ReportQueue\Support\ReportExportRegistry;
use Lalalili\ReportQueue\Support\ReportModel;
use Lalalili\ReportQueue\Support\ReportNotifier;
use Lalalili\ReportQueue\Support\ReportStorage;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ReportQueueServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('report-queue')
            ->hasConfigFile('report-queue')
            ->hasTranslations()
            ->hasRoutes(['web'])
            ->hasMigration('2026_08_01_000000_create_report_queue_tables')
            ->runsMigrations()
            ->hasCommand(PruneReportsCommand::class);
    }

    public function registeringPackage(): void
    {
        // The registry must be a singleton: hosts populate it once at boot and
        // the job resolves from the same instance.
        $this->app->singleton(ReportExportRegistry::class);
        $this->app->singleton(ReportStorage::class);
        $this->app->singleton(ReportAccess::class);
        $this->app->singleton(ReportNotifier::class);
    }

    public function bootingPackage(): void
    {
        if (config('report-queue.authorization.register_policy', true) === true) {
            Gate::policy(ReportModel::modelClass(), ReportPolicy::class);

            if (ReportModel::modelClass() !== Report::class) {
                Gate::policy(Report::class, ReportPolicy::class);
            }
        }
    }
}
