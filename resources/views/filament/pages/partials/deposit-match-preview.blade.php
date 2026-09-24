<div class="space-y-3">
    <p class="text-sm">
        <span class="font-semibold">{{ number_format((int) ($preview['matched'] ?? 0)) }} {{ \Illuminate\Support\Str::plural('deposit', (int) ($preview['matched'] ?? 0)) }}</span>
        · {{ \App\Support\Format::money($preview['matched_amount'] ?? 0) }} will be assigned.
    </p>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2">Deposit</th>
                    <th class="px-3 py-2 text-right">Amount</th>
                    <th class="px-3 py-2">Bill ref</th>
                    <th class="px-3 py-2">Customer</th>
                    <th class="px-3 py-2">Account no</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $row['deposit'] }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ $row['amount'] }}</td>
                        <td class="px-3 py-2 font-mono">{{ $row['bill_ref'] }}</td>
                        <td class="px-3 py-2">
                            @if ($row['customer_url'])
                                <x-filament::link :href="$row['customer_url']" target="_blank">{{ $row['customer'] }}</x-filament::link>
                            @else
                                {{ $row['customer'] }}
                            @endif
                        </td>
                        <td class="px-3 py-2 font-mono">{{ $row['account_no'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
