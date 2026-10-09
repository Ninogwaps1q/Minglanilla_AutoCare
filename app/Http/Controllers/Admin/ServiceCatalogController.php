<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCatalogServiceRequest;
use App\Http\Requests\UpdateCatalogServiceRequest;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
        ]);

        return view('admin.services.index', [
            'services' => Service::query()->with('category')
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
                ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
                ->orderBy('name')->paginate()->withQueryString(),
            'categories' => ServiceCategory::query()->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreCatalogServiceRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $service = Service::query()->create($request->validated());
            $this->logChange($request, $service, 'created_service');
        });

        return redirect()->route('admin.services.index')->with('status', 'Service created.');
    }

    public function edit(Service $service): View
    {
        return view('admin.services.edit', [
            'service' => $service,
            'categories' => ServiceCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateCatalogServiceRequest $request, Service $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service): void {
            $service->update($request->validated());
            $this->logChange($request, $service, 'updated_service');
        });

        return redirect()->route('admin.services.index')->with('status', 'Service updated.');
    }

    private function logChange(Request $request, Service $service, string $action): void
    {
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'module' => 'service_catalog',
            'description' => "Service #{$service->id}: {$service->name}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
