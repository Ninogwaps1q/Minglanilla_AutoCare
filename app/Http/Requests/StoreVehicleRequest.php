<?php

namespace App\Http\Requests;

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plate_number' => ['required', 'string', 'max:30', Rule::unique('vehicles', 'plate_number')],
            'vehicle_type' => ['required', 'in:car,motorcycle,other'],
            'vehicle_make_id' => ['nullable', 'integer', 'exists:vehicle_makes,id'],
            'vehicle_model_id' => ['nullable', 'integer', 'exists:vehicle_models,id'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'year' => ['required', 'integer', 'between:1900,'.(now()->year + 1)],
            'variant' => ['nullable', 'string', 'max:100'],
            'variant_other' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'current_mileage' => ['nullable', 'integer', 'min:0'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=8000,max_height=8000'],
            'booking_draft' => ['nullable', 'array:shop_id,service_ids,appointment_date,appointment_time,service_barangay_id,service_street,service_landmark,reason,promo_code'],
            'booking_draft.shop_id' => ['nullable', 'integer'],
            'booking_draft.service_ids' => ['nullable', 'array', 'max:10'],
            'booking_draft.service_ids.*' => ['integer'],
            'booking_draft.appointment_date' => ['nullable', 'string', 'max:20'],
            'booking_draft.appointment_time' => ['nullable', 'string', 'max:20'],
            'booking_draft.service_barangay_id' => ['nullable', 'integer'],
            'booking_draft.service_street' => ['nullable', 'string', 'max:255'],
            'booking_draft.service_landmark' => ['nullable', 'string', 'max:255'],
            'booking_draft.reason' => ['nullable', 'string', 'max:2000'],
            'booking_draft.promo_code' => ['nullable', 'string', 'max:32'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $make = $this->filled('vehicle_make_id') ? VehicleMake::query()->find($this->integer('vehicle_make_id')) : null;
            $model = $this->filled('vehicle_model_id') ? VehicleModel::query()->find($this->integer('vehicle_model_id')) : null;
            if ($make && $make->vehicle_type !== $this->input('vehicle_type')) {
                $validator->errors()->add('vehicle_make_id', 'Choose a make for the selected vehicle type.');
            }
            if (! $make && blank($this->input('make'))) {
                $validator->errors()->add('make', 'Enter the make when it is not listed.');
            }
            if ($model && $model->vehicle_make_id !== $make?->id) {
                $validator->errors()->add('vehicle_model_id', 'Choose a model belonging to this make.');
            }
            if (! $model && blank($this->input('model'))) {
                $validator->errors()->add('model', 'Enter the model when it is not listed.');
            }
            if ($this->input('variant') === '__other' && blank($this->input('variant_other'))) {
                $validator->errors()->add('variant_other', 'Enter the variant when it is not listed.');
            }
            if ($model && ! in_array($this->input('variant'), [...($model->variants ?? []), '__other'], true)) {
                $validator->errors()->add('variant', 'Choose a variant listed for this model, or select Other.');
            }
        }];
    }
}
