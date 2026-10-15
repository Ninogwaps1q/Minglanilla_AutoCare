<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\Component;

class AiChatWidget extends Component
{
    /** @var array<int, array{role: string, message: string, action?: array{label: string, url: string, icon: string}|null}> */
    public array $history;

    public function __construct()
    {
        $this->history = Auth::check()
            ? Session::get('ai_history_'.Auth::id(), [])
            : [];
    }

    public function render(): View|Closure|string
    {
        return view('components.ai-chat-widget');
    }
}
