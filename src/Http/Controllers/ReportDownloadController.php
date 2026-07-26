<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Lalalili\ReportQueue\Support\HostUser;
use Lalalili\ReportQueue\Support\ReportAccess;
use Lalalili\ReportQueue\Support\ReportModel;
use Lalalili\ReportQueue\Support\ReportStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a finished export.
 *
 * The report is resolved by hand rather than through route model binding: the
 * model class is configurable, and binding `{report}` globally would hijack the
 * parameter for the host's own routes.
 */
class ReportDownloadController
{
    public function __invoke(Request $request, int|string $report): StreamedResponse
    {
        $model = ReportModel::query()->findOrFail($report);

        $user = $request->user() ?? HostUser::current();

        abort_unless(app(ReportAccess::class)->canAccess($model, $user), 403);

        abort_unless($model->isDownloadable(), 404);

        $disk = filled($model->file_disk)
            ? (string) $model->file_disk
            : app(ReportStorage::class)->disk();

        $path = (string) $model->file_path;

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $model->downloadName());
    }
}
