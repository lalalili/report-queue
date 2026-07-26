<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Closure;
use Lalalili\ReportQueue\Contracts\ReportExportHandler;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Bridges maatwebsite/excel export objects to the handler contract, so hosts
 * adopting this package can keep their existing `app/Exports` classes untouched.
 *
 * maatwebsite/excel is an optional dependency — the class is only touched when
 * a host actually registers an Excel-backed type.
 */
final class ExcelExportHandler implements ReportExportHandler
{
    /**
     * @param  Closure(ReportExportContext): object  $exportFactory
     */
    public function __construct(private readonly Closure $exportFactory) {}

    /**
     * @return class-string
     */
    public static function excelFacade(): string
    {
        return Excel::class;
    }

    public function handle(ReportExportContext $context): string
    {
        if (! class_exists(Excel::class)) {
            throw new RuntimeException('maatwebsite/excel is required to run Excel-backed report exports.');
        }

        Excel::store(($this->exportFactory)($context), $context->path, $context->disk);

        return $context->path;
    }
}
