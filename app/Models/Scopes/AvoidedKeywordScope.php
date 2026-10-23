<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder as QueryBuilder;

class AvoidedKeywordScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $keywordIds = auth()->user()?->avoidedKeywordIds() ?? [];

        if ($keywordIds === []) {
            return;
        }

        $builder->whereNotExists(fn (QueryBuilder $query) => $query
            ->selectRaw('1')
            ->from('keyword_title')
            ->whereColumn('keyword_title.title_id', 'titles.id')
            ->whereIn('keyword_title.keyword_id', $keywordIds));
    }
}
