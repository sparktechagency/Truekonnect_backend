<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskPerformer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskPerformer>
 */
class TaskPerformerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = TaskPerformer::class;
    public function definition(): array
    {
        return [
            'user_id'=>User::where('role','performer')->inRandomOrder()->first(),
            'task_id'=>Task::inRandomOrder()->first(),
            'token_earned'=>$this->faker->numberBetween(1,100),
            'status'=>$this->faker->randomElement(['pending', 'completed', 'rejected', 'admin_review']),
            'verified_by'=>User::whereIn('role',['admin','reviewer'])->inRandomOrder()->first(),
            'rejection_reason'=>$this->faker->sentence(),
        ];
    }
}
