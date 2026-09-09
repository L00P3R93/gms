<?php

namespace App\Filament\Exports;

use App\Models\Payout;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class PayoutExporter extends Exporter
{
    protected static ?string $model = Payout::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('payee.name'),
            ExportColumn::make('amount'),
            ExportColumn::make('reason'),
            ExportColumn::make('status'),
            ExportColumn::make('idempotency_key'),
            ExportColumn::make('approved_by'),
            ExportColumn::make('declined_by'),
            ExportColumn::make('declined_reason'),
            ExportColumn::make('conversation_id'),
            ExportColumn::make('receipt'),
            ExportColumn::make('response'),
            ExportColumn::make('expense.id'),
            ExportColumn::make('processed_at'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payout export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
