<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInspectionRequest extends FormRequest
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
            'inspection_date' => ['required', 'date', 'before_or_equal:today'],
            'overall_condition' => ['required', 'in:Good,Needs Attention,Critical'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['array:category,item_name,condition,recommendation'],
            'items.*.category' => ['required', 'string', 'max:255'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.condition' => ['required', 'in:Good,Needs Attention,Critical'],
            'items.*.recommendation' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
