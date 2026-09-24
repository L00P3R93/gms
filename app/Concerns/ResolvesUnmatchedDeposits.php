<?php

namespace App\Concerns;

use App\Exceptions\GameApiException;
use App\Filament\Pages\DepositsPage;
use App\Filament\Pages\ReferralWithdrawalsPage;
use App\Services\GameApiService;
use App\Support\ApiAuditLog;
use App\Support\DepositSuggestion;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Assign and Record refund on the unmatched-deposit queue.
 *
 * Assign credits the deposit to a customer's wallet through KadiApi; Record
 * refund only records a reversal already made on the Kizuka M-Pesa portal.
 * Neither can be undone from the GMS, so both demand a note and show exactly
 * what will happen before submitting.
 *
 * The Idempotency-Key is generated when the modal opens, so a double click or a
 * retry after a rate limit or outage replays the same attempt instead of acting
 * twice. A 422 gets a fresh key, because the corrected form is a new request.
 * Every attempt, successful or not, is written to the audit log.
 */
trait ResolvesUnmatchedDeposits
{
    public const RESOLVE_NOTE_MAX_LENGTH = 255;

    public const REFUND_WARNING = 'Reverse the payment on the Kizuka (4007279) M-Pesa portal first. This only records it; no money is sent.';

    public const OTHER_CUSTOMER = 'other';

    /**
     * Refresh whatever shows the queue after an attempt changed a deposit, or found it resolved or gone.
     */
    abstract protected function afterDepositResolved(): void;

    public static function canAssignDeposits(): bool
    {
        return auth()->user()?->hasPermissionTo('deposits.assign') ?? false;
    }

    public static function canRefundDeposits(): bool
    {
        return auth()->user()?->hasPermissionTo('deposits.refund') ?? false;
    }

