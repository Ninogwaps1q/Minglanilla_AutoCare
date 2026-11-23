<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends StoreVehicleRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['plate_number'] = ['required', 'string', 'max:30', Rule::unique('vehicles', 'plate_number')->ignore($this->route('vehicle'))];

        return $rules;
    }
}
