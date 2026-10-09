<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendAiMessageRequest;
use App\Services\AI\AiAssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    public function index(Request $request): View
    {
        $conversation = $request->user()->aiConversations()->with('messages')->latest()->first();

        return view('customer.ai-assistant.index', compact('conversation'));
    }

    public function store(SendAiMessageRequest $request, AiAssistantService $assistant): RedirectResponse
    {
        $conversation = $request->user()->aiConversations()->whereKey($request->integer('conversation_id'))->first()
            ?? $request->user()->aiConversations()->create(['title' => str($request->string('message')->toString())->limit(80), 'status' => 'active']);

        try {
            $assistant->respond($request->user(), $conversation, $request->string('message')->toString());
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withErrors(['message' => $exception->getMessage()]);
        }

        return redirect()->route('customer.ai-assistant.index')->with('status', 'AI response received.');
    }
}
