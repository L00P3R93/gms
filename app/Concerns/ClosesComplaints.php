<?php

namespace App\Concerns;

use App\Exceptions\GameApiException;
use App\Services\GameApiService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The Resolve / Reject / Cancel actions shared by the complaints list and the
 * complaint detail page. Each close is sent to the wallet API with a note and
 * an Idempotency-Key generated when the modal opens, so retrying the same
 * attempt (after a rate limit or a dropped connection) replays rather than
 * moving money twice.
 */
trait ClosesComplaints
{
    public const NOTE_MAX_LENGTH = 255;

    /**
     * Refresh whatever shows the complaint after a close attempt changed it, or
     * found it already closed. Receives the updated complaint when the API returned one.
     *
     * @param  array<string, mixed>  $complaint
     */
    abstract protected function afterComplaintClosed(array $complaint = []): void;

    /**
     * The complaint an action acts on: the table row on the list, or the page's own complaint.
     *
     * @param  array<string, mixed>|null  $record
     * @return array<string, mixed>
     */
    protected function complaintForAction(?array $record): array
    {
        return $record ?? [];
    }

    public static function canCloseComplaints(): bool
    {
        return auth()->user()?->hasPermissionTo('complaints.close') ?? false;
    }

    /**
     * @return list<Action>
     */
    protected function closeComplaintActions(): array
    {
        return [
            $this->closeComplaintAction('resolve'),
            $this->closeComplaintAction('reject'),
            $this->closeComplaintAction('cancel'),
        ];
    }

    protected function closeComplaintAction(string $outcome): Action
    {
        [$label, $icon, $color, $heading] = match ($outcome) {
            'resolve' => ['Resolve', 'heroicon-o-check-badge', 'danger', 'Resolve complaint'],
            'reject' => ['Reject', 'heroicon-o-x-circle', 'warning', 'Reject complaint'],
            'cancel' => ['Cancel', 'heroicon-o-arrow-uturn-left', 'gray', 'Cancel complaint'],
        };

        return Action::make($outcome)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (?array $record): bool => static::canCloseComplaints()
                && ($this->complaintForAction($record)['status'] ?? null) === 'pending_dispute')
            ->requiresConfirmation()
            ->modalHeading($heading)
            ->modalDescription(fn (?array $record): string => $this->closeComplaintDescription($outcome, $this->complaintForAction($record)))
            ->modalSubmitActionLabel($label.' complaint')
            ->schema([
                Hidden::make('idempotency_key')
                    ->default(fn (): string => Str::uuid()->toString()),
                Textarea::make('note')
                    ->label('Resolution note')
                    ->helperText('Saved on the complaint as the reason for closing it.')
                    ->required()
                    ->minLength(3)
                    ->maxLength(fn (): int => self::NOTE_MAX_LENGTH - mb_strlen($this->noteSignature()))
                    ->rows(3),
            ])
            ->action(function (array $data, ?array $record, Action $action) use ($outcome): void {
                $complaint = $this->complaintForAction($record);

                try {
                    $updated = app(GameApiService::class)->closeComplaint(
                        (int) $complaint['id'],
                        $outcome,
                        trim($data['note']).$this->noteSignature(),
                        $data['idempotency_key'],
                    );
                } catch (GameApiException $e) {
                    $this->handleCloseComplaintFailure($e, $action);

                    return;
                }

                Notification::make()
                    ->title('Complaint '.GameApiService::COMPLAINT_OUTCOMES[$outcome])
                    ->body($this->closedComplaintSummary($updated))
                    ->success()
                    ->send();

                $this->afterComplaintClosed($updated);
            });
    }

    /**
     * @param  array<string, mixed>  $complaint
     */
    protected function closeComplaintDescription(string $outcome, array $complaint): string
    {
        return match ($outcome) {
            'resolve' => 'The complaint is valid. The disputed transactions are reversed and the players are refunded to their main wallets.'
                .(($complaint['subject_type'] ?? null) === 'game'
                    ? ' For a game every player gets their stake back and the house cuts are reversed.'
                    : ' For a tournament or jackpot only the disputed rounds are reversed.'),
            'reject' => 'The complaint is invalid. The held money is returned to the winner\'s wallet it was taken from.',
            'cancel' => 'The player withdrew the complaint. The held money is returned to the winner\'s wallet it was taken from.',
        };
    }

    /**
     * The wallet API records the API key as `closed_by`, so the acting admin is kept in the note.
     */
    protected function noteSignature(): string
    {
        $name = trim((string) (auth()->user()?->name ?? ''));

        return $name === '' ? '' : ' — by '.$name;
    }

    /**
     * @param  array<string, mixed>  $complaint
     */
    protected function closedComplaintSummary(array $complaint): string
    {
        if (($complaint['status'] ?? null) === 'resolved') {
            return 'Refunded '.Format::money($complaint['refunded_amount'] ?? 0)
                .' · house cuts reversed '.Format::money($complaint['house_cuts_reversed'] ?? 0).'.';
        }

        return 'Released '.Format::money($complaint['released_amount'] ?? 0).' back to the winner.';
    }

    /**
     * A 409 means another admin got there first. Rate limits, outages and server errors keep
     * the modal open so a retry reuses the same Idempotency-Key; anything else is final.
     */
    protected function handleCloseComplaintFailure(GameApiException $e, Action $action): void
    {
        $message = $e->apiMessage !== '' ? $e->apiMessage : $e->getMessage();

        if ($e->statusCode === 409) {
            Notification::make()
                ->title('Complaint already closed')
                ->body($message)
                ->warning()
                ->send();

            $this->afterComplaintClosed();

            return;
        }

        Notification::make()
            ->title('Could not close the complaint')
            ->body($message)
            ->danger()
            ->send();

        if ($e->statusCode === 0 || $e->statusCode === 429 || $e->statusCode >= 500) {
            $action->halt();
        }
    }
}
