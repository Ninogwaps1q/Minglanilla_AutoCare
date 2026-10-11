<?php

namespace App\Models;

use App\Services\AI\AiResponseFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    /** @var array<string, array{label: string, route: string, icon: string}> */
    private const ACTIONS = [
        'find_shops' => ['label' => 'Find nearby shops', 'route' => 'map.index', 'icon' => 'geo-alt'],
        'book_service' => ['label' => 'Book a service', 'route' => 'customer.appointments.create', 'icon' => 'calendar-plus'],
        'appointments' => ['label' => 'Open my appointments', 'route' => 'customer.appointments.index', 'icon' => 'calendar-check'],
        'repairs' => ['label' => 'Track my repairs', 'route' => 'customer.repairs.index', 'icon' => 'tools'],
        'vehicles' => ['label' => 'Open my garage', 'route' => 'customer.vehicles.index', 'icon' => 'car-front'],
        'invoices' => ['label' => 'View bills and payments', 'route' => 'customer.invoices.index', 'icon' => 'receipt'],
        'reminders' => ['label' => 'Open maintenance reminders', 'route' => 'customer.reminders.index', 'icon' => 'calendar-event'],
        'messages' => ['label' => 'Open messages', 'route' => 'customer.messages.index', 'icon' => 'chat-dots'],
        'profile' => ['label' => 'Open my profile', 'route' => 'customer.profile.edit', 'icon' => 'person'],
        'roadside' => ['label' => 'Open Roadside Help', 'route' => 'customer.emergency-requests.index', 'icon' => 'life-preserver'],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function displayMessage(): string
    {
        return $this->role === 'assistant'
            ? AiResponseFormatter::format($this->message)
            : $this->message;
    }

    /** @return array{label: string, url: string, icon: string}|null */
    public function suggestedAction(): ?array
    {
        $name = data_get($this->metadata, 'action');

        if (! is_string($name) || ! array_key_exists($name, self::ACTIONS)) {
            return null;
        }

        $action = self::ACTIONS[$name];

        return ['label' => $action['label'], 'url' => route($action['route']), 'icon' => $action['icon']];
    }
}
