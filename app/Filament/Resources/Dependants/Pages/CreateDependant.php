<?php

namespace App\Filament\Resources\Dependants\Pages;

use App\Filament\Resources\Dependants\DependantResource;
use App\Models\Holder;
use Filament\Resources\Pages\CreateRecord;

class CreateDependant extends CreateRecord
{
    protected static string $resource = DependantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['share'] = $data['share'] / 100;

        return $data;
    }

    protected function afterCreate(): void
    {
        $holder = Holder::find($this->record->holder_id);

        if ($holder) {
            $holder->share = $holder->share - $this->record->share;
            $holder->save();
        }
    }
}
