<?php

namespace Database\Seeders;

use App\Models\Holder;
use App\Models\HolderWallet;
use Illuminate\Database\Seeder;

class ShareholderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shareholders = [
            ['name' => 'Tax Account', 'share' => 0.3500, 'sort_order' => 1],
            ['name' => 'Company Account', 'share' => 0.2600, 'sort_order' => 2],
            ['name' => 'Angel', 'share' => 0.1560, 'sort_order' => 3],
            ['name' => 'Kennedy', 'share' => 0.1287, 'sort_order' => 4],
            ['name' => 'Zack', 'share' => 0.0585, 'sort_order' => 5],
            ['name' => 'Mitko', 'share' => 0.0234, 'sort_order' => 6],
            ['name' => 'Vincent', 'share' => 0.0234, 'sort_order' => 7],
        ];

        foreach ($shareholders as $shareholderData) {
            $holder = Holder::updateOrCreate(
                ['name' => $shareholderData['name']],
                [
                    'share' => $shareholderData['share'],
                    'status' => 'active',
                    'sort_order' => $shareholderData['sort_order'],
                ]
            );

            // Ensure wallet exists
            if (! $holder->wallet) {
                HolderWallet::create([
                    'holder_id' => $holder->id,
                    'balance' => 0,
                ]);
            }
        }

        $this->command->info('Shareholders seeded successfully.');
    }
}
