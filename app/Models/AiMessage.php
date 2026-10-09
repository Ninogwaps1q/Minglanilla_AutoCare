<?php

namespace App\Models;

use App\Services\AI\AiResponseFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
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
}
