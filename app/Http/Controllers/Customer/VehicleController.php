<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('customer.vehicles.index', ['vehicles' => auth()->user()->vehicles()->latest()->get()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', Vehicle::class);

        return view('customer.vehicles.form', ['vehicle' => new Vehicle]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $this->authorize('create', Vehicle::class);
        $attributes = $request->safe()->except('photo');
        if ($request->hasFile('photo')) {
            $attributes['photo'] = $request->file('photo')->store('vehicles', 'public');
        }
        $request->user()->vehicles()->create($attributes);

        return redirect()->route('customer.vehicles.index')->with('status', 'Vehicle added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle): View
    {
        $this->authorize('view', $vehicle);

        return view('customer.vehicles.show', ['vehicle' => $vehicle->load(['serviceRecords.shop'])]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle): View
    {
        $this->authorize('update', $vehicle);

        return view('customer.vehicles.form', compact('vehicle'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);
        $attributes = $request->safe()->except('photo');
        if ($request->hasFile('photo')) {
            $previousPhoto = $vehicle->photo;
            $attributes['photo'] = $request->file('photo')->store('vehicles', 'public');
            if ($previousPhoto !== null) {
                Storage::disk('public')->delete($previousPhoto);
            }
        }
        $vehicle->update($attributes);

        return redirect()->route('customer.vehicles.show', $vehicle)->with('status', 'Vehicle updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('delete', $vehicle);
        if ($vehicle->photo) {
            Storage::disk('public')->delete($vehicle->photo);
        }
        $vehicle->delete();

        return redirect()->route('customer.vehicles.index')->with('status', 'Vehicle removed successfully.');
    }
}
