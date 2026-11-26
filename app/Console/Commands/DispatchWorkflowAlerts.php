<?php

namespace App\Console\Commands;

use App\Jobs\DeliverWorkflowAlert;
use App\Models\WorkflowAlert;
use App\Services\CustomerWorkflowAlerts;
use Illuminate\Console\Command;

class DispatchWorkflowAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:dispatch-workflow-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send appointment and estimate reminders and queue outstanding customer workflow alerts';

    /**
     * Execute the console command.
     */
    public function handle(CustomerWorkflowAlerts $alerts): int
    {
        $alerts->appointmentReminders();

        WorkflowAlert::query()->where('next_reminder_at', '<=', now())
            ->lazyById()->each(fn (WorkflowAlert $source) => $alerts->reminder($source));

        WorkflowAlert::query()->whereNull('completed_at')->where('created_at', '<=', now()->subMinutes(2))
            ->lazyById()->each(fn (WorkflowAlert $alert) => DeliverWorkflowAlert::dispatch($alert->id));

        return self::SUCCESS;
    }
}
