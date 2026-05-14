<?php

namespace App\Filament\Resources\Dependants\Pages;

use App\Filament\Resources\Dependants\DependantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDependants extends ListRecords
{
    protected static string $resource = DependantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
