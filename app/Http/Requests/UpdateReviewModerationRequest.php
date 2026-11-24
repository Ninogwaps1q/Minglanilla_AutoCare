<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewModerationRequest extends FormRequest
{
    public const VIOLATIONS = [
        'abuse' => 'Abuse or harassment',
        'spam' => 'Spam',
        'private_information' => 'Private information',
        'other' => 'Other rule violation',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['prohibited'],
            'violation' => ['required', 'in:'.implode(',', array_keys(self::VIOLATIONS))],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
