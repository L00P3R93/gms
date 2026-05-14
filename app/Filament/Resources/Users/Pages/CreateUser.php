<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private ?string $pendingRole = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingRole = $data['role'] ?? null;
        unset($data['role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->pendingRole) {
            $this->record->assignRole($this->pendingRole);
        }
    }
}
