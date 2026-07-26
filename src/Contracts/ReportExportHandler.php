<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Contracts;

use Lalalili\ReportQueue\Support\ReportExportContext;

interface ReportExportHandler
{
    /**
     * Produce the export file and return its path relative to the context disk.
     *
     * Domain side effects that must share the export's success or failure —
     * advancing an order's status once its ERP file exists, for instance —
     * belong here, inside the host's own handler, so a failure still marks the
     * report FAILED. Do not move them into a completion listener.
     */
    public function handle(ReportExportContext $context): string;
}
