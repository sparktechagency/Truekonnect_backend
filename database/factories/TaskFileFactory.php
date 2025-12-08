<?php

namespace Database\Factories;

use App\Models\TaskFile;
use App\Models\TaskPerformer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskFile>
 */
class TaskFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = TaskFile::class;
    public function definition(): array
    {
        return [
            'tp_id'=>TaskPerformer::inRandomOrder()->first(),
            'file_url'=>$this->faker->imageUrl(),
        ];
    }
}
