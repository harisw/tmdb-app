<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OnboardingRequest extends FormRequest
{
    public const MIN_PICKS = 3;

    public const MAX_PICKS = 20;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title_ids' => ['required', 'array', 'min:'.self::MIN_PICKS, 'max:'.self::MAX_PICKS],
            'title_ids.*' => ['required', 'integer', 'distinct', 'exists:titles,id'],
        ];
    }
}
