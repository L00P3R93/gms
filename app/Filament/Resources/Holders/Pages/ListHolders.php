<?php

namespace App\Filament\Resources\Holders\Pages;

use App\Filament\Resources\Holders\HolderResource;
use App\Filament\Resources\Holders\Widgets\HolderStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHolders extends ListRecords
{
    protected static string $resource = HolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            HolderStatsWidget::class,
        ];
    }
}
