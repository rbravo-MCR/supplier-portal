<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'name' => fake()->name(),
            'username' => str($email)->before('@')->lower()->toString(),
            'email' => $email,
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'supplier_id' => null,
        ];
    }
}
