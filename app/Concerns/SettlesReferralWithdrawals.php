<?php

namespace App\Concerns;

use App\Exceptions\GameApiException;
use App\Services\GameApiService;
use App\Support\ApiAuditLog;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The Settle action shared by the referral withdrawals list and the withdrawal
 * detail page. An admin who has checked the 4151665 M-Pesa statement records
 * whether a stuck payout was actually paid (`completed`, with its receipt) or
 * not (`failed`, which refunds the customer's referral wallet).
 *
 * The Idempotency-Key is generated when the modal opens, so a double click or a
 * retry after a rate limit or outage replays the same attempt instead of
 * settling twice. A 422 gets a fresh key, because the corrected form is a new
 * request. Every attempt, successful or not, is written to the audit log.
 */
trait SettlesReferralWithdrawals
{
    public const SETTLE_NOTE_MAX_LENGTH = 255;

    public const SETTLE_WARNING = "Check the payout on the Kadi Kings (4151665) M-Pesa org portal first. 'Failed' puts the money back in the customer's referral wallet; if M-Pesa actually paid it, the customer is paid twice.";

    /**
     * Statuses that can still be settled by hand.
     *
     * @var list<string>
     */
    public const SETTLEABLE_STATUSES = ['pending', 'processing'];

    /**
     * Refresh whatever shows the withdrawal after a settle attempt changed it, or found it
     * already settled or gone. Receives the settled withdrawal when the API returned one.
     *
     * @param  array<string, mixed>  $withdrawal
     */
    abstract protected function afterReferralWithdrawalSettled(array $withdrawal = []): void;

    /**
     * The withdrawal the action acts on: the table row on the list, or the page's own withdrawal.
     *
     * @param  array<string, mixed>|null  $record
     * @return array<string, mixed>
     */
    protected function withdrawalForAction(?array $record): array
    {
        return $record ?? [];
    }

    public static function canSettleReferralWithdrawals(): bool
    {
        return auth()->user()?->hasPermissionTo('referral-withdrawals.settle') ?? false;
    }

    protected function settleReferralWithdrawalAction(): Action
    {
        return Action::make('settle')
            ->label('Settle')
            ->icon('heroicon-o-check-badge')
            ->color('warning')
            ->visible(fn (?array $record): bool => static::canSettleReferralWithdrawals()
                && in_array($this->withdrawalForAction($record)['status'] ?? null, self::SETTLEABLE_STATUSES, true))
            ->modalHeading(fn (?array $record): string => 'Settle withdrawal of '.Format::money($this->withdrawalForAction($record)['amount'] ?? 0))
            ->modalDescription(self::SETTLE_WARNING)
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalSubmitActionLabel('Settle withdrawal')
            ->schema([
                Hidden::make('idempotency_key')
                    ->default(fn (): string => Str::uuid()->toString()),
                Radio::make('outcome')
                    ->label('What happened on M-Pesa?')
                    ->options([
                        'completed' => 'Completed — the payout is on the statement',
                        'failed' => 'Failed — not on the statement; refund the referral wallet',
                    ])
                    ->required()
                    ->live(),
                TextInput::make('mpesa_receipt')
                    ->label('M-Pesa receipt')
                    ->placeholder('RKA1B2C3D4')
                    ->visible(fn (Get $get): bool => $get('outcome') === 'completed')
                    ->required(fn (Get $get): bool => $get('outcome') === 'completed')
                    ->regex('/^[A-Za-z0-9]{6,30}$/')
                    ->validationMessages(['regex' => 'The receipt must be 6 to 30 letters or digits.'])
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper(trim($state))),
                Textarea::make('note')
                    ->label('Settlement note')
                    ->helperText('Where you checked and what you found. Saved on the withdrawal.')
                    ->required()
                    ->minLength(3)
                    ->maxLength(fn (): int => self::SETTLE_NOTE_MAX_LENGTH - mb_strlen($this->settleNoteSignature()))
                    ->rows(3),
                Checkbox::make('confirm_failed')
                    ->label('I checked the 4151665 statement and this payout is not on it. Refund the customer\'s referral wallet.')
                    ->visible(fn (Get $get): bool => $get('outcome') === 'failed')
                    ->accepted()
                    ->validationMessages(['accepted' => 'Confirm you checked the statement before marking the payout failed.']),
            ])
            ->action(function (array $data, ?array $record, Action $action): void {
                $withdrawal = $this->withdrawalForAction($record);
                $withdrawalId = (int) $withdrawal['id'];

                $payload = [
                    'outcome' => $data['outcome'],
                    'mpesa_receipt' => $data['outcome'] === 'completed' ? ($data['mpesa_receipt'] ?? null) : null,
                    'note' => trim($data['note']).$this->settleNoteSignature(),
                ];

                try {
                    $settled = app(GameApiService::class)->settleReferralWithdrawal(
                        $withdrawalId,
                        $payload['outcome'],
                        $payload['mpesa_receipt'],
                        $payload['note'],
                        $data['idempotency_key'],
                    );
                } catch (GameApiException $e) {
                    ApiAuditLog::record('ReferralWithdrawal', $withdrawalId, 'settle_failed', [...$payload, 'idempotency_key' => $data['idempotency_key']], $e->statusCode, [
                        'message' => $e->apiMessage !== '' ? $e->apiMessage : $e->getMessage(),
                        'errors' => $e->errors,
                    ]);

                    $this->handleSettleFailure($e, $action);

                    return;
                }

                ApiAuditLog::record('ReferralWithdrawal', $withdrawalId, 'settled', [...$payload, 'idempotency_key' => $data['idempotency_key']], 200, $settled);

                Notification::make()
                    ->title($payload['outcome'] === 'completed' ? 'Withdrawal marked completed' : 'Withdrawal marked failed')
                    ->body($payload['outcome'] === 'completed'
                        ? 'Receipt '.$payload['mpesa_receipt'].' recorded.'
                        : Format::money($settled['amount'] ?? $withdrawal['amount'] ?? 0).' returned to the customer\'s referral wallet.')
                    ->success()
                    ->send();

                $this->afterReferralWithdrawalSettled($settled);
            });
    }

    /**
     * KadiApi records the API key as `settled_by`, so the acting admin is kept in the note.
     */
    protected function settleNoteSignature(): string
    {
        $name = trim((string) (auth()->user()?->name ?? ''));

        return $name === '' ? '' : ' — by '.$name;
    }

    /**
     * 422 errors land on the form fields. A 409 (already settled, or the receipt belongs to
     * another withdrawal) and a 404 close the modal and refresh. Rate limits, outages and
     * server errors keep the modal open so a retry reuses the same Idempotency-Key.
     *
     * @throws ValidationException
     */
    protected function handleSettleFailure(GameApiException $e, Action $action): void
    {
        $message = $e->apiMessage !== '' ? $e->apiMessage : $e->getMessage();

        if ($e->statusCode === 422) {
            $fieldErrors = Arr::only($e->errors, ['outcome', 'mpesa_receipt', 'note']);

            // The corrected form is a new request, so it must not replay this one's key.
            $this->mountedActions[$action->getNestingIndex()]['data']['idempotency_key'] = Str::uuid()->toString();

            if ($fieldErrors !== []) {
                throw ValidationException::withMessages(collect($fieldErrors)
                    ->mapWithKeys(fn (array|string $messages, string $field): array => [
                        "mountedActions.{$action->getNestingIndex()}.data.{$field}" => Arr::wrap($messages),
                    ])
                    ->all());
            }

            Notification::make()
                ->title('Could not settle the withdrawal')
                ->body($message)
                ->danger()
                ->send();

            $action->halt();
        }

        if (in_array($e->statusCode, [404, 409], true)) {
            Notification::make()
                ->title($e->statusCode === 404 ? 'Withdrawal not found' : 'Withdrawal not settled')
                ->body($message)
                ->warning()
                ->send();

            $this->afterReferralWithdrawalSettled();

            return;
        }

        Notification::make()
            ->title('Could not settle the withdrawal')
            ->body($message)
            ->danger()
            ->send();

        if ($e->statusCode === 0 || $e->statusCode === 429 || $e->statusCode >= 500) {
            $action->halt();
        }
    }
}
