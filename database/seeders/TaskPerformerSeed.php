<?php

namespace Database\Seeders;

use App\Models\TaskPerformer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaskPerformerSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TaskPerformer::factory(50)->create();
    }
}
