<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Illuminate\Container\Container;

/**
 * Resolves the package's extension points.
 *
 * Every seam follows the same order: an explicit container binding wins, then
 * the config value, then nothing (callers fall back to the bundled default).
 * Config values must be array callables — `[Class::class, 'method']` — because
 * closures cannot survive `config:cache`. Container bindings have no such
 * restriction, so hosts that need a closure should bind one.
 */
final class Seam
{
    /**
     * @param  string  $key  Config key relative to `report-queue.`, e.g. `storage.path_resolver`.
     */
    public static function callable(string $key): ?callable
    {
        $container = Container::getInstance();
        $abstract = 'report-queue.'.$key;

        if ($container->bound($abstract)) {
            $bound = $container->make($abstract);

            if (is_callable($bound)) {
                return $bound;
            }
        }

        $configured = config('report-queue.'.$key);

        return is_callable($configured) ? $configured : null;
    }

    /**
     * Invoke a seam, returning $default when the host has not wired one up.
     */
    public static function call(string $key, mixed $default, mixed ...$arguments): mixed
    {
        $callable = self::callable($key);

        return $callable === null ? $default : $callable(...$arguments);
    }
}
