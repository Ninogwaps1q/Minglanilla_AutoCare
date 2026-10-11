<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMaintenanceRecommendationRequest;
use App\Models\MaintenanceRecommendation;
use Illuminate\Http\RedirectResponse;

class MaintenanceRecommendationController extends Controller
{
    public function update(UpdateMaintenanceRecommendationRequest $request, MaintenanceRecommendation $maintenanceRecommendation): RedirectResponse
    {
        abort_unless($maintenanceRecommendation->customer_id === $request->user()->id, 404);
        $maintenanceRecommendation->update($request->validated());

        return back()->with('status', 'Recommendation updated.');
    }
}
