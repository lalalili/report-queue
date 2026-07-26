<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Lalalili\ReportQueue\Support\ReportAccess;
use Lalalili\ReportQueue\Tests\Fixtures\HostSeams;

it('treats nobody as a super admin by default', function (): void {
    $user = $this->user(['is_super_admin' => true]);

    expect(app(ReportAccess::class)->isSuperAdmin($user))->toBeFalse();
});

it('reads the config seam', function (): void {
    config()->set('report-queue.super_admin', [HostSeams::class, 'isSuperAdmin']);

    expect(app(ReportAccess::class)->isSuperAdmin($this->user(['is_super_admin' => true])))->toBeTrue()
        ->and(app(ReportAccess::class)->isSuperAdmin($this->user(['is_super_admin' => false])))->toBeFalse();
});

it('lets a container binding win over config', function (): void {
    config()->set('report-queue.super_admin', [HostSeams::class, 'denyEveryone']);
    app()->bind('report-queue.super_admin', fn (): callable => fn (Authenticatable $user): bool => true);

    expect(app(ReportAccess::class)->isSuperAdmin($this->user()))->toBeTrue();
});

it('never treats a guest as a super admin', function (): void {
    config()->set('report-queue.super_admin', [HostSeams::class, 'isSuperAdmin']);

    expect(app(ReportAccess::class)->isSuperAdmin(null))->toBeFalse();
});

it('grants report access to the owner and to super admins only', function (): void {
    config()->set('report-queue.super_admin', [HostSeams::class, 'isSuperAdmin']);

    $owner = $this->user();
    $stranger = $this->user();
    $superAdmin = $this->user(['is_super_admin' => true]);

    $report = $this->report(['user_id' => $owner->getKey()]);
    $access = app(ReportAccess::class);

    expect($access->canAccess($report, $owner))->toBeTrue()
        ->and($access->canAccess($report, $stranger))->toBeFalse()
        ->and($access->canAccess($report, $superAdmin))->toBeTrue()
        ->and($access->canAccess($report, null))->toBeFalse();
});

it('does not let an ownerless report be claimed by any signed-in user', function (): void {
    $report = $this->report(['user_id' => null]);

    expect(app(ReportAccess::class)->canAccess($report, $this->user()))->toBeFalse();
});
