<?php

namespace App\Filament\Pages;

use App\Support\ApiTablePaginator;
use Filament\Tables\Columns\Column;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A finance report whose `items` are a server-paged drill-down. The summary
 * cards and breakdown tables come from {@see FinanceReportPage}; the rows are
 * shown in a Filament table that forwards page, page size and filters to the
 * API, so nothing is sliced or filtered in memory.
 */
abstract class FinanceListReportPage extends FinanceReportPage implements HasTable
{
    use InteractsWithTable;

    /**
     * @return list<Column>
     */
    abstract protected function columns(): array;

    /**
     * @return list<BaseFilter>
     */
    protected function tableFilterDefinitions(): array
    {
        return [];
    }

    protected function emptyHeading(): string
    {
        return 'Nothing to show';
    }

    public function hasItemsTable(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): LengthAwarePaginator => ApiTablePaginator::fromReport($this->getReport()))
            ->columns($this->columns())
            ->filters($this->tableFilterDefinitions())
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-document-magnifying-glass')
            ->emptyStateHeading(fn (): string => $this->apiError ? 'Report unavailable' : $this->emptyHeading())
            ->emptyStateDescription(fn (): string => $this->apiError
                ? 'The wallet API could not be reached. Refresh the page to try again.'
                : 'Try a different period or filter.')
            ->striped();
    }

    /**
     * The current value of a table filter, or null when unset.
     */
    protected function filterValue(string $name): mixed
    {
        $value = $this->tableFilters[$name]['value'] ?? null;

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function queryFilters(): array
    {
        $perPage = $this->getTableRecordsPerPage();

        return [
            ...parent::queryFilters(),
            'page' => max(1, (int) $this->getTablePage()),
            'per_page' => is_numeric($perPage) ? min(200, (int) $perPage) : 50,
        ];
    }

    protected function onFilterApplied(): void
    {
        $this->resetPage();

        parent::onFilterApplied();
    }
}
