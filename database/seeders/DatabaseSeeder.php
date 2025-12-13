<?php

namespace Database\Seeders;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\TaskPerformer;
use App\Models\TaskSave;
use Illuminate\Database\Seeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\SocialSeeder;
use Database\Seeders\CountriesSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CountriesSeeder::class,
            AdminSeeder::class,
            SocialSeeder::class,
            TaskSeed::class,
            UserSeed::class,
            TaskSaveSeed::class,
            TaskPerformerSeed::class,
            SupportTicketSeed::class,
            TaskFileSeed::class,
        ]);
    }
}
