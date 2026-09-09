<?php

namespace App\Filament\Resources\Dependants\Tables;

use App\Enums\DependantStatus;
use App\Filament\Exports\DependantExporter;
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

class DependantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('name')
                            ->weight('bold')
                            ->searchable(),
                        TextColumn::make('holder.name')
                            ->label('Holder')
                            ->color('gray')
                            ->size(TextSize::Small),
                    ]),
                    Stack::make([
                        TextColumn::make('status')
                            ->badge(),
                        TextColumn::make('share_percent')
                            ->label('Share %')
                            ->suffix('%')
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                    Stack::make([
                        TextColumn::make('phone')
                            ->color('gray'),
                        TextColumn::make('id_no')
                            ->label('ID Number')
                            ->color('gray')
                            ->size(TextSize::Small),
                    ])->visibleFrom('md'),
                ])->from('md'),
            ])
            ->filters([
                SelectFilter::make('holder_id')
                    ->label('Holder')
                    ->relationship('holder', 'name'),
                SelectFilter::make('status')
                    ->options(DependantStatus::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->tooltip('Edit Dependant'),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label('Export Dependants')
                    ->icon('hugeicons-file-export')
                    ->color('success')
                    ->exporter(DependantExporter::class),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
