<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class PlayerRegistrationTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Daily Active Players (Last 30 Days)';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    protected function getData(): array
    {
        $data = Trend::model(Account::class)
            ->dateColumn('reset_time')
            ->between(start: now()->subDays(29), end: now())
            ->perDay()
            ->count();

        return [
            'datasets' => [[
                'label' => 'Active Players',
                'data' => $data->map(fn (TrendValue $v) => $v->aggregate)->toArray(),
                'borderColor' => '#8b5cf6',
                'backgroundColor' => 'rgba(139,92,246,0.15)',
                'fill' => true,
                'tension' => 0.4,
            ]],
            'labels' => $data->map(fn (TrendValue $v) => $v->date)->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
