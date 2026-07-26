<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Closure;
use Illuminate\Container\Container;
use Lalalili\ReportQueue\Contracts\ReportExportHandler;
use Lalalili\ReportQueue\Exceptions\UnknownReportTypeException;
use RuntimeException;

/**
 * Maps a report `type` to the handler that produces its file.
 *
 * The key is whatever the host already stores in the `type` column — including
 * localized strings — so existing rows keep resolving after adoption. `$label`
 * exists to decouple display text from that key later on; leaving it null keeps
 * today's behaviour of showing the raw type.
 */
final class ReportExportRegistry
{
    /** @var array<string, class-string|Closure> */
    private array $handlers = [];

    /** @var array<string, string> */
    private array $labels = [];

    /**
     * @param  class-string|Closure(ReportExportContext): (string|null)  $handler
     */
    public function register(string $type, string|Closure $handler, ?string $label = null): void
    {
        if (is_string($handler) && ! is_a($handler, ReportExportHandler::class, true)) {
            throw new RuntimeException(
                "Handler [{$handler}] for report type [{$type}] must implement ".ReportExportHandler::class.'.'
            );
        }

        $this->handlers[$type] = $handler;

        if ($label !== null) {
            $this->labels[$type] = $label;
        }
    }

    /**
     * Register a handler backed by a maatwebsite/excel export object.
     *
     * @param  Closure(ReportExportContext): object  $exportFactory
     */
    public function registerExcel(string $type, Closure $exportFactory, ?string $label = null): void
    {
        if (! class_exists(ExcelExportHandler::excelFacade())) {
            throw new RuntimeException(
                "Report type [{$type}] uses registerExcel() but maatwebsite/excel is not installed."
            );
        }

        $this->register($type, fn (ReportExportContext $context): string => (new ExcelExportHandler($exportFactory))->handle($context), $label);
    }

    public function resolve(string $type): ReportExportHandler
    {
        if (! isset($this->handlers[$type])) {
            throw UnknownReportTypeException::for($type);
        }

        $handler = $this->handlers[$type];

        if ($handler instanceof Closure) {
            return new ClosureExportHandler($handler);
        }

        $resolved = Container::getInstance()->make($handler);

        if (! $resolved instanceof ReportExportHandler) {
            throw new RuntimeException(
                "Handler [{$handler}] for report type [{$type}] must implement ".ReportExportHandler::class.'.'
            );
        }

        return $resolved;
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    public function label(string $type): string
    {
        return $this->labels[$type] ?? $type;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Suitable for a Filament SelectFilter.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->types() as $type) {
            $options[$type] = $this->label($type);
        }

        return $options;
    }

    public function forget(string $type): void
    {
        unset($this->handlers[$type], $this->labels[$type]);
    }

    public function flush(): void
    {
        $this->handlers = [];
        $this->labels = [];
    }
}
