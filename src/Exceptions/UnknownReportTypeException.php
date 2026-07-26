<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Exceptions;

use RuntimeException;

final class UnknownReportTypeException extends RuntimeException
{
    public static function for(string $type): self
    {
        return new self((string) trans('report-queue::messages.unknown_type', ['type' => $type]));
    }
}
