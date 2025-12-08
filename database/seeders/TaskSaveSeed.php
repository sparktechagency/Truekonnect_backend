<?php

namespace Database\Seeders;

use App\Models\TaskSave;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaskSaveSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TaskSave::factory(20)->create();
    }
}
