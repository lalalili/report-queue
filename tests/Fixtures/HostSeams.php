<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Tests\Fixtures;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Auth\Authenticatable;
use Lalalili\ReportQueue\Models\Report;

/**
 * Array callables standing in for what a host wires into config — the shape
 * that survives `config:cache`.
 */
class HostSeams
{
    public static bool $viewAnyAnswer = true;

    public static function isSuperAdmin(Authenticatable $user): bool
    {
        return $user instanceof TestUser && $user->is_super_admin;
    }

    public static function denyEveryone(Authenticatable $user): bool
    {
        return false;
    }

    public static function viewAny(?Authenticatable $user): bool
    {
        return self::$viewAnyAnswer;
    }

    public static function monthlyPath(Report $report, string $filename): string
    {
        return 'reports/erp/'.$report->created_at?->format('Y/m').'/'.$report->getKey().'_'.$filename;
    }

    public static function fixedFilename(string $prefix, string $extension): string
    {
        return $prefix.'fixed.'.$extension;
    }

    /**
     * @return list<TextColumn>
     */
    public static function extraColumns(): array
    {
        return [
            TextColumn::make('order_number')
                ->label('Order')
                ->state(fn (Report $record): ?string => $record->param('order_number')),
        ];
    }
}
