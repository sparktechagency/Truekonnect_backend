<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupportTicketSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SupportTicket::factory(20)->create();
    }
}
