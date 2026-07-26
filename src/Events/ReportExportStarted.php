<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Events;

use Lalalili\ReportQueue\Models\Report;

final class ReportExportStarted
{
    public function __construct(public readonly Report $report) {}
}
