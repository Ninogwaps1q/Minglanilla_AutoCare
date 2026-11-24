<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBarangayRequest;
use App\Http\Requests\UpdateBarangayRequest;
use App\Models\Barangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarangayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            ...Barangay::AREA_FILTER_RULES,
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return view('admin.barangays.index', [
            'barangays' => Barangay::query()->with('municipality.province.region')->inArea($filters)
                ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', '%'.$search.'%'))
                ->orderBy('name')->orderBy('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
        ]);
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
