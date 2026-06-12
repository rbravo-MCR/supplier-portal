<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait WithSearchableCatalog
{
    /**
     * Apply a search filter to a query builder using LIKE.
     *
     * @param  list<string>  $columns
     */
    public function applySearchFilter(Builder $query, string $term, array $columns): Builder
    {
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term, $columns) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    /**
     * Apply a search filter to a query builder using exact match.
     *
     * @param  list<string>  $columns
     */
    public function applySearchFilterExact(Builder $query, string $term, array $columns): Builder
    {
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term, $columns) {
            foreach ($columns as $column) {
                $query->orWhere($column, $term);
            }
        });
    }
}
