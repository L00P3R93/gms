<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PayoutStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            PayoutStatus::Pending => 'Pending',
            PayoutStatus::Approved => 'Approved',
            PayoutStatus::Declined => 'Declined',
            PayoutStatus::Processing => 'Processing',
            PayoutStatus::Completed => 'Completed',
            PayoutStatus::Failed => 'Failed',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            PayoutStatus::Pending => Heroicon::OutlinedClock,
            PayoutStatus::Approved => Heroicon::OutlinedCheckBadge,
            PayoutStatus::Declined => Heroicon::OutlinedXCircle,
            PayoutStatus::Processing => Heroicon::OutlinedArrowPath,
            PayoutStatus::Completed => Heroicon::OutlinedCheckCircle,
            PayoutStatus::Failed => Heroicon::OutlinedExclamationCircle,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            PayoutStatus::Pending => 'warning',
            PayoutStatus::Approved => 'info',
            PayoutStatus::Declined => 'gray',
            PayoutStatus::Processing => 'primary',
            PayoutStatus::Completed => 'success',
            PayoutStatus::Failed => 'danger',
        };
    }
}
