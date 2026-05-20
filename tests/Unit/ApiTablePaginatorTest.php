<?php

use App\Support\ApiTablePaginator;
use Illuminate\Pagination\LengthAwarePaginator;

function sampleRows(): array
{
    return [
        ['id' => 1, 'name' => 'Alice', 'amount' => 300],
        ['id' => 2, 'name' => 'bob', 'amount' => 100],
        ['id' => 3, 'name' => 'Carol', 'amount' => 200],
    ];
}

it('returns a LengthAwarePaginator for a flat collection', function () {
    $paginator = ApiTablePaginator::make(response: sampleRows());

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(3)
        ->and($paginator->items())->toHaveCount(3);
});

it('unwraps a {data: [...]} envelope', function () {
    $paginator = ApiTablePaginator::make(response: ['data' => sampleRows()]);

    expect($paginator->total())->toBe(3);
});

it('respects pagination metadata when present', function () {
    $paginator = ApiTablePaginator::make(response: [
        'data' => sampleRows(),
        'current_page' => 2,
        'per_page' => 3,
        'total' => 30,
    ]);

    expect($paginator->total())->toBe(30)
        ->and($paginator->currentPage())->toBe(2)
        ->and($paginator->perPage())->toBe(3);
});

it('slices a flat collection by page and perPage', function () {
    $paginator = ApiTablePaginator::make(response: sampleRows(), page: 2, perPage: 2);

    expect($paginator->total())->toBe(3)
        ->and($paginator->items())->toHaveCount(1)
        ->and($paginator->currentPage())->toBe(2);
});

it('filters rows by search across the given keys', function () {
    $paginator = ApiTablePaginator::make(
        response: sampleRows(),
        search: 'bob',
        searchKeys: ['name'],
    );

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]['name'])->toBe('bob');
});

it('searches case-insensitively', function () {
    $paginator = ApiTablePaginator::make(
        response: sampleRows(),
        search: 'CAROL',
        searchKeys: ['name'],
    );

    expect($paginator->total())->toBe(1);
});

it('ignores search when no search keys are given', function () {
    $paginator = ApiTablePaginator::make(response: sampleRows(), search: 'bob');

    expect($paginator->total())->toBe(3);
});

it('sorts ascending by the given column', function () {
    $paginator = ApiTablePaginator::make(
        response: sampleRows(),
        sortColumn: 'amount',
        sortDirection: 'asc',
    );

    expect(array_column($paginator->items(), 'amount'))->toBe([100, 200, 300]);
});

it('sorts descending by the given column', function () {
    $paginator = ApiTablePaginator::make(
        response: sampleRows(),
        sortColumn: 'amount',
        sortDirection: 'desc',
    );

    expect(array_column($paginator->items(), 'amount'))->toBe([300, 200, 100]);
});

it('handles an empty response', function () {
    $paginator = ApiTablePaginator::make(response: []);

    expect($paginator->total())->toBe(0)
        ->and($paginator->items())->toBeEmpty();
});

it('discards non-array rows', function () {
    $paginator = ApiTablePaginator::make(response: [
        ['id' => 1, 'name' => 'Alice'],
        'not-a-row',
        42,
    ]);

    expect($paginator->total())->toBe(1);
});

it('clamps page and perPage to at least 1', function () {
    $paginator = ApiTablePaginator::make(response: sampleRows(), page: 0, perPage: 0);

    expect($paginator->currentPage())->toBe(1)
        ->and($paginator->perPage())->toBe(1);
});

it('appends the query string for shareable paginated URLs', function () {
    $paginator = ApiTablePaginator::make(response: sampleRows(), perPage: 1);

    expect($paginator->url(2))->toContain('page=2');
});
