<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = SupportTicket::class;
    public function definition(): array
    {
        return [
            'user_id'=>User::whereIn('role',['performer','brand'])->first(),
            'subject'=>$this->faker->sentence(),
            'issue'=>$this->faker->sentence(),
            'attachments'=>$this->faker->imageUrl(),
            'status'=>'pending',
            'admin_reason' => $this->faker->sentence(),
        ];
    }
}
