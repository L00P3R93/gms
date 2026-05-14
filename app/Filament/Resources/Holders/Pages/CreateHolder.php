<?php

namespace App\Filament\Resources\Holders\Pages;

use App\Filament\Resources\Holders\HolderResource;
use App\Models\HolderWallet;
use Filament\Resources\Pages\CreateRecord;

class CreateHolder extends CreateRecord
{
    protected static string $resource = HolderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['share'] = $data['share'] / 100;

        return $data;
    }

    protected function afterCreate(): void
    {
        HolderWallet::create([
            'holder_id' => $this->record->id,
            'balance' => 0.00,
            'updated_at' => now(),
        ]);
    }
}
