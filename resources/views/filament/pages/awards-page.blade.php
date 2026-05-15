<x-filament-panels::page>
    @if($this->apiError)
        <x-filament::section>
            <div class="flex items-center gap-3 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 dark:border-warning-700 dark:bg-warning-950">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0 text-warning-500" />
                <div>
                    <p class="text-sm font-semibold text-warning-800 dark:text-warning-300">Game API Unavailable</p>
                    <p class="text-xs text-warning-700 dark:text-warning-400">Could not connect to the wallet API. Award data cannot be loaded at this time.</p>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
