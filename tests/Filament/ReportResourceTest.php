<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Filament\Resources\Reports\ReportResource;
use Lalalili\ReportQueue\Support\ReportExportRegistry;
use Lalalili\ReportQueue\Support\ReportModel;
use Lalalili\ReportQueue\Tests\Fixtures\ExposedReportResource;
use Lalalili\ReportQueue\Tests\Fixtures\HostSeams;
use Lalalili\ReportQueue\Tests\Fixtures\RecordingExportHandler;

/**
 * Table composition and scoping are asserted through the resource's own
 * helpers. Rendering the Livewire page is exercised in the host application's
 * suite instead: Livewire 4.3 cannot render any component under Testbench
 * (a plain component fails the same way), so a render-level test here would be
 * testing the harness, not the package.
 */
beforeEach(function (): void {
    config()->set('report-queue.super_admin', [HostSeams::class, 'isSuperAdmin']);
});

it('registers the resource into the configured panel', function (): void {
    expect(Filament::getPanel('admin')->getResources())->toContain(ReportResource::class);
});

it('is not registered into a panel outside panel_ids', function (): void {
    expect(config('report-queue.filament.panel_ids'))->toBe(['admin']);
});

it('offers no create page, because reports come from export actions elsewhere', function (): void {
    expect(ReportResource::canCreate())->toBeFalse()
        ->and(array_keys(ReportResource::getPages()))->toBe(['index']);
});

it('takes its navigation label and group from config', function (): void {
    config()->set('report-queue.filament.navigation_label', '匯出下載');
    config()->set('report-queue.filament.navigation_group', '系統');
    config()->set('report-queue.filament.navigation_sort', 90);

    expect(ReportResource::getNavigationLabel())->toBe('匯出下載')
        ->and(ReportResource::getModelLabel())->toBe('匯出下載')
        ->and(ReportResource::getNavigationGroup())->toBe('系統')
        ->and(ReportResource::getNavigationSort())->toBe(90);
});

it('falls back to the translated label', function (): void {
    app()->setLocale('zh_TW');

    expect(ReportResource::getNavigationLabel())->toBe('我的報表')
        ->and(ExposedReportResource::exposedHeading())->toContain('24 小時');
});

it('reflects a custom download window in the heading', function (): void {
    app()->setLocale('zh_TW');
    config()->set('report-queue.retention.download_ttl_hours', 72);

    expect(ExposedReportResource::exposedHeading())->toContain('72 小時');
});

it('lets a host override the heading outright', function (): void {
    config()->set('report-queue.filament.heading', 'Downloads expire quickly.');

    expect(ExposedReportResource::exposedHeading())->toBe('Downloads expire quickly.');
});

it('polls on the configured interval', function (): void {
    expect(ExposedReportResource::exposedPollInterval())->toBe('5s');

    config()->set('report-queue.filament.poll_interval', null);

    expect(ExposedReportResource::exposedPollInterval())->toBeNull();
});

it('resolves the model through config so a host may subclass it', function (): void {
    expect(ReportResource::getModel())->toBe(ReportModel::modelClass());
});

describe('column composition', function (): void {
    it('ships the expected columns', function (): void {
        $names = array_map(fn ($column): string => $column->getName(), ExposedReportResource::exposedColumns());

        expect($names)->toBe([
            'type', 'count', 'status', 'progress', 'user.name', 'created_at', 'finished_at', 'error',
        ]);
    });

    it('drops columns a host hides', function (): void {
        config()->set('report-queue.filament.hidden_columns', ['progress', 'count', 'error']);

        $names = array_map(fn ($column): string => $column->getName(), ExposedReportResource::exposedColumns());

        expect($names)->toBe(['type', 'status', 'user.name', 'created_at', 'finished_at']);
    });

    it('appends host-supplied columns', function (): void {
        config()->set('report-queue.filament.extra_columns', [HostSeams::class, 'extraColumns']);

        $names = array_map(fn ($column): string => $column->getName(), ExposedReportResource::exposedColumns());

        expect($names)->toContain('order_number')
            ->and(array_slice($names, -1))->toBe(['order_number']);
    });

    it('ignores an extra_columns seam that returns rubbish', function (): void {
        app()->bind('report-queue.filament.extra_columns', fn (): callable => fn (): array => ['not a column']);

        $names = array_map(fn ($column): string => $column->getName(), ExposedReportResource::exposedColumns());

        expect($names)->not->toContain('not a column');
    });

    it('names the creator column after the host user column', function (): void {
        config()->set('report-queue.user.name_column', 'full_name');

        $names = array_map(fn ($column): string => $column->getName(), ExposedReportResource::exposedColumns());

        expect($names)->toContain('user.full_name');
    });
});

describe('filter composition', function (): void {
    it('offers a status filter', function (): void {
        $names = array_map(fn ($filter): string => $filter->getName(), ExposedReportResource::exposedFilters());

        expect($names)->toBe(['status']);
    });

    it('adds a type filter once export types are registered', function (): void {
        app(ReportExportRegistry::class)->register('商品匯出', RecordingExportHandler::class);

        $filters = ExposedReportResource::exposedFilters();
        $names = array_map(fn ($filter): string => $filter->getName(), $filters);

        expect($names)->toBe(['status', 'type']);
    });

    it('drops every filter when the host turns them off', function (): void {
        config()->set('report-queue.filament.filters_enabled', false);

        expect(ExposedReportResource::exposedFilters())->toBe([]);
    });
});

describe('viewer scoping', function (): void {
    it('limits an ordinary user to their own reports', function (): void {
        $owner = $this->user();
        $mine = $this->report(['user_id' => $owner->getKey()]);
        $this->report(['user_id' => $this->user()->getKey()]);

        $this->actingAs($owner);

        $query = ReportModel::query();
        (ExposedReportResource::exposedScope())($query);

        expect($query->pluck('id')->all())->toBe([$mine->getKey()]);
    });

    it('shows every report to a super admin', function (): void {
        $mine = $this->report(['user_id' => $this->user()->getKey()]);
        $theirs = $this->report(['user_id' => $this->user()->getKey()]);

        $this->actingAs($this->user(['is_super_admin' => true]));

        $query = ReportModel::query();
        (ExposedReportResource::exposedScope())($query);

        expect($query->pluck('id')->all())->toBe([$mine->getKey(), $theirs->getKey()]);
    });

    it('shows a guest nothing', function (): void {
        $this->report(['user_id' => $this->user()->getKey()]);

        $query = ReportModel::query();
        (ExposedReportResource::exposedScope())($query);

        expect($query->count())->toBe(0);
    });
});

it('renders the status enum as a coloured badge', function (): void {
    expect(ReportStatusEnum::FINISHED->getColor())->toBe('success')
        ->and(ReportStatusEnum::FAILED->getColor())->toBe('danger')
        ->and(ReportStatusEnum::EXPIRED->getColor())->toBe('gray')
        ->and(ReportStatusEnum::PENDING->getColor())->toBe('warning')
        ->and(ReportStatusEnum::RUNNING->getColor())->toBe('warning');
});