    protected function assignDepositAction(): Action
    {
        return Action::make('assign')
            ->label('Assign')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->visible(fn (array $record): bool => static::canAssignDeposits() && ($record['status'] ?? 'unmatched') === 'unmatched')
            ->modalHeading(fn (array $record): string => 'Assign '.Format::money($record['amount'] ?? 0).' to a customer')
            ->modalDescription(fn (array $record): string => 'Bill ref '.($record['bill_ref_no'] ?? '—').' · paid by '.($record['name'] ?? 'unknown').' · M-Pesa '.($record['trans_id'] ?? '—'))
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Credit wallet')
            ->fillForm(fn (array $record): array => [
                // Generated here, not as a default: fillForm() replaces field defaults.
                'idempotency_key' => Str::uuid()->toString(),
                'suggested_customer_id' => DepositSuggestion::top($record) === null ? self::OTHER_CUSTOMER : null,
            ])
            ->schema(fn (array $record): array => [
                Hidden::make('idempotency_key'),
                Radio::make('suggested_customer_id')
                    ->label('Who paid?')
                    ->options(fn (): array => static::suggestionOptions($record))
                    ->descriptions(fn (): array => static::suggestionDescriptions($record))
                    ->required()
                    ->live(),
                Select::make('searched_customer_id')
                    ->label('Customer')
                    ->placeholder('Search by name, account or phone')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => DepositsPage::searchCustomers($search))
                    ->getOptionLabelUsing(fn ($value): string => DepositsPage::customerLabel((int) $value))
                    ->visible(fn (Get $get): bool => $get('suggested_customer_id') === self::OTHER_CUSTOMER)
                    ->required(fn (Get $get): bool => $get('suggested_customer_id') === self::OTHER_CUSTOMER)
                    ->live(),
                TextEntry::make('customer_preview')
                    ->label('Customer to credit')
                    ->state(fn (Get $get): array => static::customerPreview(static::chosenCustomerId($get('suggested_customer_id'), $get('searched_customer_id'))))
                    ->listWithLineBreaks()
                    ->visible(fn (Get $get): bool => static::chosenCustomerId($get('suggested_customer_id'), $get('searched_customer_id')) !== null),
                TextEntry::make('assign_confirmation')
                    ->hiddenLabel()
                    ->state(fn (Get $get): ?string => static::assignConfirmation($record, static::chosenCustomerId($get('suggested_customer_id'), $get('searched_customer_id'))))
                    ->color('warning')
                    ->weight('bold')
                    ->visible(fn (Get $get): bool => static::chosenCustomerId($get('suggested_customer_id'), $get('searched_customer_id')) !== null),
                Checkbox::make('confirm_shared_number')
                    ->label('I opened every customer who shares this number and confirmed who paid.')
                    ->helperText(fn (): HtmlString => static::sharedNumberLinks($record))
                    ->visible(fn (Get $get): bool => static::isSharedNumber($record, static::chosenCustomerId($get('suggested_customer_id'), $get('searched_customer_id'))))
                    ->accepted()
                    ->validationMessages(['accepted' => 'This number belongs to more than one customer. Open each of them and confirm before assigning.']),
                Textarea::make('note')
                    ->label('Why this customer?')
                    ->helperText('Saved on the deposit with your name.')
                    ->required()
                    ->minLength(3)
                    ->maxLength(fn (): int => self::RESOLVE_NOTE_MAX_LENGTH - mb_strlen($this->resolveNoteSignature()))
                    ->rows(3),
            ])
            ->action(function (array $data, array $record, Action $action): void {
                abort_unless(static::canAssignDeposits(), 403);

                $depositId = (int) $record['id'];
                $customerId = static::chosenCustomerId($data['suggested_customer_id'] ?? null, $data['searched_customer_id'] ?? null);

                if ($customerId === null) {
                    throw ValidationException::withMessages(["mountedActions.{$action->getNestingIndex()}.data.suggested_customer_id" => 'Choose the customer who paid.']);
                }

                $payload = ['customer_id' => $customerId, 'note' => trim($data['note']).$this->resolveNoteSignature()];

                try {
                    $deposit = app(GameApiService::class)->assignDeposit($depositId, $customerId, $payload['note'], $data['idempotency_key']);
                } catch (GameApiException $e) {
                    ApiAuditLog::record('Deposit', $depositId, 'assign_failed', [...$payload, 'idempotency_key' => $data['idempotency_key']], $e->statusCode, static::errorResponse($e));

                    $this->handleResolveFailure($e, $action, 'assign', [
                        'customer_id' => ($data['suggested_customer_id'] ?? null) === self::OTHER_CUSTOMER ? 'searched_customer_id' : 'suggested_customer_id',
                        'note' => 'note',
                    ]);

                    return;
                }

                ApiAuditLog::record('Deposit', $depositId, 'assigned', [...$payload, 'idempotency_key' => $data['idempotency_key']], 200, $deposit);

                Notification::make()
                    ->title('Deposit assigned')
                    ->body(Format::money($record['amount'] ?? 0).' credited to '.static::customerName($customerId).'.')
                    ->success()
                    ->send();

                $this->afterDepositResolved();
            });
    }

    protected function refundDepositAction(): Action
    {
        return Action::make('refund')
            ->label('Record refund')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (array $record): bool => static::canRefundDeposits() && ($record['status'] ?? 'unmatched') === 'unmatched')
            ->modalHeading(fn (array $record): string => 'Record refund of '.Format::money($record['amount'] ?? 0))
            ->modalDescription(self::REFUND_WARNING)
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('warning')
            ->modalSubmitActionLabel('Record refund')
            ->fillForm(fn (): array => ['idempotency_key' => Str::uuid()->toString()])
            ->schema([
                Hidden::make('idempotency_key'),
                TextInput::make('mpesa_reference')
                    ->label('M-Pesa reversal reference')
                    ->placeholder('RKA1B2C3D4')
                    ->helperText('The receipt of the reversal on the Kizuka portal.')
                    ->required()
                    ->regex('/^\s*[A-Za-z0-9]{6,30}\s*$/')
                    ->validationMessages(['regex' => 'The reference must be 6 to 30 letters or digits.'])
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper(trim($state))),
                Textarea::make('note')
                    ->label('Why was it refunded?')
                    ->helperText('Saved on the deposit with your name.')
                    ->required()
                    ->minLength(3)
                    ->maxLength(fn (): int => self::RESOLVE_NOTE_MAX_LENGTH - mb_strlen($this->resolveNoteSignature()))
                    ->rows(3),
            ])
            ->action(function (array $data, array $record, Action $action): void {
                abort_unless(static::canRefundDeposits(), 403);

                $depositId = (int) $record['id'];
                $payload = ['mpesa_reference' => $data['mpesa_reference'], 'note' => trim($data['note']).$this->resolveNoteSignature()];

                try {
                    $deposit = app(GameApiService::class)->refundDeposit($depositId, $payload['mpesa_reference'], $payload['note'], $data['idempotency_key']);
                } catch (GameApiException $e) {
                    ApiAuditLog::record('Deposit', $depositId, 'refund_failed', [...$payload, 'idempotency_key' => $data['idempotency_key']], $e->statusCode, static::errorResponse($e));

                    $this->handleResolveFailure($e, $action, 'refund', ['mpesa_reference' => 'mpesa_reference', 'note' => 'note']);

                    return;
                }

                ApiAuditLog::record('Deposit', $depositId, 'refunded', [...$payload, 'idempotency_key' => $data['idempotency_key']], 200, $deposit);

                Notification::make()
                    ->title('Refund recorded')
                    ->body('Reversal '.$payload['mpesa_reference'].' recorded for '.Format::money($record['amount'] ?? 0).'.')
                    ->success()
                    ->send();

                $this->afterDepositResolved();
            });
    }

    /**
     * KadiApi records only the API key as `resolved_by`, so the acting admin is kept in the note.
     */
    protected function resolveNoteSignature(): string
    {
        $name = trim((string) (auth()->user()?->name ?? ''));

        return $name === '' ? '' : ' — by '.$name;
    }

    /**
     * 422 errors land on the form fields. A 409 (already resolved) and a 404 close the modal
     * and refresh, except a refund 409 for a reference used on another refund, which stays
     * on the reference field. Rate limits, outages and server errors keep the modal open so
     * a retry reuses the same Idempotency-Key.
     *
     * @param  'assign'|'refund'  $kind
     * @param  array<string, string>  $fields  KadiApi field => form field
     *
     * @throws ValidationException
     */
    protected function handleResolveFailure(GameApiException $e, Action $action, string $kind, array $fields): void
    {
        $message = $e->apiMessage !== '' ? $e->apiMessage : $e->getMessage();
        $index = $action->getNestingIndex();
        $verb = $kind === 'assign' ? 'assign' : 'record the refund for';

        $isTakenReference = $kind === 'refund' && $e->statusCode === 409 && static::isReferenceTaken($e, $message);

        if ($e->statusCode === 422 || $isTakenReference) {
            // The corrected form is a new request, so it must not replay this one's key.
            $this->mountedActions[$index]['data']['idempotency_key'] = Str::uuid()->toString();

            $fieldErrors = $isTakenReference
                ? ['mpesa_reference' => $e->errors['mpesa_reference'] ?? $message]
                : Arr::only($e->errors, array_keys($fields));

            if ($fieldErrors !== []) {
                throw ValidationException::withMessages(collect($fieldErrors)
                    ->mapWithKeys(fn (array|string $messages, string $field): array => [
                        "mountedActions.{$index}.data.{$fields[$field]}" => Arr::wrap($messages),
                    ])
                    ->all());
            }

            Notification::make()->title("Could not {$verb} the deposit")->body($message)->danger()->send();

            $action->halt();
        }

        if (in_array($e->statusCode, [404, 409], true)) {
            Notification::make()
                ->title(match (true) {
                    $e->statusCode === 409 => 'Deposit already resolved',
                    $kind === 'assign' => 'Deposit or customer not found',
                    default => 'Deposit not found',
                })
                ->body($message)
                ->warning()
                ->send();

            $this->afterDepositResolved();

            return;
        }

        Notification::make()->title("Could not {$verb} the deposit")->body($message)->danger()->send();

        if ($e->statusCode === 0 || $e->statusCode === 429 || $e->statusCode >= 500) {
            $action->halt();
        }
    }

    /**
     * KadiApi answers a refund with 409 both when the deposit is already resolved and when
     * the reference is on another refund, with no `errors` or `code` today. A `code` or an
     * `errors.mpesa_reference` entry wins when KadiApi sends one; until then the message
     * decides ("That M-Pesa reference is already on another refund").
     */
    protected static function isReferenceTaken(GameApiException $e, string $message): bool
    {
        return match (true) {
            $e->errorCode === 'reference_used', isset($e->errors['mpesa_reference']) => true,
            $e->errorCode !== null => false,
            default => Str::contains(Str::lower($message), 'reference'),
        };
    }

    /**
     * @return array{message: string, errors: array<string, mixed>, code?: string}
     */
    protected static function errorResponse(GameApiException $e): array
    {
        return array_filter([
            'message' => $e->apiMessage !== '' ? $e->apiMessage : $e->getMessage(),
            'errors' => $e->errors,
            'code' => $e->errorCode,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * KadiApi's suggestions as radio options, strongest first, plus a search for anyone else.
     *
     * @param  array<string, mixed>  $deposit
     * @return array<int|string, string>
     */
    protected static function suggestionOptions(array $deposit): array
    {
        return collect($deposit['suggestions'] ?? [])
            ->filter(fn ($suggestion): bool => is_array($suggestion) && isset($suggestion['customer_id']))
            ->mapWithKeys(fn (array $suggestion): array => [(int) $suggestion['customer_id'] => DepositSuggestion::customerLabel($suggestion)])
            ->put(self::OTHER_CUSTOMER, 'Someone else — search customers')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $deposit
     * @return array<int|string, string>
     */
    protected static function suggestionDescriptions(array $deposit): array
    {
        return collect($deposit['suggestions'] ?? [])
            ->filter(fn ($suggestion): bool => is_array($suggestion) && isset($suggestion['customer_id']))
            ->mapWithKeys(fn (array $suggestion): array => [(int) $suggestion['customer_id'] => collect(DepositSuggestion::badges($suggestion))->pluck('label')
                ->prepend($suggestion['phone_no'] ?? null)
                ->filter()
                ->implode(' · ')])
            ->all();
    }

    protected static function chosenCustomerId(mixed $suggested, mixed $searched): ?int
    {
        $id = $suggested === self::OTHER_CUSTOMER ? $searched : $suggested;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * Whether the chosen customer's number is shared with another customer.
     *
     * @param  array<string, mixed>  $deposit
     */
    protected static function isSharedNumber(array $deposit, ?int $customerId): bool
    {
        return $customerId !== null && collect($deposit['suggestions'] ?? [])
            ->contains(fn ($suggestion): bool => is_array($suggestion) && (int) ($suggestion['customer_id'] ?? 0) === $customerId && ($suggestion['ambiguous'] ?? false) === true);
    }

    /**
     * Links to every customer flagged as sharing the number, to open before assigning.
     *
     * @param  array<string, mixed>  $deposit
     */
    protected static function sharedNumberLinks(array $deposit): HtmlString
    {
        $links = collect($deposit['suggestions'] ?? [])
            ->filter(fn ($suggestion): bool => is_array($suggestion) && ($suggestion['ambiguous'] ?? false) === true)
            ->map(function (array $suggestion): string {
                $label = e(DepositSuggestion::customerLabel($suggestion));
                $url = ReferralWithdrawalsPage::customerUrl($suggestion['customer_id'] ?? null);

                return $url === null ? $label : '<a href="'.e($url).'" target="_blank" class="underline">'.$label.'</a>';
            })
            ->implode(', ');

        return new HtmlString('Open each before assigning: '.$links);
    }

    /**
     * The chosen customer's name, account, masked phone and join date, so the admin can check
     * who will be credited. Cached briefly because the form re-renders on every change.
     *
     * @return list<string>
     */
    public static function customerPreview(?int $customerId): array
    {
        if ($customerId === null) {
            return [];
        }

        $customer = static::lookupCustomer($customerId);

        if ($customer === null) {
            return ["Customer #{$customerId} could not be loaded. Check the ID before assigning."];
        }

        return array_values(array_filter([
            $customer['name'] ?? 'Customer #'.$customerId,
            'Account '.($customer['account_no'] ?? '—'),
            'Phone '.Format::maskedPhone($customer['phone_no'] ?? null),
            'Joined '.Format::date($customer['created_at'] ?? null),
        ]));
    }

    protected static function customerName(int $customerId): string
    {
        return static::lookupCustomer($customerId)['name'] ?? 'customer #'.$customerId;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function lookupCustomer(int $customerId): ?array
    {
        $key = "deposits.assign.customer.{$customerId}";

        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $customer = app(GameApiService::class)->getCustomer($customerId);
        } catch (GameApiException) {
            return null;
        }

        if ($customer === []) {
            return null;
        }

        Cache::put($key, $customer, now()->addMinutes(5));

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $deposit
     */
    protected static function assignConfirmation(array $deposit, ?int $customerId): ?string
    {
        if ($customerId === null) {
            return null;
        }

        return Format::money($deposit['amount'] ?? 0).' will be credited to '.static::customerName($customerId)."'s wallet. 5% excise duty applies. This cannot be undone from the GMS.";
    }
}
