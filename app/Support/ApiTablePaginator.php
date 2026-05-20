<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Builds a LengthAwarePaginator from a raw GameApi response for Filament v5
 * table `records()` closures.
 *
 * If the response carries pagination metadata it is respected and the API's
 * own totals are used. Otherwise the response is treated as a flat collection,
 * sliced locally, and wrapped with the correct total. No GameApi endpoint
 * returns pagination metadata today, so in practice every payload takes the
 * flat path — the paginated branch is kept so a future API change needs no
 * call-site edits.
 */
class ApiTablePaginator
{
    /**
     * @param  mixed  $response  Raw decoded GameApi response.
     * @param  list<string>  $searchKeys  Row keys matched against $search.
     */
    public static function make(
        mixed $response,
        int|string $page = 1,
        int|string $perPage = 10,
        ?string $search = null,
        array $searchKeys = [],
        ?string $sortColumn = null,
        ?string $sortDirection = null,
    ): LengthAwarePaginator {
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);

        if (self::isPaginatedPayload($response)) {
            // TODO: when a GameApi list endpoint starts returning pagination
            // metadata, forward $search/$sortColumn to it as query params
            // rather than relying on the in-memory fallback used for flat data.
            return self::fromPaginatedPayload($response, $page, $perPage);
        }

        $rows = self::rows(self::unwrap($response));
        $rows = self::applySearch($rows, $search, $searchKeys);
        $rows = self::applySort($rows, $sortColumn, $sortDirection);

        return self::paginator(
            $rows->forPage($page, $perPage)->values()->all(),
            $rows->count(),
            $perPage,
            $page,
        );
    }

    /**
     * A payload is paginated when a `data` array sits alongside pagination
     * totals — either flat keys or a nested `meta` block.
     */
    protected static function isPaginatedPayload(mixed $response): bool
    {
        if (! is_array($response) || ! isset($response['data']) || ! is_array($response['data'])) {
            return false;
        }

        $meta = is_array($response['meta'] ?? null) ? $response['meta'] : $response;

        return isset($meta['current_page']) || isset($meta['total']) || isset($meta['per_page']);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected static function fromPaginatedPayload(array $response, int $page, int $perPage): LengthAwarePaginator
    {
        $meta = is_array($response['meta'] ?? null) ? $response['meta'] : $response;
        $rows = self::rows($response['data']);

        return self::paginator(
            $rows->all(),
            (int) ($meta['total'] ?? $rows->count()),
            (int) ($meta['per_page'] ?? $perPage),
            (int) ($meta['current_page'] ?? $page),
        );
    }

    /**
     * Unwrap a `{ data: [...] }` envelope from an otherwise flat response.
     */
    protected static function unwrap(mixed $response): mixed
    {
        if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

    /**
     * Keep only array rows and drop string keys — the API sometimes returns
     * collections keyed by id (e.g. game results).
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected static function rows(mixed $rows): Collection
    {
        return collect(is_array($rows) ? $rows : [])
            ->filter(fn ($row): bool => is_array($row))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return Collection<int, array<string, mixed>>
     */
    protected static function applySearch(Collection $rows, ?string $search, array $keys): Collection
    {
        $search = trim((string) $search);

        if ($search === '' || $keys === []) {
            return $rows;
        }

        $needle = mb_strtolower($search);

        return $rows
            ->filter(function (array $row) use ($needle, $keys): bool {
                foreach ($keys as $key) {
                    if (str_contains(mb_strtolower((string) ($row[$key] ?? '')), $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected static function applySort(Collection $rows, ?string $column, ?string $direction): Collection
    {
        if ($column === null || $column === '') {
            return $rows;
        }

        $sorted = $rows->sortBy(
            fn (array $row) => $row[$column] ?? null,
            SORT_NATURAL | SORT_FLAG_CASE,
        );

        if (strtolower((string) $direction) === 'desc') {
            $sorted = $sorted->reverse();
        }

        return $sorted->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected static function paginator(array $items, int $total, int $perPage, int $page): LengthAwarePaginator
    {
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'pageName' => 'page',
        ]);

        return $paginator->withQueryString();
    }
}
