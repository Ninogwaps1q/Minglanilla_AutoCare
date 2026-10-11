<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceScheduleRequest extends FormRequest
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
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'last_service_date' => ['nullable', 'date', 'before_or_equal:today'],
            'last_service_mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'recommended_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_without:recommended_interval_mileage'],
            'recommended_interval_mileage' => ['nullable', 'integer', 'min:1', 'max:500000', 'required_without:recommended_interval_days'],
        ];
    }
}
