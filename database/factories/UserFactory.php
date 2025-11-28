<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
        static $counter = 1;
        
        $firstName = 'TestUser' . $counter;
        $lastName = 'LastName' . $counter;
        $username = 'testuser' . $counter;
        $counter++;
        
        return [
            'name' => $firstName . ' ' . $lastName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'kode_user' => strtoupper(substr($username, 0, 8)),
            'email' => 'testuser' . ($counter - 1) . '@example.com',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'password' => static::$password ??= bcrypt('password'),
            'remember_token' => bin2hex(random_bytes(5)),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
