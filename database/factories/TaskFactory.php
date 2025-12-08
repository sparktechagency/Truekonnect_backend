<?php

namespace Database\Factories;

use App\Models\Countrie;
use App\Models\SocialMedia;
use App\Models\SocialMediaService;
use App\Models\Task;
use App\Models\TaskPerformer;
use App\Models\User;
use Google\Service\Dfareporting\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Task::class;
    public function definition(): array
    {
        return [
            'sm_id'=>SocialMedia::inRandomOrder()->first(),
            'sms_id'=>SocialMediaService::inRandomOrder()->first(),
            'user_id'=>User::where('role','performer')->inRandomOrder()->first(),
            'country_id'=>Countrie::first(),
            'quantity'=>$this->faker->numberBetween(1,100),
            'description'=>$this->faker->text,
            'link'=>$this->faker->url,
            'per_perform'=>$this->faker->numberBetween(1,100),
            'total_token'=>$this->faker->numberBetween(1,100),
            'token_distributed'=>$this->faker->numberBetween(1,100),
            'unite_price'=>$this->faker->numberBetween(1,100),
            'total_price'=>$this->faker->numberBetween(1,100),
            'note'=>$this->faker->text,
            'rejection_reason'=>$this->faker->text,
            'status'=>fake()->randomElement(['pending','verifyed','rejected','completed','admin_review']),
            'verified_by'=>User::where('role','admin')->inRandomOrder()->first(),
        ];
    }
}
