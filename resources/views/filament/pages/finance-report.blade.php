<x-filament-panels::page>
    @php($this->getReport())

    @if ($this->apiError)
        <x-filament::section>
            <div class="flex items-center gap-3">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0 text-warning-500" />
                <div>
                    <p class="text-sm font-semibold text-warning-600 dark:text-warning-400">Game API unavailable</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">This report could not be loaded. Refresh the page to try again.</p>
                </div>
            </div>
        </x-filament::section>
    @else
        @foreach ($this->getWarnings() as $warning)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 shrink-0 text-info-500" />
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $warning }}</p>
                </div>
            </x-filament::section>
        @endforeach

        @foreach ($this->getProgressBars() as $bar)
            <x-filament::section compact>
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $bar['label'] }}</p>
                    <p class="text-sm font-semibold" style="color: var(--{{ $bar['color'] }}-600);">{{ number_format($bar['percent'], 1) }}%</p>
                </div>
                <div
                    role="progressbar"
                    aria-label="{{ $bar['label'] }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ min(100, $bar['percent']) }}"
                    style="margin-top: 0.5rem; height: 0.625rem; border-radius: 9999px; overflow: hidden; background: color-mix(in oklab, var(--gray-400) 30%, transparent);"
                >
                    <div style="height: 100%; width: {{ min(100, $bar['percent']) }}%; border-radius: 9999px; background: var(--{{ $bar['color'] }}-500);"></div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $bar['description'] }}</p>
            </x-filament::section>
        @endforeach

        {{ $this->reportInfolist }}

        @if ($this->hasItemsTable())
            {{ $this->table }}
        @endif
    @endif
</x-filament-panels::page>
