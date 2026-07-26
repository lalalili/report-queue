<?php

declare(strict_types=1);
use Lalalili\ReportQueue\Models\Report;

return [

    /*
    |--------------------------------------------------------------------------
    | Report table
    |--------------------------------------------------------------------------
    |
    | Physical table backing the export queue. Hosts that already own a
    | `reports` table keep it — the package migration only adds missing
    | columns, it never rewrites an existing table.
    |
    */

    'table' => 'reports',

    'model' => Report::class,

    /*
    |--------------------------------------------------------------------------
    | Host user mapping
    |--------------------------------------------------------------------------
    |
    | `table` is only consulted by the migration: when set, `user_id` becomes a
    | real foreign key constrained to that table; when null the column is
    | created as a plain indexed column with no constraint.
    |
    | Note some hosts use a singular `user` table.
    |
    */

    'user' => [
        'model' => null,
        'table' => null,
        'foreign_key' => 'user_id',
        'name_column' => 'name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Super admin seam
    |--------------------------------------------------------------------------
    |
    | Decides who may see and download other people's reports. Resolution
    | order: container binding `report-queue.super_admin` > this config value >
    | false (everyone sees only their own reports).
    |
    | Must be an array callable — `[Class::class, 'method']` — so the value
    | survives `config:cache`. Signature:
    |
    |     fn (Illuminate\Contracts\Auth\Authenticatable $user): bool
    |
    */

    'super_admin' => null,

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | `register_policy` registers the bundled shield-flavoured ReportPolicy
    | (`can('ViewAny:Report')`). `view_any` is an array callable that takes
    | precedence over the policy for hosts that authorize through explicit
    | permission checks instead. Signature:
    |
    |     fn (?Illuminate\Contracts\Auth\Authenticatable $user): bool
    |
    */

    'authorization' => [
        'register_policy' => true,
        'view_any' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | `disk` falls back to the default filesystem disk when null.
    |
    | `path_resolver` — fn (Report $report, string $filename): string
    | `filename_resolver` — fn (string $prefix, string $extension): string
    |
    | Both are array callables. The defaults produce
    | `reports/{prefix}{YmdHis}_{random6}.{ext}`.
    |
    */

    'storage' => [
        // Null uses the default filesystem disk. Hosts usually point this at
        // their own config value, e.g. config('project.report_disk').
        'disk' => null,
        'path_prefix' => 'reports',
        'path_resolver' => null,
        'filename_resolver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | `download_name` is a backward-compatibility contract: every host already
    | has `route()` calls against its own name. Never hardcode a route name in
    | package code — always resolve it from here.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'admin',
        'download_name' => 'report-queue.download',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament presentation
    |--------------------------------------------------------------------------
    |
    | `resource_class` lets a host subclass the bundled resource. Prefer it
    | over the shallow column seams for anything non-trivial.
    |
    | `extra_columns` — fn (): array<Filament\Tables\Columns\Column>
    | `hidden_columns` — list of bundled column names to drop.
    |
    */

    'filament' => [
        'panel_ids' => ['admin'],
        'resource_class' => null,
        'navigation_group' => null,
        'navigation_label' => null,
        'navigation_icon' => 'heroicon-o-document-arrow-down',
        'navigation_sort' => null,
        'heading' => null,
        'poll_interval' => '5s',
        'hidden_columns' => [],
        'extra_columns' => null,
        'filters_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | Guards against duplicate exports when a user double-submits.
    |
    |   type_per_user — reject when the same user already has a pending or
    |                   running report of the same type.
    |   request_key   — reject when an identical request (type + params, minus
    |                   volatile keys) is already pending or running.
    |   none          — no guard.
    |
    */

    'idempotency' => [
        'enabled' => true,
        'strategy' => 'type_per_user',
        'lock_for_update' => true,
        'volatile_params' => ['filename', 'request_key', 'updated_by'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Heartbeat / stall detection
    |--------------------------------------------------------------------------
    |
    | Long exports write `heartbeat_at` so a crashed worker can be told apart
    | from a slow one. Writes are throttled to at most one per
    | `interval_seconds`, or whenever progress advances by `progress_step`.
    |
    */

    'heartbeat' => [
        'enabled' => true,
        'stalled_after_minutes' => 10,
        'interval_seconds' => 60,
        'progress_step' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Drives the three phases of `report-queue:prune`:
    |   1. finished reports older than `download_ttl_hours` lose their file and
    |      become EXPIRED,
    |   2. pending/running reports untouched for `stuck_after_hours` become
    |      FAILED,
    |   3. terminal reports older than `purge_after_days` are deleted outright.
    |
    */

    'retention' => [
        'download_ttl_hours' => 24,
        'stuck_after_hours' => 6,
        'purge_after_days' => 7,
        'delete_files_on_purge' => true,
        'write_finished_at_on_timeout' => true,
        'chunk_size' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Null connection/queue means "use the application default".
    |
    */

    'queue' => [
        'connection' => 'redis-long-running',
        'queue' => 'long-running-queue',
        'tries' => 3,
        'timeout' => 1800,
        'backoff' => [60, 300],

        // A retry whose file already exists skips regeneration. Turn this off
        // only for exports whose contents must reflect the retry's moment.
        'skip_if_complete' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | `on_queued` and `on_duplicate` are flashed to the current session;
    | `on_finished` and `on_failed` are persisted as Filament database
    | notifications. `log_channel` additionally logs failures to a named log
    | channel when set.
    |
    */

    'notifications' => [
        'on_queued' => true,
        'on_duplicate' => true,
        'on_finished' => true,
        'on_failed' => true,
        'log_channel' => null,
    ],

];
