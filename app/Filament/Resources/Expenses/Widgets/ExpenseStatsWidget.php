<?php

namespace App\Filament\Resources\Expenses\Widgets;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpenseStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalIncome = Expense::where('category', ExpenseCategory::Income->value)->sum('amount');
        $totalExpenses = Expense::where('category', ExpenseCategory::Expense->value)->sum('amount');

        $incomeLast7 = collect(range(6, 0))
            ->map(fn ($d) => (int) Expense::where('category', ExpenseCategory::Income->value)->whereDate('created_at', now()->subDays($d))->sum('amount'))
            ->toArray();

        $expenseLast7 = collect(range(6, 0))
            ->map(fn ($d) => (int) Expense::where('category', ExpenseCategory::Expense->value)->whereDate('created_at', now()->subDays($d))->sum('amount'))
            ->toArray();

        return [
            Stat::make('Total Income', 'KES '.number_format($totalIncome, 2))
                ->color('success')
                ->chart($incomeLast7)
                ->descriptionIcon('heroicon-m-arrow-trending-up'),
            Stat::make('Total Expenses', 'KES '.number_format($totalExpenses, 2))
                ->color('danger')
                ->chart($expenseLast7)
                ->descriptionIcon('heroicon-m-arrow-trending-down'),
            Stat::make('Net Balance', 'KES '.number_format($totalIncome - $totalExpenses, 2))
                ->color($totalIncome >= $totalExpenses ? 'success' : 'danger')
                ->descriptionIcon($totalIncome >= $totalExpenses ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'),
        ];
    }
}
