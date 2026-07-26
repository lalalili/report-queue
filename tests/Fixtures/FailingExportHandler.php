<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Tests\Fixtures;

use Lalalili\ReportQueue\Contracts\ReportExportHandler;
use Lalalili\ReportQueue\Support\ReportExportContext;
use RuntimeException;

class FailingExportHandler implements ReportExportHandler
{
    public const MESSAGE = 'The spreadsheet engine gave up.';

    public function handle(ReportExportContext $context): string
    {
        throw new RuntimeException(self::MESSAGE);
    }
}
