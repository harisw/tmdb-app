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
            'value' => ['nullable', 'numeric', 'between:0,10'],
        ];
    }
}
