<?php

namespace App\Filament\Pages;

use App\Support\Format;
use App\Traits\SuperAdminAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Shared base for the Reports group. Owns one filter dimension — the report
 * period — exposed through a single Header Filter Action and persisted in the
 * URL query string so reports stay shareable. Subclasses read the resolved
 * range via {@see dateRange()}.
 */
abstract class BaseReportPage extends Page
{
    use SuperAdminAccess;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    #[Url]
    public string $period = 'this_month';

    #[Url]
    public ?string $customStart = null;

    #[Url]
    public ?string $customEnd = null;

    public bool $apiError = false;

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    /**
     * @return array<string, string>
     */
    public function periodOptions(): array
    {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This Week',
            'last_week' => 'Last Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_year' => 'This Year',
            'all_time' => 'All Time',
            'custom' => 'Custom Range',
        ];
    }

    /**
     * Resolve the active period to a concrete date range. `all_time` returns
     * nulls so callers can decide their own open-ended bounds.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function dateRange(): array
    {
        return match ($this->period) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'yesterday' => [today()->subDay()->toDateString(), today()->subDay()->toDateString()],
            'this_week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'last_week' => [now()->subWeek()->startOfWeek()->toDateString(), now()->subWeek()->endOfWeek()->toDateString()],
            'this_month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'last_month' => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
            'this_year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'all_time' => [null, null],
            'custom' => [$this->customStart ?? now()->startOfMonth()->toDateString(), $this->customEnd ?? now()->toDateString()],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    /**
     * Human label for the active period, shown on the filter action button.
     */
    public function periodLabel(): string
    {
        if ($this->period === 'custom') {
            [$start, $end] = $this->dateRange();

            return Format::date($start).' – '.Format::date($end);
        }

        return $this->periodOptions()[$this->period] ?? 'This Month';
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('filterPeriod')
                ->label(fn (): string => $this->periodLabel())
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->modalHeading('Report period')
                ->modalDescription('Choose the date range this report covers.')
                ->modalWidth(Width::Medium)
                ->modalSubmitActionLabel('Apply')
                ->fillForm(fn (): array => [
                    'period' => $this->period,
                    'customStart' => $this->customStart,
                    'customEnd' => $this->customEnd,
                ])
                ->schema([
                    Select::make('period')
                        ->label('Period')
                        ->options($this->periodOptions())
                        ->live()
                        ->required(),
                    DatePicker::make('customStart')
                        ->label('From')
                        ->native(false)
                        ->maxDate(now())
                        ->visible(fn ($get): bool => $get('period') === 'custom')
                        ->required(fn ($get): bool => $get('period') === 'custom'),
                    DatePicker::make('customEnd')
                        ->label('Until')
                        ->native(false)
                        ->maxDate(now())
                        ->afterOrEqual('customStart')
                        ->visible(fn ($get): bool => $get('period') === 'custom')
                        ->required(fn ($get): bool => $get('period') === 'custom'),
                ])
                ->action(function (array $data): void {
                    $this->period = $data['period'];
                    $this->customStart = $data['period'] === 'custom' ? ($data['customStart'] ?? null) : null;
                    $this->customEnd = $data['period'] === 'custom' ? ($data['customEnd'] ?? null) : null;
                    $this->onFilterApplied();
                }),
        ];
    }

    /**
     * Hook fired after the period filter changes. Table-backed reports override
     * this to reset pagination.
     */
    protected function onFilterApplied(): void {}
}
