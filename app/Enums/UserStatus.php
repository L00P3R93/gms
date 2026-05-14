<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum UserStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            UserStatus::Active => 'Active',
            UserStatus::Blocked => 'Blocked',
            UserStatus::Suspended => 'Suspended',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            UserStatus::Active => Heroicon::OutlinedCheckCircle,
            UserStatus::Blocked => Heroicon::OutlinedXCircle,
            UserStatus::Suspended => Heroicon::OutlinedExclamationCircle,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            UserStatus::Active => 'success',
            UserStatus::Blocked => 'danger',
            UserStatus::Suspended => 'warning',
        };
    }
}
