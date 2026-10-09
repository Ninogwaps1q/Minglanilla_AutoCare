<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBarangayRequest;
use App\Http\Requests\UpdateBarangayRequest;
use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BarangayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('admin.barangays.index', ['barangays' => Barangay::query()->with('municipality')->orderBy('name')->paginate(), 'municipalities' => Municipality::query()->orderBy('name')->get()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBarangayRequest $request): RedirectResponse
    {
        Barangay::query()->create($request->validated());

        return back()->with('status', 'Barangay created.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBarangayRequest $request, Barangay $barangay): RedirectResponse
    {
        $barangay->update($request->validated());

        return back()->with('status', 'Barangay updated.');
    }
}
