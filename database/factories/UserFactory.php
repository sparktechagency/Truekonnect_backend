<?php

namespace Database\Factories;

use App\Models\Countrie;
use App\Models\User;
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
    protected $model = User::class;
    public function definition(): array
    {
        $referrer = User::inRandomOrder()->first();
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone'=>fake()->phoneNumber(),
            'role'=>fake()->randomElement(['performer', 'brand', 'reviewer', 'admin']),
            'status'=>fake()->randomElement(['active','banned']),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'country_id'=>Countrie::first(),
            'verification_by'=> User::where('role','admin')->inRandomOrder()->first(),
            'avatar'=> fake()->image(),
            'phone_verified_at'=>now(),
            'referral_id' => $referrer?->id,
            'referral_code' => $referrer ? fake()->unique()->uuid() : null,
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
