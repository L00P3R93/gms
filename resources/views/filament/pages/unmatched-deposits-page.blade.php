<x-filament-panels::page>
    <x-filament::tabs label="Unmatched deposit status">
        @foreach (\App\Filament\Pages\UnmatchedDepositsPage::TABS as $key => $label)
            <x-filament::tabs.item
                :active="$this->tab === $key"
                wire:click="setTab('{{ $key }}')"
            >
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
