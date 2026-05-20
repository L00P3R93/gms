<x-filament-panels::page>
    {{ $this->form }}

    @if ($this->selectedAccountId)
        <div class="flex justify-end">
            <x-filament::button wire:click="downloadPdf" icon="heroicon-o-arrow-down-tray" size="sm">
                Download PDF
            </x-filament::button>
        </div>
    @endif

    {{ $this->payslipInfolist }}
</x-filament-panels::page>
