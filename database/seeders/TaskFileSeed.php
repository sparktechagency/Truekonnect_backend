<?php

namespace Database\Seeders;

use App\Models\TaskFile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaskFileSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TaskFile::factory(40)->create();
    }
}
