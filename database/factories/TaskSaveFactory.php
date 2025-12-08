<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskSave;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskSave>
 */
class TaskSaveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = TaskSave::class;

    public function definition(): array
    {
        return [
            'user_id'=>User::inRandomOrder()->first(),
            'task_id'=>Task::inRandomOrder()->first()
        ];
    }
}
