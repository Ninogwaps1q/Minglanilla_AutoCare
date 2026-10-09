<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
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
            'plate_number' => ['required', 'string', 'max:30', 'unique:vehicles,plate_number,'.$this->route('vehicle')->id],
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'between:1900,'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'engine_type' => ['nullable', 'string', 'max:50'],
            'current_mileage' => ['required', 'integer', 'min:0'],
            'vin' => ['nullable', 'string', 'max:50', 'unique:vehicles,vin,'.$this->route('vehicle')->id],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=8000,max_height=8000'],
        ];
    }
}
