<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => Str::lower(Str::random(10)),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password#123',
            'role_id' => fn () => Role::where('name', Role::SUPER_ADMIN)->value('id'),
            'is_active' => true,
        ];
    }

    public function role(string $name): static
    {
        return $this->state(fn () => ['role_id' => Role::where('name', $name)->value('id')]);
    }
}
