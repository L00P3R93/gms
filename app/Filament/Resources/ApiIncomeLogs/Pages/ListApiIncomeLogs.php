<?php

namespace App\Filament\Resources\ApiIncomeLogs\Pages;

use App\Filament\Resources\ApiIncomeLogs\ApiIncomeLogResource;
use Filament\Resources\Pages\ListRecords;

class ListApiIncomeLogs extends ListRecords
{
    protected static string $resource = ApiIncomeLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
