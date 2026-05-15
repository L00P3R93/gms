<x-filament-panels::page>
    {{-- API unavailability warning --}}
    @if($this->apiUnavailable)
        <x-filament::section>
            <div class="flex items-center gap-3 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 dark:border-warning-700 dark:bg-warning-950">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0 text-warning-500" />
                <div>
                    <p class="text-sm font-semibold text-warning-800 dark:text-warning-300">Wallet API Unavailable</p>
                    <p class="text-xs text-warning-700 dark:text-warning-400">Could not connect to the wallet API. Balance, transactions, and purchase data are unavailable. Local game history is still shown below.</p>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- Player Info Header --}}
    <x-filament::section>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Name</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $this->record->name }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Phone</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $this->record->phone }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Email</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $this->record->email }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Game Credits</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ number_format($this->record->credit) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">VCoins</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ number_format($this->record->vcoins) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Wallet Balance</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">KES {{ number_format($this->walletInfo['balance'] ?? 0, 2) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Status / VIP</p>
                <div class="mt-1 flex items-center gap-2">
                    <x-filament::badge :color="$this->record->game_status == 1 ? 'success' : 'gray'">
                        {{ $this->record->game_status == 1 ? 'Active' : 'Hidden' }}
                    </x-filament::badge>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $this->record->current_vip }}</span>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- Tabs --}}
    <div x-data="{ tab: 'single_games' }">
        {{-- Tab nav --}}
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex gap-1 overflow-x-auto">
                @php
                    $tabs = [
                        'single_games'     => 'Single Games (' . count($this->singleGames) . ')',
                        'tournament_games' => 'Tournaments (' . count($this->tournamentGames) . ')',
                        'jackpot_games'    => 'Jackpots (' . count($this->jackpotGames) . ')',
                        'deposits'         => 'Deposits (' . count($this->deposits) . ')',
                        'withdrawals'      => 'Withdrawals (' . count($this->withdrawals) . ')',
                        'purchases'        => 'Purchases (' . count($this->purchases) . ')',
                    ];
                @endphp
                @foreach($tabs as $key => $label)
                    <button
                        @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}'
                            ? 'border-primary-500 text-primary-600 dark:text-primary-400 border-b-2'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 hover:border-gray-300'"
                        class="whitespace-nowrap px-4 py-3 text-sm font-medium transition-colors duration-150">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Tab panels --}}
        <x-filament::section :without-header="true" class="mt-0 rounded-t-none">

            {{-- Single Games --}}
            <div x-show="tab === 'single_games'" x-cloak>
                @if(count($this->singleGames) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Game ID</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Match Type</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Bet (KES)</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Result</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($this->singleGames as $game)
                                    @php $won = (string) $game['winner'] === (string) $this->record->id; @endphp
                                    <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800/50">
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $game['id'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $game['match_type'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ number_format($game['amount'], 2) }}</td>
                                        <td class="px-4 py-3">
                                            <x-filament::badge :color="$won ? 'success' : 'danger'">
                                                {{ $won ? 'Won ✓' : 'Lost' }}
                                            </x-filament::badge>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $game['time'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-500 dark:text-gray-400">No single games found.</div>
                @endif
            </div>

            {{-- Tournament Games --}}
            <div x-show="tab === 'tournament_games'" x-cloak>
                @if(count($this->tournamentGames) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Competition ID</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Bet (KES)</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Level / Round</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($this->tournamentGames as $game)
                                    <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800/50">
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $game['match_name'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ number_format($game['amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $game['match_name'] }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $game['time'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-500 dark:text-gray-400">No tournament games found.</div>
                @endif
            </div>

            {{-- Jackpot Games --}}
            <div x-show="tab === 'jackpot_games'" x-cloak>
                @if(count($this->jackpotGames) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Competition ID</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Bet (KES)</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Tier</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($this->jackpotGames as $game)
                                    <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800/50">
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $game['match_name'] }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ number_format($game['amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $game['match_name'] }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $game['time'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-500 dark:text-gray-400">No jackpot games found.</div>
                @endif
            </div>

            {{-- Deposits --}}
            <div x-show="tab === 'deposits'" x-cloak>
                @if(count($this->deposits) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">#</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Transaction ID</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Amount (KES)</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($this->deposits as $i => $deposit)
                                    <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800/50">
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $deposit['transaction_id'] ?? $deposit['id'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ number_format($deposit['amount'] ?? 0, 2) }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $deposit['date'] ?? $deposit['created_at'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-500 dark:text-gray-400">No deposit records available.</div>
                @endif
            </div>

            {{-- Withdrawals --}}
            <div x-show="tab === 'withdrawals'" x-cloak>
                @if(count($this->withdrawals) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">#</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Transaction ID</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Amount (KES)</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($this->withdrawals as $i => $withdrawal)
                                    <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800/50">
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $withdrawal['transaction_id'] ?? $withdrawal['id'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ number_format($withdrawal['amount'] ?? 0, 2) }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $withdrawal['date'] ?? $withdrawal['created_at'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-500 dark:text-gray-400">No withdrawal records available.</div>
                @endif
            </div>

            {{-- Purchases --}}
            <div x-show="tab === 'purchases'" x-cloak>
                @if(count($this->purchases) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">#</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Type</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Amount</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Value</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($this->purchases as $i => $purchase)
                                    <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800/50">
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $purchase['type'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ number_format($purchase['amount'] ?? 0, 2) }}</td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $purchase['value'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $purchase['date'] ?? $purchase['created_at'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-500 dark:text-gray-400">No purchase records available.</div>
                @endif
            </div>

        </x-filament::section>
    </div>
</x-filament-panels::page>
