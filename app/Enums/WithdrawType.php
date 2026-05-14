<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WithdrawType: string implements HasColor, HasLabel
{
    case Holder = 'holder';
    case Dependant = 'dependant';

    public function getLabel(): string
    {
        return match ($this) {
            WithdrawType::Holder => 'Holder',
            WithdrawType::Dependant => 'Dependant',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            WithdrawType::Holder => 'info',
            WithdrawType::Dependant => 'warning',
        };
    }
}
