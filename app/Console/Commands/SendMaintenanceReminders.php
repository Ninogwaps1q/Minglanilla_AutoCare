<?php

namespace App\Console\Commands;

use App\Models\MaintenanceReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendMaintenanceReminders extends Command
{
    protected $signature = 'app:send-maintenance-reminders';

    protected $description = 'Create in-app notifications for due maintenance reminders.';

    public function handle(): int
    {
        $sent = 0;

        MaintenanceReminder::query()
            ->where('status', 'pending')
            ->whereDate('reminder_date', '<=', today())
            ->with('customer:id')
            ->orderBy('id')
            ->chunkById(100, function ($reminders) use (&$sent): void {
                foreach ($reminders as $reminder) {
                    $sent += DB::transaction(function () use ($reminder): int {
                        $reminder = MaintenanceReminder::query()->lockForUpdate()->find($reminder->id);
                        if ($reminder === null || $reminder->status !== 'pending' || $reminder->reminder_date->isAfter(today())) {
                            return 0;
                        }
                        $notifications = $reminder->customer->notifications();
                        if ($notifications->where('type', 'maintenance_due')->where('data->reminder_id', $reminder->id)->exists()) {
                            return 0;
                        }
                        $reminder->customer->notifications()->create([
                            'type' => 'maintenance_due',
                            'message' => "{$reminder->title} is due for your vehicle.",
                            'title' => 'Maintenance due',
                            'data' => ['reminder_id' => $reminder->id],
                        ]);

                        return 1;
                    });
                }
            });

        $this->info("Sent {$sent} maintenance reminder notification(s).");

        return self::SUCCESS;
    }
}
