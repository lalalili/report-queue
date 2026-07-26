<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Lalalili\ReportQueue\Models\Report;
use Lalalili\ReportQueue\Policies\ReportPolicy;
use Lalalili\ReportQueue\Support\ReportAccess;
use Lalalili\ReportQueue\Tests\Fixtures\HostSeams;

it('has no opinion on viewAny until the host wires the seam', function (): void {
    expect(app(ReportAccess::class)->canViewAny($this->user()))->toBeNull();
});

it('answers viewAny from the config seam', function (): void {
    config()->set('report-queue.authorization.view_any', [HostSeams::class, 'viewAny']);

    HostSeams::$viewAnyAnswer = true;
    expect(app(ReportAccess::class)->canViewAny($this->user()))->toBeTrue();

    HostSeams::$viewAnyAnswer = false;
    expect(app(ReportAccess::class)->canViewAny($this->user()))->toBeFalse();
});

it('lets a container binding win over the config seam', function (): void {
    config()->set('report-queue.authorization.view_any', [HostSeams::class, 'viewAny']);
    HostSeams::$viewAnyAnswer = false;

    app()->bind('report-queue.authorization.view_any', fn (): callable => fn (): bool => true);

    expect(app(ReportAccess::class)->canViewAny($this->user()))->toBeTrue();
});

it('registers the bundled policy by default', function (): void {
    expect(Gate::getPolicyFor(Report::class))->toBeInstanceOf(ReportPolicy::class);
});

it('defers to shield-style permission names', function (): void {
    $user = $this->user();

    Gate::define('ViewAny:Report', fn (): bool => true);
    Gate::define('Delete:Report', fn (): bool => false);

    $policy = new ReportPolicy;

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->delete($user))->toBeFalse();
});
