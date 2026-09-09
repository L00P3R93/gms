<?php

namespace App\Filament\Resources\Payees\Tables;

use App\Enums\PayeeStatus;
use App\Filament\Exports\PayeeExporter;
use App\Models\Payee;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('name')
                            ->weight('bold')
                            ->searchable()
                            ->sortable(),
                        TextColumn::make('designation')
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->searchable(),
                    ]),
                    Stack::make([
                        TextColumn::make('team')
                            ->badge()
                            ->color('info')
                            ->searchable(),
                        TextColumn::make('status')
                            ->badge(),
                    ])->visibleFrom('md'),
                    Stack::make([
                        TextColumn::make('phone')
                            ->searchable(),
                        TextColumn::make('payouts_count')
                            ->label('Payouts')
                            ->counts('payouts')
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PayeeStatus::class),
                SelectFilter::make('team')
                    ->options(fn () => Payee::distinct()->pluck('team', 'team')),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->tooltip('Edit Payee'),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Export Payees')
                    ->icon('hugeicons-file-export')
                    ->color('success')
                    ->exporter(PayeeExporter::class),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),

            ]);
    }
}
