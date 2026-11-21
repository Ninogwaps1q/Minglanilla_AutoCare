<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShopProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'barangay_id' => ['required', 'exists:barangays,id'],
            'latitude' => [$this->user()?->role === 'mechanic' ? 'required' : 'prohibited', 'numeric', 'between:9.9,10.6'],
            'longitude' => [$this->user()?->role === 'mechanic' ? 'required' : 'prohibited', 'numeric', 'between:123.5,124.1'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i', 'after:opening_time'],
            'booking_capacity' => ['sometimes', 'integer', 'min:1', 'max:'.($this->user()?->role === 'mechanic' ? '1' : '10')],
            'operating_days' => ['sometimes', 'array'],
            'operating_days.*' => ['integer', 'between:1,7', 'distinct'],
            'roadside_available' => ['sometimes', 'boolean'],
            'roadside_radius_km' => [$this->user()?->role === 'mechanic' && $this->boolean('roadside_available') ? 'required' : 'nullable', 'integer', 'between:1,200'],
            'gcash_number' => ['nullable', 'string', 'max:30'],
            'bank_transfer_details' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'cover_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=8000,max_height=8000'],
        ];
    }
}
