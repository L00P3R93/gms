<x-filament-panels::page>
    @if ($houseWalletWarning = $this->houseWalletWarning())
        <x-filament::section>
            <div class="flex items-center gap-3">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0 text-warning-500" />
                <div>
                    <p class="text-sm font-semibold text-warning-600 dark:text-warning-400">House wallet running low</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $houseWalletWarning }}</p>
                </div>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section icon="heroicon-o-information-circle" compact>
        <x-slot name="heading">How the signup bonus works</x-slot>

        <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
            <p>
                A player who signs up with an active code, then verifies their email and phone before the code expires,
                gets KES 20.00 in their main wallet. The house wallet pays KES 21.05: KES 1.05 is excise duty owed to KRA.
                The bonus can only be staked until the player has staked KES 20.00; until then it can't be withdrawn,
                transferred or spent on coins.
            </p>
            <p>
                The promotion's on/off switch and budget cap are KadiApi settings. While the promotion is off, codes can
                still be created here, but they aren't applied at signup and no bonus is paid.
            </p>
        </div>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
