<?php

namespace App\Http\Requests;

use App\Domain\Enums\TitleSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseTitlesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sort' => ['nullable', Rule::enum(TitleSort::class)],
        ];
    }

    public function sort(): TitleSort
    {
        return $this->enum('sort', TitleSort::class) ?? TitleSort::POPULAR;
    }
}
