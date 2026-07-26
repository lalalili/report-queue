<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * Bridges the package to whichever user model and table the host app owns.
 *
 * Hosts differ here more than anywhere else in this package: the table may be
 * singular or plural, and the display column may not be `name`.
 */
final class HostUser
{
    /**
     * @return class-string<Model>
     */
    public static function modelClass(): string
    {
        $configured = Config::nullableString('user.model');

        if ($configured !== null) {
            if (! is_subclass_of($configured, Model::class)) {
                throw new RuntimeException("report-queue.user.model must be an Eloquent model, got [{$configured}].");
            }

            return $configured;
        }

        $provider = config('auth.providers.users.model');

        if (is_string($provider) && is_subclass_of($provider, Model::class)) {
            return $provider;
        }

        throw new RuntimeException('Unable to resolve the host user model. Set report-queue.user.model.');
    }

    public static function find(int|string|null $id): ?Model
    {
        if ($id === null || $id === '') {
            return null;
        }

        $model = self::modelClass();

        return $model::query()->find($id);
    }

    public static function foreignKey(): string
    {
        return Config::string('user.foreign_key', 'user_id');
    }

    public static function nameColumn(): string
    {
        return Config::string('user.name_column', 'name');
    }

    /**
     * The acting user, preferring the Filament panel guard so exports created
     * inside a panel are attributed to the panel's user rather than the web
     * default. Falls back to the default guard outside a panel context
     * (console, queue worker, plain HTTP).
     */
    public static function current(): ?Authenticatable
    {
        try {
            $user = Filament::auth()->user();

            if ($user !== null) {
                return $user;
            }
        } catch (Throwable) {
            // No panel is bound in this context; fall through to the default guard.
        }

        return auth()->user();
    }

    public static function currentId(): int|string|null
    {
        return self::identifier(self::current());
    }

    public static function identifier(?Authenticatable $user): int|string|null
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || is_string($id) ? $id : null;
    }
}
