<?php

namespace App\Models;

use App\Notifications\WorkflowEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowAlert extends Model
{
    protected $fillable = ['customer_id', 'event', 'event_key', 'data', 'channels', 'completed_at', 'next_reminder_at'];

    protected function casts(): array
    {
        return ['event' => WorkflowEvent::class, 'data' => 'array', 'channels' => 'array', 'completed_at' => 'datetime', 'next_reminder_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destination(): string
    {
        $data = $this->data;
        if (isset($data['quotation_id'])) {
            return route('customer.quotations.index', ['focus' => $data['quotation_id']]);
        }
        if (isset($data['invoice_id'])) {
            return route('customer.invoices.index', ['focus' => $data['invoice_id']]);
        }

        return route('customer.repairs.index', ['repair_job_id' => $data['repair_job_id']]).'#repair-'.$data['repair_job_id'];
    }
}
