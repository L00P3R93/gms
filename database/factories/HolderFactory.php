<?php

namespace Database\Factories;

use App\Enums\HolderStatus;
use App\Models\Holder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holder>
 */
class HolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'id_no' => fake()->randomNumber(10),
            'share' => fake()->randomFloat(2, 0, 100),
            'status' => HolderStatus::Active->value,
            'user_id' => User::query()->inRandomOrder()->first()->id,
        ];
    }
}
