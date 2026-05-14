<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum CompanyWithdrawStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Failed = 'failed';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            CompanyWithdrawStatus::Pending => 'Pending',
            CompanyWithdrawStatus::Processing => 'Processing',
            CompanyWithdrawStatus::Failed => 'Failed',
            CompanyWithdrawStatus::Completed => 'Completed',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            CompanyWithdrawStatus::Pending => Heroicon::OutlinedClock,
            CompanyWithdrawStatus::Processing => Heroicon::OutlinedArrowPath,
            CompanyWithdrawStatus::Failed => Heroicon::OutlinedXCircle,
            CompanyWithdrawStatus::Completed => Heroicon::OutlinedCheckCircle,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            CompanyWithdrawStatus::Pending => 'warning',
            CompanyWithdrawStatus::Processing => 'info',
            CompanyWithdrawStatus::Failed => 'danger',
            CompanyWithdrawStatus::Completed => 'success',
        };
    }
}
