<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Illuminate\Database\Eloquent\Builder;
use Lalalili\ReportQueue\Models\Report;
use RuntimeException;

/**
 * Resolves the report model, which hosts may subclass to add domain accessors
 * (an order number, a reporting period) without forking the package.
 */
final class ReportModel
{
    /**
     * @return class-string<Report>
     */
    public static function modelClass(): string
    {
        $configured = config('report-queue.model', Report::class);

        if (! is_string($configured) || $configured === '') {
            return Report::class;
        }

        if ($configured !== Report::class && ! is_subclass_of($configured, Report::class)) {
            throw new RuntimeException('report-queue.model must extend '.Report::class.", got [{$configured}].");
        }

        /** @var class-string<Report> $configured */
        return $configured;
    }

    /**
     * @return Builder<Report>
     */
    public static function query(): Builder
    {
        $model = self::modelClass();

        return $model::query();
    }

    public static function find(int|string $id): ?Report
    {
        return self::query()->find($id);
    }

    public static function findOrFail(int|string $id): Report
    {
        return self::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): Report
    {
        $model = self::modelClass();

        return $model::query()->create($attributes);
    }
}
