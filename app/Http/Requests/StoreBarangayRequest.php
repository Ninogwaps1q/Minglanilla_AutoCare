<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBarangayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'municipality_id' => ['required', 'exists:municipalities,id'],
            'name' => ['required', 'string', 'max:255', 'unique:barangays,name,NULL,id,municipality_id,'.$this->integer('municipality_id')],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
