<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Models\Report;
use Lalalili\ReportQueue\ReportQueueServiceProvider;
use Lalalili\ReportQueue\Support\ReportExportRegistry;
use Lalalili\ReportQueue\Support\ReportModel;
use Lalalili\ReportQueue\Tests\Fixtures\HostSeams;
use Lalalili\ReportQueue\Tests\Fixtures\RecordingExportHandler;
use Lalalili\ReportQueue\Tests\Fixtures\TestUser;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RuntimeException;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ReportQueueServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        if ($app->configurationIsCached()) {
            throw new RuntimeException('Cached configuration detected — clear it before running the package suite.');
        }

        tap($app['config'], function ($config): void {
            $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

            $config->set('database.default', 'testing');
            $config->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);

            $config->set('auth.providers.users.model', TestUser::class);

            // Left null so the package migration does not need `users` to exist
            // first; MigrationCompatTest covers the foreign-key branch.
            $config->set('report-queue.user.table', null);

            $config->set('filesystems.default', 'reports');
            $config->set('filesystems.disks.reports', [
                'driver' => 'local',
                'root' => $this->reportDiskRoot(),
                'throw' => false,
            ]);

            $config->set('queue.default', 'sync');
            $config->set('report-queue.queue.connection', null);
            $config->set('report-queue.queue.queue', null);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('reports');
        RecordingExportHandler::reset();
        HostSeams::$viewAnyAnswer = true;
    }

    /**
     * A named `login` route so the auth middleware can redirect guests instead
     * of failing to build a redirect target.
     */
    protected function defineRoutes($router): void
    {
        $router->get('login', fn (): string => 'login')->name('login');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->boolean('is_super_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    protected function reportDiskRoot(): string
    {
        return sys_get_temp_dir().'/report-queue-tests';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function user(array $attributes = []): TestUser
    {
        static $sequence = 0;
        $sequence++;

        return TestUser::query()->create(array_merge([
            'name' => 'User '.$sequence,
            'email' => "user{$sequence}@example.test",
            'is_super_admin' => false,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function report(array $attributes = []): Report
    {
        return ReportModel::create(array_merge([
            'type' => 'Widget export',
            'status' => ReportStatusEnum::PENDING,
            'progress' => 0,
            'count' => 0,
        ], $attributes));
    }

    /**
     * A finished, downloadable report with its file actually on disk.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function downloadableReport(array $attributes = []): Report
    {
        $path = 'reports/widget-export.xlsx';

        Storage::disk('reports')->put($path, 'spreadsheet');

        return $this->report(array_merge([
            'status' => ReportStatusEnum::FINISHED,
            'progress' => 100,
            'file_path' => $path,
            'file_disk' => 'reports',
            'finished_at' => now(),
            'params' => ['filename' => 'widget-export.xlsx'],
        ], $attributes));
    }

    protected function registry(): ReportExportRegistry
    {
        return app(ReportExportRegistry::class);
    }
}
