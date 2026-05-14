<?php

namespace App\Filament\Resources\CompanyWithdraws\Pages;

use App\Filament\Resources\CompanyWithdraws\CompanyWithdrawResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCompanyWithdraw extends EditRecord
{
    protected static string $resource = CompanyWithdrawResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
