<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsumeRepairPartRequest extends FormRequest
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
            'repair_job_id' => ['required', 'integer', 'exists:repair_jobs,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'repair_job_item_id' => ['nullable', 'integer', 'exists:repair_job_items,id'],
            'spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
        ];
    }
}
