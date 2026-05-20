<?php

namespace App\Filament\Resources\RobotResults\Pages;

use App\Filament\Resources\RobotResults\RobotResultResource;
use App\Filament\Resources\RobotResults\Widgets\RobotResultStatsWidget;
use App\Support\ApiTablePaginator;
use App\Support\Format;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

class ListRobotResults extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = RobotResultResource::class;

    protected string $view = 'filament.resources.robot-results.pages.list-robot-results';

    /**
     * @return array<int, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            RobotResultStatsWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            // TODO: GameApi exposes no robot-game results endpoint. The table is
            // routed through the standard paginator with an empty payload so it
            // stays consistent with the other result pages. See TODO.md.
            ->records(fn (int|string $page, int|string $recordsPerPage, ?string $search, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => ApiTablePaginator::make(
                response: [],
                page: $page,
                perPage: $recordsPerPage,
                search: $search,
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
            ))
            ->columns([
                TextColumn::make('id')
                    ->label('#'),
                TextColumn::make('player')
                    ->label('Player'),
                TextColumn::make('result')
                    ->label('Result')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            ])
            ->emptyStateIcon('heroicon-o-cpu-chip')
            ->emptyStateHeading('No robot game records available')
            ->emptyStateDescription('Robot game history is not tracked by the wallet API in the current system.')
            ->striped();
    }
}
