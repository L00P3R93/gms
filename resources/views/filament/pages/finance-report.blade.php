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

        {{ $this->reportInfolist }}

        @if ($this->hasItemsTable())
            {{ $this->table }}
        @endif
    @endif
</x-filament-panels::page>
