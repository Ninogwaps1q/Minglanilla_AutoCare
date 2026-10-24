<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendAiMessageRequest;
use App\Services\AI\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    public function index(Request $request): View
    {
        $history = $request->session()->get('ai_history_'.$request->user()->id, []);

        return view('customer.ai-assistant.index', compact('history'));
    }

    public function store(SendAiMessageRequest $request, AiAssistantService $assistant): RedirectResponse|JsonResponse
    {
        $message = $request->string('message')->toString();
        $historyKey = 'ai_history_'.$request->user()->id;
        $history = $request->session()->get($historyKey, []);
        try {
            $response = $assistant->respond($request->user(), $history, $message);
        } catch (\RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 503);
            }

            return back()->withInput()->with('ai_widget_open', $request->boolean('widget'))->withErrors(['message' => $exception->getMessage()]);
        }

        $history[] = ['role' => 'user', 'message' => $message, 'action' => null];
        $history[] = ['role' => 'assistant', 'message' => $response['message'], 'action' => $response['action']];
        $request->session()->put($historyKey, array_slice($history, -12));

        if ($request->expectsJson()) {
            return response()->json(['reply' => $response['message'], 'action' => $response['action']]);
        }

        if ($request->boolean('widget')) {
            return back()->with('status', 'AI response received.')->with('ai_widget_open', true);
        }

        return redirect()->route('customer.ai-assistant.index')->with('status', 'AI response received.');
    }
}
