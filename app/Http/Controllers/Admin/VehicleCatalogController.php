<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VehicleCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['type' => ['nullable', 'in:car,motorcycle']]);

        return view('admin.vehicle-catalog.index', [
            'makes' => VehicleMake::query()->withCount('models')
                ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('vehicle_type', $type))
                ->orderBy('vehicle_type')->orderBy('name')->get(),
            'allMakes' => VehicleMake::query()->orderBy('vehicle_type')->orderBy('name')->get(),
            'models' => VehicleModel::query()->with('make')->orderBy('name')->paginate(25)->withQueryString(),
            'selectedType' => $filters['type'] ?? null,
        ]);
    }

    public function storeMake(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('vehicle_makes')->where('vehicle_type', $request->input('vehicle_type'))],
            'vehicle_type' => ['required', 'in:car,motorcycle'],
        ]);
        VehicleMake::query()->create([...$attributes, 'source' => 'admin']);

        return to_route('admin.vehicle-catalog.index')->with('status', 'Vehicle make added.');
    }

    public function updateMake(Request $request, VehicleMake $vehicleMake): RedirectResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('vehicle_makes')->where('vehicle_type', $vehicleMake->vehicle_type)->ignore($vehicleMake)],
        ]);
        $vehicleMake->update($attributes);
        Vehicle::query()->where('vehicle_make_id', $vehicleMake->id)->update(['make' => $attributes['name']]);

        return to_route('admin.vehicle-catalog.index')->with('status', 'Vehicle make updated.');
    }

    public function storeModel(Request $request): RedirectResponse
    {
        $attributes = $this->modelAttributes($request);
        VehicleModel::query()->create([...$attributes, 'source' => 'admin']);

        return to_route('admin.vehicle-catalog.index')->with('status', 'Vehicle model added.');
    }

    public function updateModel(Request $request, VehicleModel $vehicleModel): RedirectResponse
    {
        $attributes = $this->modelAttributes($request, $vehicleModel);
        $newMake = VehicleMake::query()->findOrFail($attributes['vehicle_make_id']);
        if ($vehicleModel->make->vehicle_type !== $newMake->vehicle_type
            && Vehicle::query()->where('vehicle_model_id', $vehicleModel->id)->exists()) {
            throw ValidationException::withMessages(['vehicle_make_id' => 'This model is already used by vehicles of another type. Add a new model for the new type.']);
        }
        $vehicleModel->update($attributes);
        Vehicle::query()->where('vehicle_model_id', $vehicleModel->id)->update([
            'vehicle_make_id' => $attributes['vehicle_make_id'],
            'make' => $newMake->name,
            'model' => $attributes['name'],
        ]);

        return to_route('admin.vehicle-catalog.index')->with('status', 'Vehicle model updated.');
    }

    /** @return array{vehicle_make_id: int, name: string, variants: array<int, string>} */
    private function modelAttributes(Request $request, ?VehicleModel $vehicleModel = null): array
    {
        $attributes = $request->validate([
            'vehicle_make_id' => ['required', 'integer', 'exists:vehicle_makes,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('vehicle_models')->where('vehicle_make_id', $request->input('vehicle_make_id'))->ignore($vehicleModel)],
            'variants' => ['nullable', 'string', 'max:2000'],
        ]);
        $variants = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\r\n]+/', $attributes['variants'] ?? '') ?: []))));

        return [
            'vehicle_make_id' => (int) $attributes['vehicle_make_id'],
            'name' => $attributes['name'],
            'variants' => $variants ?: ['Base'],
        ];
    }
}
