<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn(PHP_EOL.'Creating Roles...');

        $roles = [
            'super-admin',
            'admin',
            'director',
            'agent',
        ];

        foreach ($roles as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->command?->info('Roles seeded successfully.');
    }
}
