<?php

namespace App\Filament\Resources\RobotResults\Pages;

use App\Filament\Resources\RobotResults\RobotResultResource;
use App\Filament\Resources\RobotResults\Widgets\RobotResultStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListRobotResults extends ListRecords
{
    protected static string $resource = RobotResultResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RobotResultStatsWidget::class,
        ];
    }
}
