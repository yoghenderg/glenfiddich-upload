<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function (): void {
            \App\Models\UploadSession::whereNull('media_id')->where('updated_at', '<', now()->subDay())
                ->pluck('id')->each(function ($id): void {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($id): void {
                        $session = \App\Models\UploadSession::lockForUpdate()->find($id);
                        if (! $session || $session->media_id || $session->updated_at->greaterThanOrEqualTo(now()->subDay())) {
                            return;
                        }
                        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('uploads/'.$session->id);
                        \Illuminate\Support\Facades\Storage::disk('local')->delete('media/'.$session->id);
                        $session->delete();
                    });
                });
        })->name('cleanup-abandoned-uploads')->hourly()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
