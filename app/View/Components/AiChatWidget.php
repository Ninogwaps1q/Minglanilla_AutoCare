<?php

namespace App\View\Components;

use App\Models\AiConversation;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class AiChatWidget extends Component
{
    public ?AiConversation $conversation;

    public function __construct()
    {
        $user = Auth::user();
        $this->conversation = $user instanceof User
            ? $user->aiConversations()
                ->with(['messages' => fn ($query) => $query->latest('id')->limit(12)])
                ->latest('id')
                ->first()
            : null;
    }

    public function render(): View|Closure|string
    {
        return view('components.ai-chat-widget');
    }
}
