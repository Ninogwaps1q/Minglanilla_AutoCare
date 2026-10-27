<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepairJobItemRequest extends FormRequest
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
            'type' => ['required', Rule::in(['labor', 'part', 'service', 'other'])],
            'description' => [Rule::requiredIf(! $this->filled('spare_part_id')), 'nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'unit_price' => [Rule::requiredIf(! $this->filled('spare_part_id')), 'nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
        ];
    }
}
