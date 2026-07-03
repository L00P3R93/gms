<?php

namespace App\Filament\Resources\IncomeDistributions\Pages;

use App\Filament\Resources\IncomeDistributions\IncomeDistributionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewIncomeDistribution extends ViewRecord
{
    protected static string $resource = IncomeDistributionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
