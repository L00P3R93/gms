<?php

namespace App\Filament\Resources\CompanyWithdraws\Pages;

use App\Filament\Resources\CompanyWithdraws\CompanyWithdrawResource;
use App\Filament\Resources\CompanyWithdraws\Widgets\CompanyWithdrawStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanyWithdraws extends ListRecords
{
    protected static string $resource = CompanyWithdrawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CompanyWithdrawStatsWidget::class,
        ];
    }
}
