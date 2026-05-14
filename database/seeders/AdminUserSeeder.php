<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->command->warn(PHP_EOL.'Creating Admin User...');
        $name = config('app.admin_name');
        $username = config('app.admin_username');
        $phone = config('app.admin_phone');
        $email = config('app.admin_email');
        $password = config('app.admin_password');
        $user = User::firstOrCreate(
            [
                'name' => $name,
                'userName' => $username,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => UserStatus::Active,
            ]
        );

        $user->assignRole('super-admin');
        $this->command->info("✓ Admin: {$name} created.");
    }
}
