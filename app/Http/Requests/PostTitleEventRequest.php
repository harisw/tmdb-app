<?php

namespace App\Http\Requests;

use App\Domain\Enums\InteractionEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostTitleEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title_id' => ['required', 'integer', 'exists:title,id'],
            'type' => ['required', 'string', Rule::contains(InteractionEventType::values())],
            'rate' => [
                Rule::requiredIf(fn ($req) => $req->type === InteractionEventType::RATE->value),
                'float',
            ],
        ];
    }
}
