<?php

namespace App\Models\Scopes;

use App\Models\Title;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Title>
 */
class TitleTypeScope implements Scope
{
    /**
     * @param  Builder<covariant Title>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if ($type = request('title_type')) {
            $builder->where('titles.type', $type);
        }
    }
}
