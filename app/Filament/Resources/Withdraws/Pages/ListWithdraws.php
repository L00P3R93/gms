<?php

namespace App\Filament\Resources\Withdraws\Pages;

use App\Filament\Resources\Withdraws\Widgets\WithdrawStatsWidget;
use App\Filament\Resources\Withdraws\WithdrawResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWithdraws extends ListRecords
{
    protected static string $resource = WithdrawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WithdrawStatsWidget::class,
        ];
    }
}
