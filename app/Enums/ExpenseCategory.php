<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ExpenseCategory: string implements HasColor, HasIcon, HasLabel
{
    case Income = 'income';
    case Expense = 'expense';

    public function getLabel(): string
    {
        return match ($this) {
            ExpenseCategory::Income => 'Income',
            ExpenseCategory::Expense => 'Expense',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            ExpenseCategory::Income => Heroicon::OutlinedArrowTrendingUp,
            ExpenseCategory::Expense => Heroicon::OutlinedArrowTrendingDown,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            ExpenseCategory::Income => 'success',
            ExpenseCategory::Expense => 'danger',
        };
    }
}
