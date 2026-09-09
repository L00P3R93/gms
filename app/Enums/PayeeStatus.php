<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PayeeStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            PayeeStatus::Active => 'Active',
            PayeeStatus::Inactive => 'Inactive',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            PayeeStatus::Active => Heroicon::OutlinedCheckCircle,
            PayeeStatus::Inactive => Heroicon::OutlinedPauseCircle,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            PayeeStatus::Active => 'success',
            PayeeStatus::Inactive => 'gray',
        };
    }
}
