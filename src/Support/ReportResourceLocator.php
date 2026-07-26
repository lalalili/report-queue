<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Filament\Resources\Resource;
use Lalalili\ReportQueue\Filament\Resources\Reports\ReportResource;
use RuntimeException;
use Throwable;

/**
 * Resolves the Filament resource backing the download centre, and links to it
 * from contexts that have no current panel (queue workers, console commands).
 */
final class ReportResourceLocator
{
    /**
     * @return class-string<\Filament\Resources\Resource>
     */
    public static function resourceClass(): string
    {
        $configured = config('report-queue.filament.resource_class');

        if (! is_string($configured) || $configured === '') {
            return ReportResource::class;
        }

        if (! is_subclass_of($configured, Resource::class)) {
            throw new RuntimeException(
                'report-queue.filament.resource_class must extend '.Resource::class.", got [{$configured}]."
            );
        }

        /** @var class-string<\Filament\Resources\Resource> $configured */
        return $configured;
    }

    /**
     * @return list<string>
     */
    public static function panelIds(): array
    {
        $configured = config('report-queue.filament.panel_ids', ['admin']);

        if (! is_array($configured)) {
            return ['admin'];
        }

        return array_values(array_filter($configured, static fn (mixed $id): bool => is_string($id) && $id !== ''));
    }

    /**
     * Null when no panel can be resolved — callers should omit the link rather
     * than fail the export.
     */
    public static function indexUrl(): ?string
    {
        $panelIds = self::panelIds();
        $resource = self::resourceClass();

        try {
            return $resource::getUrl('index', panel: $panelIds[0] ?? null);
        } catch (Throwable) {
            return null;
        }
    }
}
