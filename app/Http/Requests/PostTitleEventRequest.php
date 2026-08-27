<?php

namespace App\Http\Requests;

use App\Domain\Enums\TitleEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostTitleEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title_id' => ['required', 'integer', 'exists:titles,id'],
            'type' => ['required', 'string', Rule::in(TitleEventType::values())],
            'value' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('type') === TitleEventType::RATE->value),
                'numeric',
                'between:0,5',
            ],
        ];
    }
}
