<?php

declare(strict_types=1);

namespace Lalalili\ReportQueue\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Lalalili\ReportQueue\Models\Report;

/**
 * All user-facing messaging for the export queue, in one place so hosts can
 * silence individual notifications through config instead of subclassing.
 */
final class ReportNotifier
{
    public function queued(Report $report): void
    {
        if (config('report-queue.notifications.on_queued', true) !== true) {
            return;
        }

        Notification::make()
            ->success()
            ->title((string) trans('report-queue::messages.queued.title'))
            ->body((string) trans('report-queue::messages.queued.body'))
            ->actions(array_filter([$this->goToReportsAction()]))
            ->send();
    }

    public function duplicate(Report $existing): void
    {
        if (config('report-queue.notifications.on_duplicate', true) !== true) {
            return;
        }

        Notification::make()
            ->warning()
            ->title((string) trans('report-queue::messages.duplicate.title'))
            ->body((string) trans('report-queue::messages.duplicate.body'))
            ->actions(array_filter([$this->goToReportsAction()]))
            ->send();
    }

    public function finished(Report $report): void
    {
        if (config('report-queue.notifications.on_finished', true) !== true) {
            return;
        }

        $this->sendToOwner($report, function (Model $user) use ($report): void {
            $notification = Notification::make()
                ->success()
                ->title((string) trans('report-queue::messages.finished.title', ['type' => $report->type]))
                ->body((string) trans('report-queue::messages.finished.body', ['hours' => Report::downloadTtlHours()]));

            $url = $report->downloadUrl();

            if ($url !== null) {
                $notification->actions([
                    Action::make('download')
                        ->label((string) trans('report-queue::messages.finished.action'))
                        ->button()
                        ->url($url)
                        ->openUrlInNewTab()
                        ->markAsRead(),
                ]);
            }

            $notification->sendToDatabase($user);
        });
    }

    public function failed(Report $report): void
    {
        if (config('report-queue.notifications.on_failed', true) !== true) {
            return;
        }

        $this->sendToOwner($report, function (Model $user) use ($report): void {
            Notification::make()
                ->danger()
                ->title((string) trans('report-queue::messages.failed.title', ['type' => $report->type]))
                ->body((string) trans('report-queue::messages.failed.body'))
                ->sendToDatabase($user);
        });
    }

    private function goToReportsAction(): ?Action
    {
        $url = ReportResourceLocator::indexUrl();

        if ($url === null) {
            return null;
        }

        return Action::make('go-to-reports')
            ->label((string) trans('report-queue::messages.actions.go_to_reports'))
            ->url($url);
    }

    /**
     * @param  callable(Model): void  $callback
     */
    private function sendToOwner(Report $report, callable $callback): void
    {
        $ownerId = $report->getAttribute(HostUser::foreignKey());

        $user = is_int($ownerId) || is_string($ownerId) ? HostUser::find($ownerId) : null;

        // Database notifications require the host user model to be notifiable.
        if ($user === null || ! in_array(Notifiable::class, class_uses_recursive($user), true)) {
            return;
        }

        $callback($user);
    }
}
