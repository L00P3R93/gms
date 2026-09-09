<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('user.name')
                            ->label('Actor')
                            ->weight('bold')
                            ->placeholder('System')
                            ->searchable(),
                        TextColumn::make('auditable_type')
                            ->label('Model')
                            ->formatStateUsing(fn (string $state): string => class_basename($state))
                            ->color('gray')
                            ->size(TextSize::Small),
                    ]),
                    Stack::make([
                        TextColumn::make('event')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default => 'gray',
                            }),
                        TextColumn::make('created_at')
                            ->label('When')
                            ->since()
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                    TextColumn::make('ip_address')
                        ->label('IP Address')
                        ->visibleFrom('md'),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
