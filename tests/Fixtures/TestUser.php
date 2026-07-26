<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Stands in for the host user model. `is_super_admin` mirrors the shape one of
 * the adopting hosts uses, so the super-admin seam has something realistic to
 * read.
 *
 * @property bool $is_super_admin
 * @property string $name
 */
class TestUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_super_admin' => 'boolean'];
    }

    public static function isSuperAdmin(self $user): bool
    {
        return $user->is_super_admin;
    }
}
