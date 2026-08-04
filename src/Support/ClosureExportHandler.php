<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Closure;
use Lalalili\ReportQueue\Contracts\ReportExportHandler;

/**
 * Adapts an inline closure registration to the handler contract. Returning a
 * path from the closure is optional; the context path is used otherwise.
 */
final class ClosureExportHandler implements ReportExportHandler
{
    /**
     * @param  Closure(ReportExportContext): (string|null)  $callback
     */
    public function __construct(private readonly Closure $callback)
    {
    }

    public function handle(ReportExportContext $context): string
    {
        $path = ($this->callback)($context);

        return is_string($path) && $path !== '' ? $path : $context->path;
    }
}
