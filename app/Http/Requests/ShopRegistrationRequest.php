<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShopRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isProviderOwner() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'legal_consent' => ['accepted'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'business_permit_number' => $this->user()?->role === 'mechanic'
                ? ['prohibited']
                : ['nullable', 'string', 'max:100', 'unique:shops'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
            'latitude' => ['required', 'numeric', 'between:4,22'],
            'longitude' => ['required', 'numeric', 'between:112,128'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i', 'after:opening_time'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'cover_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=8000,max_height=8000'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
        ];
    }
}
