<x-filament-panels::page>
    <div>
        {{ $this->form }}
    </div>

    @if($playerData = $this->getPlayerData())
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <span>Player Payslip</span>
                    <x-filament::button wire:click="downloadPdf" icon="heroicon-o-arrow-down-tray" size="sm">
                        Download PDF
                    </x-filament::button>
                </div>
            </x-slot>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div>
                    <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Player Information</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Name</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ $playerData['account']->name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ $playerData['account']->phone }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ $playerData['account']->email }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Game Credits</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ number_format($playerData['account']->credit) }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Financial Summary</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Total Deposits</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">KES {{ number_format($playerData['totalDeposits'], 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Total Withdrawals</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">KES {{ number_format($playerData['totalWithdrawals'], 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Total Purchases</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">KES {{ number_format($playerData['totalPurchases'], 2) }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Game Statistics</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Games Played</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ number_format($playerData['gamesPlayed']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Games Won</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ number_format($playerData['gamesWon']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Win Rate</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ $playerData['winRate'] }}%</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section class="mt-6">
            <p class="py-8 text-center text-gray-500 dark:text-gray-400">Select a player above to generate their payslip.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
