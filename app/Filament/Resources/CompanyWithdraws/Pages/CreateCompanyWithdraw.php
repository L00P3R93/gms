<?php

namespace App\Filament\Resources\CompanyWithdraws\Pages;

use App\Filament\Resources\CompanyWithdraws\CompanyWithdrawResource;
use App\Models\CompanyWithdraw;
use Filament\Resources\Pages\CreateRecord;

class CreateCompanyWithdraw extends CreateRecord
{
    protected static string $resource = CompanyWithdrawResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['status'] = CompanyWithdraw::STATUS_PENDING;

        return $data;
    }
}
