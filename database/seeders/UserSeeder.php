<?php

namespace Database\Seeders;

use App\Enums\HolderStatus;
use App\Enums\UserStatus;
use App\Models\HolderWallet;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends BaseSeeder
{
    /**
     * Path to the JSON data file.
     */
    protected string $dataPath;

    public function __construct()
    {
        $this->dataPath = database_path('seeders/data/gms-users.json');
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gmsUsers = $this->loadUsers();

        if (empty($gmsUsers)) {
            $this->command->error('❌ No users found in JSON file. Seeding aborted.');

            return;
        }

        $createdCount = 0;

        foreach ($gmsUsers as $userData) {
            $existingUser = User::where('email', $userData['email'])->first();

            if (! $existingUser) {
                $user = $this->createUser($userData);
                $this->createHolder($user, $userData);
                $this->command->info("✓ User {$user->name} created with Holder record and assigned to '{$userData['role']}' role.");
                $createdCount++;
            } else {
                $this->updateExistingUser($existingUser, $userData);
            }
        }

        $this->command->info("✅ {$createdCount} new GMS users created. All records synced successfully.");
    }

    /**
     * Load and validate user data from JSON.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadUsers(): array
    {
        if (! File::exists($this->dataPath)) {
            $this->command->error("❌ JSON file not found: {$this->dataPath}");

            return [];
        }

        $content = File::get($this->dataPath);
        $users = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('❌ Invalid JSON: '.json_last_error_msg());

            return [];
        }

        return $users ?? [];
    }

    /**
     * Create a new user from JSON data.
     */
    protected function createUser(array $data): User
    {
        $user = User::factory()->create([
            'name' => $data['name'],
            'userName' => $data['userName'],
            'email' => $data['email'],
            'email_verified_at' => now(),
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(10),
            'status' => UserStatus::Active->value,
        ]);

        $user->assignRole($data['role']);

        return $user;
    }

    /**
     * Create a Holder record for a user.
     */
    protected function createHolder(User $user, array $data): void
    {
        $holder = $user->holder()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'id_no' => $data['id_no'],
            'share' => $data['share'],
            'status' => HolderStatus::Active->value,
        ]);

        // Create Holder wallet
        HolderWallet::query()->firstOrCreate([
            'holder_id' => $holder->id,
            'balance' => 0,
        ]);
    }

    /**
     * Update an existing user and ensure Holder record exists.
     */
    protected function updateExistingUser(User $existingUser, array $data): void
    {
        $existingUser->syncRoles([$data['role']]);

        if (! $existingUser->holder) {
            $this->createHolder($existingUser, $data);
            $this->command->info("✓ User {$existingUser->name} already exists. Role updated to '{$data['role']}' and Holder record created.");
        } else {
            $this->command->info("✓ User {$existingUser->name} already exists. Role updated to '{$data['role']}'.");
        }
    }
}
