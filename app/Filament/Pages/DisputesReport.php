<?php

namespace App\Filament\Pages;

use App\Support\Format;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The finance view of player complaints: money taken into dispute escrow, what
 * is still held and how closed complaints were settled. Rows open the complaint.
 */
class DisputesReport extends FinanceListReportPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Disputes';

    protected static ?int $navigationSort = 26;

    protected ?string $heading = 'Disputes';

    protected function reportKey(): string
    {
        return 'disputes';
    }

    protected function exportKey(): ?string
    {
        return 'disputes';
    }

    protected function extraFilters(): array
    {
        return [
            'status' => $this->filterValue('status'),
            'type' => $this->filterValue('type'),
        ];
    }

    protected function summaryStats(array $data): array
    {
        $byStatus = $data['summary']['by_status'] ?? [];
        $pending = $byStatus['pending_dispute'] ?? [];
        $resolved = $byStatus['resolved'] ?? [];
        $released = collect(['rejected', 'cancelled'])->sum(fn (string $status): float => (float) ($byStatus[$status]['released'] ?? 0));

        return [
            ['label' => 'Held Right Now', 'value' => Format::money($data['summary']['currently_held'] ?? 0), 'description' => 'In dispute escrow across open complaints', 'icon' => 'heroicon-m-lock-closed', 'color' => 'warning'],
            ['label' => 'Pending', 'value' => number_format((int) ($pending['complaints'] ?? 0)), 'description' => Format::money($pending['disputed'] ?? 0).' disputed', 'icon' => 'heroicon-m-scale', 'color' => 'warning'],
            ['label' => 'Refunded', 'value' => Format::money($resolved['refunded'] ?? 0), 'description' => 'House cuts reversed '.Format::money($resolved['house_cuts_reversed'] ?? 0), 'icon' => 'heroicon-m-arrow-uturn-left', 'color' => 'danger'],
            ['label' => 'Released to Winners', 'value' => Format::money($released), 'description' => 'From rejected and cancelled complaints', 'icon' => 'heroicon-m-check-circle', 'color' => 'success'],
        ];
    }

    protected function blocks(array $data): array
    {
        return [
            [
                'title' => 'By status',
                'headers' => ['Status', 'Complaints', 'Disputed', 'Held', 'Shortfall', 'Refunded', 'House cuts reversed', 'Released'],
                'rows' => collect($data['summary']['by_status'] ?? [])
                    ->filter(fn ($row): bool => is_array($row))
                    ->map(fn (array $row, string $status): array => [
                        ComplaintsPage::STATUSES[$status] ?? ucfirst($status),
                        number_format((int) ($row['complaints'] ?? 0)),
                        Format::money($row['disputed'] ?? 0),
                        Format::money($row['held'] ?? 0),
                        Format::money($row['shortfall'] ?? 0),
                        Format::money($row['refunded'] ?? 0),
                        Format::money($row['house_cuts_reversed'] ?? 0),
                        Format::money($row['released'] ?? 0),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->recordUrl(fn (array $record): ?string => isset($record['id']) && ComplaintDetailPage::canAccess()
                ? ComplaintDetailPage::getUrl(['complaint' => $record['id']])
                : null);
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('filed_at')
                ->label('Filed')
                ->formatStateUsing(fn ($state): string => Format::dateTime($state)),
            TextColumn::make('complaint_id')
                ->label('Complaint')
                ->fontFamily('mono')
                ->formatStateUsing(fn (?string $state): string => substr((string) $state, 0, 8)),
            TextColumn::make('subject_type')
                ->label('Subject')
                ->badge()
                ->color(fn (?string $state): string => ComplaintsPage::subjectColor($state))
                ->formatStateUsing(fn (?string $state): string => ComplaintsPage::SUBJECT_TYPES[$state] ?? ucfirst((string) $state)),
            TextColumn::make('customer_id')
                ->label('Complainant')
                ->formatStateUsing(fn ($state): string => '#'.$state),
            TextColumn::make('status')
                ->badge()
                ->color(fn (?string $state): string => ComplaintsPage::statusColor($state))
                ->formatStateUsing(fn (?string $state): string => ComplaintsPage::STATUSES[$state] ?? ucfirst((string) $state)),
            TextColumn::make('disputed_amount')
                ->label('Disputed')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('still_held')
                ->label('Still Held')
                ->alignEnd()
                ->weight('bold')
                ->color(fn ($state): ?string => (float) $state > 0 ? 'warning' : null)
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('refunded_amount')
                ->label('Refunded')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('released_amount')
                ->label('Released')
                ->alignEnd()
                ->formatStateUsing(fn ($state): string => Format::money($state)),
            TextColumn::make('closed_at')
                ->label('Closed')
                ->formatStateUsing(fn ($state): string => Format::dateTime($state))
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function tableFilterDefinitions(): array
    {
        return [
            SelectFilter::make('status')->options(ComplaintsPage::STATUSES),
            SelectFilter::make('type')->label('Subject')->options(ComplaintsPage::SUBJECT_TYPES),
        ];
    }

    protected function emptyHeading(): string
    {
        return 'No disputes in this period';
    }
}
