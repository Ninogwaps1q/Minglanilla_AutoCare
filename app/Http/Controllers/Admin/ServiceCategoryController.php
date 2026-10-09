<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveServiceCategoryRequest;
use App\Models\ActivityLog;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.services.categories', ['categories' => ServiceCategory::query()->withCount('services')->orderBy('name')->paginate(15)]);
    }

    public function store(SaveServiceCategoryRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $category = ServiceCategory::query()->create($request->validated());
            $this->recordChange($request, $category, 'created_service_category');
        });

        return back()->with('status', 'Service category created.');
    }

    public function update(SaveServiceCategoryRequest $request, ServiceCategory $category): RedirectResponse
    {
        DB::transaction(function () use ($request, $category): void {
            $category->update($request->validated());
            $this->recordChange($request, $category, 'updated_service_category');
        });

        return back()->with('status', 'Service category updated.');
    }

    public function destroy(Request $request, ServiceCategory $category): RedirectResponse
    {
        DB::transaction(function () use ($request, $category): void {
            $category = ServiceCategory::query()->lockForUpdate()->findOrFail($category->id);
            if ($category->services()->exists()) {
                throw ValidationException::withMessages(['category' => 'This category contains services. Reassign them before deleting the category.']);
            }
            $this->recordChange($request, $category, 'deleted_service_category');
            $category->delete();
        });

        return back()->with('status', 'Unused service category deleted.');
    }

    private function recordChange(Request $request, ServiceCategory $category, string $action): void
    {
        ActivityLog::query()->create([
            'user_id' => $request->user()->id, 'action' => $action, 'module' => 'service_catalog',
            'description' => "Category #{$category->id}: {$category->name}.", 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }
}
