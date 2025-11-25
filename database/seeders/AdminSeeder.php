<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $users=[
            [
                'name' => 'Admin',
                'email' => 'admin@gmail.com',
                'phone' => '01234567891',
                'country_id' => 1,
                'role' => 'admin',
                'status' => 'active',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Performer',
                'email' => 'performer@gmail.com',
                'phone' => '01234567892',
                'country_id' => 1,
                'role' => 'performer',
                'status' => 'active',
                'password' => Hash::make('12345678'),
                'phone_verified_at' => now(),
            ],
            [
                'name' => 'Brand',
                'email' => 'brand@gmail.com',
                'phone' => '01234567893',
                'country_id' => 1,
                'role' => 'brand',
                'status' => 'active',
                'password' => Hash::make('12345678'),
                'phone_verified_at' => now(),
            ],
            [
                'name' => 'Reviewer',
                'email' => 'reviewer@gmail.com',
                'phone' => '01234567894',
                'country_id' => 1,
                'role' => 'reviewer',
                'status' => 'active',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ],
        ];
        foreach($users as $list){
            User::create([
                'name' => $list['name'],
                'email' => $list['email'],
                'phone' => $list['phone'],
                'country_id' => $list['country_id'],
                'role' => $list['role'],
                'status' => $list['status'],
                'password' => $list['password'],
                'email_verified_at' => $list['email_verified_at'] ?? null,
                'phone_verified_at' => $list['phone_verified_at'] ?? null,
            ]);
        }
    }
}
