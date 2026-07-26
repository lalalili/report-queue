<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Lalalili\ReportQueue\Support\ReportResourceLocator;

/**
 * Registers the download centre into a host panel.
 *
 * Hosts opt in explicitly — the package never injects itself into a panel — and
 * `report-queue.filament.panel_ids` decides which panels get the resource, so a
 * multi-panel app can expose it in one place only.
 */
class ReportQueuePlugin implements Plugin
{
    public static function make(): static
    {
        /** @var static $plugin */
        $plugin = app(static::class);

        return $plugin;
    }

    public function getId(): string
    {
        return 'report-queue';
    }

    public function register(Panel $panel): void
    {
        $panelIds = ReportResourceLocator::panelIds();

        if ($panelIds !== [] && ! in_array($panel->getId(), $panelIds, true)) {
            return;
        }

        $panel->resources([ReportResourceLocator::resourceClass()]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
