<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Enums\ExpenseCategory;
use App\Filament\Exports\ExpenseExporter;
use App\Filament\Imports\ExpenseImporter;
use App\Models\Expense;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('category')
                            ->badge()
                            ->searchable(),
                        TextColumn::make('description')
                            ->limit(50)
                            ->color('gray')
                            ->size(TextSize::Small),
                    ]),
                    Stack::make([
                        TextColumn::make('amount')
                            ->prefix('KES ')
                            ->numeric(2)
                            ->weight('bold'),
                        TextColumn::make('created_at')
                            ->label('Date')
                            ->date()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                    IconColumn::make('has_receipt')
                        ->label('Receipt')
                        ->getStateUsing(fn (Expense $record): bool => $record->hasMedia('receipt'))
                        ->boolean()
                        ->trueIcon('heroicon-o-paper-clip')
                        ->falseIcon('heroicon-o-minus')
                        ->trueColor('success')
                        ->falseColor('gray')
                        ->visibleFrom('md'),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(ExpenseCategory::class),
                Filter::make('created_at')
                    ->label('Date Range')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->tooltip('Edit Expense'),
            ])
            ->toolbarActions([
                ImportAction::make()
                    ->label('Import Expenses')
                    ->icon('hugeicons-file-import')
                    ->color('info')
                    ->importer(ExpenseImporter::class),

                ExportAction::make()
                    ->label('Export Expenses')
                    ->icon('hugeicons-file-export')
                    ->color('success')
                    ->exporter(ExpenseExporter::class),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
