<?php

namespace App\Filament\Resources\Dependants\Pages;

use App\Filament\Resources\Dependants\DependantResource;
use App\Models\Holder;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDependant extends EditRecord
{
    protected static string $resource = DependantResource::class;

    private float $oldShare = 0;

    private int $oldHolderId = 0;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['share'] = $data['share'] * 100;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldShare = $this->record->share;
        $this->oldHolderId = $this->record->holder_id;
        $data['share'] = $data['share'] / 100;

        return $data;
    }

    protected function afterSave(): void
    {
        $oldHolder = Holder::find($this->oldHolderId);

        if ($oldHolder) {
            $oldHolder->share = $oldHolder->share + $this->oldShare;
            $oldHolder->save();
        }

        $newHolder = Holder::find($this->record->holder_id);

        if ($newHolder) {
            $newHolder->share = $newHolder->share - $this->record->share;
            $newHolder->save();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
