<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The migration has to be safe to run against a table that already exists in
 * production, because every host adopting this package brought its own.
 */
function runPackageMigration(): void
{
    $migration = require __DIR__.'/../../database/migrations/2026_08_01_000000_create_report_queue_tables.php';

    $migration->up();
}

/**
 * A `reports` table as it looked in a host before this package: no file_disk,
 * no heartbeat_at.
 */
function createLegacyReportsTable(): void
{
    Schema::dropIfExists('reports');

    Schema::create('reports', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable()->index();
        $table->string('type');
        $table->json('params')->nullable();
        $table->tinyInteger('status')->index();
        $table->tinyInteger('progress')->default(0);
        $table->unsignedInteger('count')->default(0);
        $table->string('file_path')->nullable();
        $table->text('error')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('finished_at')->nullable();
        $table->timestamps();
    });
}

it('creates the full table on a host that has none', function (): void {
    expect(Schema::hasTable('reports'))->toBeTrue();

    foreach ([
        'id', 'user_id', 'type', 'params', 'status', 'progress', 'count',
        'file_path', 'file_disk', 'error', 'heartbeat_at', 'started_at',
        'finished_at', 'created_at', 'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('reports', $column))->toBeTrue();
    }
});

it('tops up a legacy table instead of recreating it', function (): void {
    createLegacyReportsTable();

    DB::table('reports')->insert([
        'id' => 7,
        'type' => '商品匯出',
        'status' => 3,
        'progress' => 100,
        'count' => 12,
        'file_path' => 'reports/legacy.xlsx',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runPackageMigration();

    expect(Schema::hasColumn('reports', 'file_disk'))->toBeTrue()
        ->and(Schema::hasColumn('reports', 'heartbeat_at'))->toBeTrue();

    $row = DB::table('reports')->find(7);

    expect($row->type)->toBe('商品匯出')
        ->and($row->file_path)->toBe('reports/legacy.xlsx')
        ->and($row->count)->toBe(12)
        ->and($row->file_disk)->toBeNull()
        ->and($row->heartbeat_at)->toBeNull();
});

it('is safe to run twice', function (): void {
    createLegacyReportsTable();

    runPackageMigration();
    runPackageMigration();

    expect(Schema::hasColumn('reports', 'heartbeat_at'))->toBeTrue();
});

it('adds nothing when the table is already complete', function (): void {
    $before = Schema::getColumnListing('reports');

    runPackageMigration();

    expect(Schema::getColumnListing('reports'))->toBe($before);
});

it('honours a custom table name', function (): void {
    config()->set('report-queue.table', 'export_jobs');

    Schema::dropIfExists('export_jobs');

    runPackageMigration();

    expect(Schema::hasTable('export_jobs'))->toBeTrue()
        ->and(Schema::hasColumn('export_jobs', 'heartbeat_at'))->toBeTrue();
});

it('creates a plain indexed column when no user table is configured', function (): void {
    config()->set('report-queue.user.table', null);

    Schema::dropIfExists('reports');
    runPackageMigration();

    // No constraint, so an orphan owner id is accepted.
    DB::table('reports')->insert([
        'user_id' => 999999,
        'type' => 'Orphan export',
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('reports')->where('user_id', 999999)->exists())->toBeTrue();
});

it('constrains the owner column when a user table is configured', function (): void {
    config()->set('report-queue.user.table', 'users');

    Schema::dropIfExists('reports');
    runPackageMigration();

    DB::table('reports')->insert([
        'user_id' => 999999,
        'type' => 'Orphan export',
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('supports a singular user table, as one host uses', function (): void {
    Schema::create('user', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('report-queue.user.table', 'user');

    Schema::dropIfExists('reports');
    runPackageMigration();

    $userId = DB::table('user')->insertGetId(['name' => 'Owner', 'created_at' => now(), 'updated_at' => now()]);

    DB::table('reports')->insert([
        'user_id' => $userId,
        'type' => 'Widget export',
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('reports')->where('user_id', $userId)->exists())->toBeTrue();
});
