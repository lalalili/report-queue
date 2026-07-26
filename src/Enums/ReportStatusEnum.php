<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReportStatusEnum: int implements HasColor, HasLabel
{
    case PENDING = 1;
    case RUNNING = 2;
    case FINISHED = 3;
    case FAILED = 4;
    case EXPIRED = 5;

    public function getLabel(): string
    {
        return (string) trans('report-queue::status.'.strtolower($this->name));
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING, self::RUNNING => 'warning',
            self::FINISHED => 'success',
            self::FAILED => 'danger',
            self::EXPIRED => 'gray',
        };
    }

    /**
     * Statuses that are still expected to progress on their own.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::PENDING, self::RUNNING];
    }

    /**
     * Statuses that will never change again without user action.
     *
     * @return list<self>
     */
    public static function terminal(): array
    {
        return [self::FINISHED, self::FAILED, self::EXPIRED];
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
