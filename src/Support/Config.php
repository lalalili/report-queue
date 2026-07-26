<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

/**
 * Typed reads of the package config.
 *
 * Config values are host-supplied and arrive as `mixed`; funnelling them
 * through here keeps the coercion in one place instead of scattering casts that
 * would silently accept the wrong shape.
 */
final class Config
{
    public static function int(string $key, int $default): int
    {
        $value = config('report-queue.'.$key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default): bool
    {
        $value = config('report-queue.'.$key, $default);

        return is_bool($value) ? $value : $default;
    }

    public static function string(string $key, string $default): string
    {
        $value = self::nullableString($key);

        return $value ?? $default;
    }

    /**
     * Like string(), but an explicit empty string is kept as a value rather than
     * treated as "unset". Needed where empty is meaningful — a route prefix of
     * '' mounts the route at the root, which is what most hosts already have.
     */
    public static function stringOrEmpty(string $key, string $default): string
    {
        $value = config('report-queue.'.$key);

        return is_string($value) ? $value : $default;
    }

    public static function nullableString(string $key): ?string
    {
        $value = config('report-queue.'.$key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    public static function stringList(string $key): array
    {
        $value = config('report-queue.'.$key);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        ));
    }

    /**
     * @param  list<int>  $default
     * @return list<int>
     */
    public static function intList(string $key, array $default): array
    {
        $value = config('report-queue.'.$key);

        if (! is_array($value) || $value === []) {
            return $default;
        }

        return array_values(array_map(
            static fn (mixed $item): int => is_numeric($item) ? (int) $item : 0,
            $value,
        ));
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function array(string $key): array
    {
        $value = config('report-queue.'.$key);

        return is_array($value) ? $value : [];
    }

    /**
     * @return class-string|null
     */
    public static function classString(string $key): ?string
    {
        $value = self::nullableString($key);

        return $value !== null && (class_exists($value) || interface_exists($value)) ? $value : null;
    }
}
