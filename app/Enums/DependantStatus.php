<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum DependantStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            DependantStatus::Active => 'Active',
            DependantStatus::Inactive => 'Inactive',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            DependantStatus::Active => Heroicon::OutlinedCheckCircle,
            DependantStatus::Inactive => Heroicon::OutlinedPauseCircle,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            DependantStatus::Active => 'success',
            DependantStatus::Inactive => 'gray',
        };
    }
}
