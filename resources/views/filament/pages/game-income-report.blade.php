<x-filament-panels::page>
    {{-- API error alert --}}
    @if($this->apiError)
        <x-filament::section>
            <div class="flex items-center gap-3 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 dark:border-warning-700 dark:bg-warning-950">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0 text-warning-500" />
                <div>
                    <p class="text-sm font-semibold text-warning-800 dark:text-warning-300">Game API Unavailable</p>
                    <p class="text-xs text-warning-700 dark:text-warning-400">Could not connect to the wallet API. Income data cannot be loaded at this time.</p>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- Period selector --}}
    <x-filament::section>
        <div class="flex flex-wrap gap-2">
            @foreach($this->getPeriodOptions() as $key => $label)
                <button
                    wire:click="$set('period', '{{ $key }}')"
                    @class([
                        'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors duration-150',
                        'bg-primary-600 text-white shadow-sm' => $period === $key,
                        'bg-white text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-gray-700' => $period !== $key,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if($period === 'custom')
            <div class="mt-4 flex flex-wrap gap-4">
                <div>
                    <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">From</p>
                    <input
                        type="date"
                        wire:model.live="customStart"
                        class="rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-gray-950/10 dark:bg-gray-800 dark:text-white dark:ring-white/10"
                    >
                </div>
                <div>
                    <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Until</p>
                    <input
                        type="date"
                        wire:model.live="customEnd"
                        class="rounded-lg border-0 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-gray-950/10 dark:bg-gray-800 dark:text-white dark:ring-white/10"
                    >
                </div>
            </div>
        @endif
    </x-filament::section>

    @php $data = $this->getReportData(); @endphp

    {{-- Summary totals --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-filament::section>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Singles Income</p>
            <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">KES {{ number_format($data['totals']['singles_income'], 2) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tournament Income</p>
            <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">KES {{ number_format($data['totals']['tournament_income'], 2) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Jackpot Income</p>
            <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">KES {{ number_format($data['totals']['jackpot_income'], 2) }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-xs font-medium text-warning-600 dark:text-warning-400">Grand Total</p>
            <p class="mt-1 text-xl font-bold text-warning-700 dark:text-warning-300">KES {{ number_format($data['totals']['grand_total'], 2) }}</p>
        </x-filament::section>
    </div>

    {{-- Breakdown tables --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Singles --}}
        <x-filament::section heading="Singles Games">
            <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Players</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">House Income</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($data['singles'] as $row)
                            <tr>
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $row['players'] ?? '—' }} Players</td>
                                <td class="px-4 py-2.5 text-right font-medium text-gray-950 dark:text-white">KES {{ number_format((float) ($row['total_income'] ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-6 text-center text-xs text-gray-400 dark:text-gray-600">No data for this period</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Tournaments --}}
        <x-filament::section heading="Tournaments">
            <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Rounds</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">House Income</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($data['tournaments'] as $row)
                            <tr>
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $row['jp_rounds'] ?? $row['rounds'] ?? '—' }} Rounds</td>
                                <td class="px-4 py-2.5 text-right font-medium text-gray-950 dark:text-white">KES {{ number_format((float) ($row['total_income'] ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-6 text-center text-xs text-gray-400 dark:text-gray-600">No data for this period</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Jackpots --}}
        <x-filament::section heading="Jackpots">
            <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Tier</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">House Income</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($data['jackpots'] as $row)
                            <tr>
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                    @php $rounds = (int) ($row['jp_rounds'] ?? $row['rounds'] ?? 0); @endphp
                                    {{ match($rounds) { 21 => 'Gold (21)', 17 => 'Silver (17)', 13 => 'Bronze (13)', default => $rounds ? "{$rounds} Rounds" : '—' } }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium text-gray-950 dark:text-white">KES {{ number_format((float) ($row['total_income'] ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-6 text-center text-xs text-gray-400 dark:text-gray-600">No data for this period</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
