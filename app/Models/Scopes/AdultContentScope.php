<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AdultContentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->user()?->hide_adult ?? true) {
            $builder->where('titles.is_adult', false);
        }
    }
}
