<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Illuminate\Support\Str;
use Lalalili\ReportQueue\Models\Report;

/**
 * Resolves where an export file lands and what it is called.
 */
final class ReportStorage
{
    public function disk(): string
    {
        $configured = Config::nullableString('storage.disk');

        if ($configured !== null) {
            return $configured;
        }

        $default = config('filesystems.default', 'local');

        return is_string($default) && $default !== '' ? $default : 'local';
    }

    /**
     * Random suffix keeps concurrent exports of the same type from colliding.
     */
    public function filename(string $prefix, string $extension = 'xlsx'): string
    {
        $resolved = Seam::call('storage.filename_resolver', null, $prefix, $extension);

        if (is_string($resolved) && $resolved !== '') {
            return $resolved;
        }

        return $prefix.date('YmdHis').'_'.Str::random(6).'.'.$extension;
    }

    public function path(Report $report, string $filename): string
    {
        $resolved = Seam::call('storage.path_resolver', null, $report, $filename);

        if (is_string($resolved) && $resolved !== '') {
            return $resolved;
        }

        $prefix = trim(Config::string('storage.path_prefix', 'reports'), '/');

        return $prefix === '' ? $filename : $prefix.'/'.$filename;
    }
}
