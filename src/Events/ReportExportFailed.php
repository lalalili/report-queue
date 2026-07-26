<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Events;

use Lalalili\ReportQueue\Models\Report;
use Throwable;

final class ReportExportFailed
{
    public function __construct(
        public readonly Report $report,
        public readonly Throwable $exception,
    ) {}
}
