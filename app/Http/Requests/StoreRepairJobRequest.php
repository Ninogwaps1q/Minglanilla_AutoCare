<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRepairJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isShopOwner() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'description' => ['required', 'string', 'max:5000'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
