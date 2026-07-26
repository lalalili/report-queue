<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Lalalili\ReportQueue\Enums\ReportStatusEnum;
use Lalalili\ReportQueue\Exceptions\UnknownReportTypeException;
use Lalalili\ReportQueue\Jobs\RunReportExportJob;
use Lalalili\ReportQueue\Support\ClosureExportHandler;
use Lalalili\ReportQueue\Support\ReportExportContext;
use Lalalili\ReportQueue\Tests\Fixtures\RecordingExportHandler;

it('resolves a class handler out of the container', function (): void {
    $this->registry()->register('Widget export', RecordingExportHandler::class);

    expect($this->registry()->resolve('Widget export'))->toBeInstanceOf(RecordingExportHandler::class);
});

it('wraps a closure handler', function (): void {
    $this->registry()->register('Widget export', fn (ReportExportContext $context): string => $context->path);

    expect($this->registry()->resolve('Widget export'))->toBeInstanceOf(ClosureExportHandler::class);
});

it('falls back to the raw type when no label is registered', function (): void {
    $this->registry()->register('商品匯出', RecordingExportHandler::class);

    expect($this->registry()->label('商品匯出'))->toBe('商品匯出');
});

it('decouples the display label from the registry key when one is given', function (): void {
    $this->registry()->register('product.export', RecordingExportHandler::class, '商品匯出');

    expect($this->registry()->label('product.export'))->toBe('商品匯出')
        ->and($this->registry()->options())->toBe(['product.export' => '商品匯出']);
});

it('throws for an unregistered type', function (): void {
    $this->registry()->resolve('Nope');
})->throws(UnknownReportTypeException::class);

it('rejects a class handler that does not implement the contract', function (): void {
    $this->registry()->register('Widget export', stdClass::class);
})->throws(RuntimeException::class, 'must implement');

it('reports which types are registered', function (): void {
    $this->registry()->register('A', RecordingExportHandler::class);
    $this->registry()->register('B', RecordingExportHandler::class);

    expect($this->registry()->types())->toBe(['A', 'B'])
        ->and($this->registry()->has('A'))->toBeTrue()
        ->and($this->registry()->has('C'))->toBeFalse();
});

it('keeps localized type strings working, so existing rows still resolve', function (): void {
    $this->registry()->register('商品匯出', RecordingExportHandler::class);

    $report = $this->report(['type' => '商品匯出', 'params' => ['filename' => 'products.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();

    expect($report->refresh()->status)->toBe(ReportStatusEnum::FINISHED)
        ->and(RecordingExportHandler::$calls)->toHaveCount(1);
});

it('lets a closure handler override the stored path', function (): void {
    $this->registry()->register('Widget export', function (ReportExportContext $context): string {
        Storage::disk($context->disk)->put('reports/elsewhere.xlsx', 'generated');

        return 'reports/elsewhere.xlsx';
    });

    $report = $this->report(['params' => ['filename' => 'ignored.xlsx']]);

    (new RunReportExportJob($report->getKey()))->handle();

    expect($report->refresh()->file_path)->toBe('reports/elsewhere.xlsx');
});
