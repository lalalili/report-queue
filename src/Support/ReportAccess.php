<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Lalalili\ReportQueue\Models\Report;

/**
 * The two authorization questions this package asks the host.
 *
 * Hosts disagree on how a privileged operator is identified (a boolean column,
 * a role, a method on the user model), so the answer is a seam rather than a
 * convention. The default is deliberately restrictive: with nothing wired up,
 * every user sees only their own reports.
 */
final class ReportAccess
{
    public function isSuperAdmin(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        return Seam::call('super_admin', false, $user) === true;
    }

    /**
     * Null means "no opinion" — the caller should fall back to the policy/gate.
     */
    public function canViewAny(?Authenticatable $user): ?bool
    {
        $callable = Seam::callable('authorization.view_any');

        if ($callable === null) {
            return null;
        }

        return $callable($user) === true;
    }

    public function canAccess(Report $report, ?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        $ownerId = $report->getAttribute(HostUser::foreignKey());
        $userId = HostUser::identifier($user);

        // Compared as strings because a host key may be an int column or a uuid.
        if ($userId !== null
            && (is_int($ownerId) || is_string($ownerId))
            && (string) $ownerId === (string) $userId) {
            return true;
        }

        return $this->isSuperAdmin($user);
    }
}
