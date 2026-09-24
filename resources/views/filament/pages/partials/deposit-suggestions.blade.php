<div class="space-y-3">
    @foreach ($suggestions as $suggestion)
        <x-filament::section compact>
            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3">
                    @if ($suggestion['url'])
                        <x-filament::link :href="$suggestion['url']" target="_blank" weight="semibold">
                            {{ $suggestion['label'] }}
                        </x-filament::link>
                    @else
                        <span class="text-sm font-semibold">{{ $suggestion['label'] }}</span>
                    @endif

                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $suggestion['phone'] ?? '—' }}</span>
                </div>

                <div class="flex flex-wrap gap-1">
                    @foreach ($suggestion['badges'] as $badge)
                        <x-filament::badge :color="$badge['color']">{{ $badge['label'] }}</x-filament::badge>
                    @endforeach
                </div>

                @if ($suggestion['ambiguous'])
                    <p class="text-xs text-danger-600 dark:text-danger-400">
                        This number belongs to more than one customer. Open each of them before deciding who paid.
                    </p>
                @endif
            </div>
        </x-filament::section>
    @endforeach
</div>
