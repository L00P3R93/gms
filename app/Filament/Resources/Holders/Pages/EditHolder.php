<?php

namespace App\Filament\Resources\Holders\Pages;

use App\Filament\Resources\Holders\HolderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHolder extends EditRecord
{
    protected static string $resource = HolderResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['share'] = $data['share'] * 100;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['share'] = $data['share'] / 100;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
