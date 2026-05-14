<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum WithdrawStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Failed = 'failed';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            WithdrawStatus::Pending => 'Pending',
            WithdrawStatus::Processing => 'Processing',
            WithdrawStatus::Failed => 'Failed',
            WithdrawStatus::Completed => 'Completed',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            WithdrawStatus::Pending => Heroicon::OutlinedClock,
            WithdrawStatus::Processing => Heroicon::OutlinedArrowPath,
            WithdrawStatus::Failed => Heroicon::OutlinedXCircle,
            WithdrawStatus::Completed => Heroicon::OutlinedCheckCircle,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            WithdrawStatus::Pending => 'warning',
            WithdrawStatus::Processing => 'info',
            WithdrawStatus::Failed => 'danger',
            WithdrawStatus::Completed => 'success',
        };
    }
}
