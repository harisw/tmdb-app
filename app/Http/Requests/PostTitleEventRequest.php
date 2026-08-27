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
            'title_id' => ['required', 'integer', 'exists:title,id'],
            'type' => ['required', 'string', Rule::contains(TitleEventType::values())],
            'rate' => [
                Rule::requiredIf(fn ($req) => $req->type === TitleEventType::RATE->value),
                'float',
            ],
        ];
    }
}
