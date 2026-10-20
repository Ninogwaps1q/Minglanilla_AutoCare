<?php

namespace App\Http\Requests;

use App\Models\RepairJob;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
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
        $repairJob = $this->route('repairJob');
        $hasSavedWorkItems = $repairJob instanceof RepairJob && $repairJob->items()->exists();

        return [
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => [$hasSavedWorkItems ? 'sometimes' : 'required', 'array', 'min:1', 'max:50'],
            'items.*' => ['array:description,quantity,unit_price'],
            'items.*.description' => ['required', 'string', 'max:1000'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'],
        ];
    }
}
