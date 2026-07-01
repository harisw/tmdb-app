<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TitleTypeScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($type = request('title_type')) {
            $builder->where('titles.type', $type);
        }
    }
}
