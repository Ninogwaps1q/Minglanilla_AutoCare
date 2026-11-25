<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\WorkflowAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OpenWorkflowAlertController extends Controller
{
    public function __invoke(Request $request, WorkflowAlert $workflowAlert): RedirectResponse
    {
        abort_unless($workflowAlert->customer_id === $request->user()->id, 404);

        $request->user()->notifications()->where('workflow_alert_id', $workflowAlert->id)->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->to($workflowAlert->destination());
    }
}
