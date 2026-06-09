<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait RemembersModelRows
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  \Closure(): Collection<int, Model>  $callback
     * @return Collection<int, Model>
     */
    private function rememberModels(string $key, string $modelClass, \Closure $callback, int $seconds = 3600): Collection
    {
        $cachedRows = Cache::get($key);

        if (! $this->containsOnlyArrayRows($cachedRows)) {
            Cache::forget($key);

            $cachedRows = $callback()
                ->map(fn (Model $model): array => $model->getAttributes())
                ->all();

            Cache::put($key, $cachedRows, $seconds);
        }

        return $modelClass::hydrate($cachedRows)->values();
    }

    private function containsOnlyArrayRows(mixed $cachedRows): bool
    {
        if (! is_array($cachedRows)) {
            return false;
        }

        foreach ($cachedRows as $row) {
            if (! is_array($row)) {
                return false;
            }
        }

        return true;
    }
}
