<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum HolderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            HolderStatus::Active => 'Active',
            HolderStatus::Inactive => 'Inactive',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            HolderStatus::Active => Heroicon::OutlinedCheckCircle,
            HolderStatus::Inactive => Heroicon::OutlinedPauseCircle,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            HolderStatus::Active => 'success',
            HolderStatus::Inactive => 'gray',
        };
    }
}
