<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Events;

use Lalalili\ReportQueue\Models\Report;

/**
 * Fired after the report row is marked FINISHED.
 *
 * Intended for observation — activity logs, extra notifications, cache busting.
 * Domain side effects that must fail the export belong inside the handler, not
 * in a listener here, because by this point the report is already FINISHED.
 */
final class ReportExportCompleted
{
    public function __construct(public readonly Report $report)
    {
    }
}
