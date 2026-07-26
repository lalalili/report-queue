<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Shield-flavoured permission names, matching the convention the host apps
 * already generate (`ViewAny:Report`, `Delete:Report`, …).
 *
 * Reports are only ever created by an export action, never by hand, so there is
 * deliberately no `create` ability.
 */
class ReportPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return (bool) $user->can('ViewAny:Report');
    }

    public function view(Authorizable $user): bool
    {
        return (bool) $user->can('View:Report');
    }

    public function delete(Authorizable $user): bool
    {
        return (bool) $user->can('Delete:Report');
    }

    public function deleteAny(Authorizable $user): bool
    {
        return (bool) $user->can('DeleteAny:Report');
    }
}
