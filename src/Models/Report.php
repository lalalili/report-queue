<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Support\Config;
use Lalalili\ReportQueue\Support\HostUser;
use RuntimeException;

/**
 * A single asynchronous export task.
 *
 * `type` is both the human-readable label shown in the table and the key the
 * export registry dispatches on. That double duty is inherited from the host
 * apps this package was extracted from, where the column already holds
 * localized strings in production — it must not be repurposed.
 *
 * @property int $id
 * @property int|string|null $user_id
 * @property string $type
 * @property array<string, mixed>|null $params
 * @property ReportStatusEnum $status
 * @property int $progress
 * @property int $count
 * @property string|null $file_path
 * @property string|null $file_disk
 * @property string|null $error
 * @property Carbon|null $heartbeat_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $user
 */
class Report extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'params',
        'status',
        'progress',
        'count',
        'file_path',
        'file_disk',
        'error',
        'heartbeat_at',
        'started_at',
        'finished_at',
    ];

    public function getTable(): string
    {
        return Config::string('table', 'reports');
    }

    /**
     * The primary key, narrowed for the queue and for route generation.
     */
    public function reportKey(): int|string
    {
        $key = $this->getKey();

        if (is_int($key) || is_string($key)) {
            return $key;
        }

        throw new RuntimeException('Report has no usable primary key.');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'status' => ReportStatusEnum::class,
            'progress' => 'integer',
            'count' => 'integer',
            'heartbeat_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(HostUser::modelClass(), HostUser::foreignKey());
    }

    public function isDownloadable(): bool
    {
        return $this->status === ReportStatusEnum::FINISHED
            && filled($this->file_path)
            && $this->finished_at?->addHours(self::downloadTtlHours())->gte(now()) === true;
    }

    /**
     * A pending/running report whose worker has gone quiet for longer than the
     * configured window. Used to warn the user instead of leaving the row
     * spinning forever.
     */
    public function isStalled(): bool
    {
        if (! in_array($this->status, ReportStatusEnum::active(), true)) {
            return false;
        }

        if (! Config::bool('heartbeat.enabled', true)) {
            return false;
        }

        $lastSignal = $this->heartbeat_at ?? $this->started_at;

        if ($lastSignal === null) {
            return false;
        }

        return $lastSignal->addMinutes(Config::int('heartbeat.stalled_after_minutes', 10))->isPast();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ReportStatusEnum::terminal(), true);
    }

    /**
     * Filename offered to the browser, which may differ from the stored path
     * (stored paths stay ASCII-safe, download names may be localized).
     */
    public function downloadName(): string
    {
        $params = $this->params ?? [];

        foreach (['download_name', 'filename'] as $key) {
            $candidate = $params[$key] ?? null;

            if (is_string($candidate) && $candidate !== '') {
                return self::stripDirectories($candidate);
            }
        }

        return self::stripDirectories((string) $this->file_path);
    }

    /**
     * Deliberately not `basename()`: that is locale-dependent and mangles
     * multibyte filenames, and download names are routinely localized.
     */
    private static function stripDirectories(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $position = strrpos($normalized, '/');

        return $position === false ? $normalized : substr($normalized, $position + 1);
    }

    public function downloadUrl(): ?string
    {
        $name = Config::nullableString('routes.download_name');

        if ($name === null || ! Route::has($name)) {
            return null;
        }

        return route($name, $this);
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return data_get($this->params, $key, $default);
    }

    public static function downloadTtlHours(): int
    {
        return Config::int('retention.download_ttl_hours', 24);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOwnedBy(Builder $query, int|string|null $userId): Builder
    {
        return $query->where(HostUser::foreignKey(), $userId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ReportStatusEnum::active());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeTerminal(Builder $query): Builder
    {
        return $query->whereIn('status', ReportStatusEnum::terminal());
    }
}
