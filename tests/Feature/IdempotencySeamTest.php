<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Lalalili\ReportQueue\Actions\QueueReportExport;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Jobs\RunReportExportJob;
use Lalalili\ReportQueue\Support\ReportModel;
use Lalalili\ReportQueue\Tests\Fixtures\RecordingExportHandler;

beforeEach(function (): void {
    $this->registry()->register('Widget export', RecordingExportHandler::class);
    $this->registry()->register('Gadget export', RecordingExportHandler::class);
    Bus::fake();
});

it('queues an export and dispatches the job', function (): void {
    $this->actingAs($user = $this->user());

    $report = QueueReportExport::dispatch('Widget export', 'widgets_', ['selected_ids' => [1, 2]], count: 2);

    expect($report)->not->toBeNull()
        ->and($report->status)->toBe(ReportStatusEnum::PENDING)
        ->and($report->count)->toBe(2)
        ->and($report->user_id)->toBe($user->getKey())
        ->and($report->param('selected_ids'))->toBe([1, 2])
        ->and($report->param('filename'))->toEndWith('.xlsx');

    Bus::assertDispatched(RunReportExportJob::class);
});

describe('type_per_user strategy', function (): void {
    beforeEach(fn () => config()->set('report-queue.idempotency.strategy', 'type_per_user'));

    it('rejects a second export of the same type by the same user', function (): void {
        $this->actingAs($this->user());

        QueueReportExport::dispatch('Widget export', 'widgets_');
        $second = QueueReportExport::dispatch('Widget export', 'widgets_');

        expect($second)->toBeNull()
            ->and(ReportModel::query()->count())->toBe(1);

        Bus::assertDispatchedTimes(RunReportExportJob::class, 1);
    });

    it('allows a different type for the same user', function (): void {
        $this->actingAs($this->user());

        QueueReportExport::dispatch('Widget export', 'widgets_');

        expect(QueueReportExport::dispatch('Gadget export', 'gadgets_'))->not->toBeNull()
            ->and(ReportModel::query()->count())->toBe(2);
    });

    it('allows the same type for a different user', function (): void {
        $this->actingAs($this->user());
        QueueReportExport::dispatch('Widget export', 'widgets_');

        $this->actingAs($this->user());

        expect(QueueReportExport::dispatch('Widget export', 'widgets_'))->not->toBeNull()
            ->and(ReportModel::query()->count())->toBe(2);
    });

    it('allows a retry once the earlier export reached a terminal state', function (): void {
        $this->actingAs($this->user());

        $first = QueueReportExport::dispatch('Widget export', 'widgets_');
        $first->update(['status' => ReportStatusEnum::FAILED]);

        expect(QueueReportExport::dispatch('Widget export', 'widgets_'))->not->toBeNull();
    });
});

describe('request_key strategy', function (): void {
    beforeEach(fn () => config()->set('report-queue.idempotency.strategy', 'request_key'));

    it('stores a fingerprint of the request', function (): void {
        $this->actingAs($this->user());

        $report = QueueReportExport::dispatch('Widget export', 'widgets_', ['order_id' => 42]);

        expect($report->param('request_key'))->toBeString()->toHaveLength(64);
    });

    it('rejects an identical request even from a different user', function (): void {
        $this->actingAs($this->user());
        QueueReportExport::dispatch('Widget export', 'widgets_', ['order_id' => 42]);

        $this->actingAs($this->user());
        $second = QueueReportExport::dispatch('Widget export', 'widgets_', ['order_id' => 42]);

        expect($second)->toBeNull()
            ->and(ReportModel::query()->count())->toBe(1);
    });

    it('allows a request that differs in a meaningful param', function (): void {
        $this->actingAs($this->user());

        QueueReportExport::dispatch('Widget export', 'widgets_', ['order_id' => 42]);

        expect(QueueReportExport::dispatch('Widget export', 'widgets_', ['order_id' => 43]))->not->toBeNull();
    });

    it('ignores volatile params when fingerprinting', function (): void {
        $this->actingAs($this->user());

        $first = QueueReportExport::dispatch('Widget export', 'widgets_', ['order_id' => 42, 'updated_by' => 'alice']);
        $first->update(['status' => ReportStatusEnum::FINISHED]);

        $second = QueueReportExport::dispatch('Widget export', 'widgets_', ['order_id' => 42, 'updated_by' => 'bob']);

        expect($second->param('request_key'))->toBe($first->param('request_key'));
    });

    it('is insensitive to param ordering', function (): void {
        $this->actingAs($this->user());

        $first = QueueReportExport::dispatch('Widget export', 'widgets_', ['a' => 1, 'b' => 2]);
        $first->update(['status' => ReportStatusEnum::FINISHED]);

        $second = QueueReportExport::dispatch('Widget export', 'widgets_', ['b' => 2, 'a' => 1]);

        expect($second->param('request_key'))->toBe($first->param('request_key'));
    });
});

it('places no guard at all under the none strategy', function (): void {
    config()->set('report-queue.idempotency.strategy', 'none');

    $this->actingAs($this->user());

    QueueReportExport::dispatch('Widget export', 'widgets_');
    QueueReportExport::dispatch('Widget export', 'widgets_');

    expect(ReportModel::query()->count())->toBe(2);
});

it('places no guard when idempotency is disabled', function (): void {
    config()->set('report-queue.idempotency.enabled', false);

    $this->actingAs($this->user());

    QueueReportExport::dispatch('Widget export', 'widgets_');
    QueueReportExport::dispatch('Widget export', 'widgets_');

    expect(ReportModel::query()->count())->toBe(2);
});
