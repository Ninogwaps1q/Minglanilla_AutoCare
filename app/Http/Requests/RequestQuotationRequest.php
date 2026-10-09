<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestQuotationRequest extends FormRequest
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
            'shop_id' => ['required', 'integer', Rule::exists('shops', 'id')->where('verification_status', 'approved')->where('shop_status', 'active')],
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('customer_id', $this->user()->id)],
            'notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
