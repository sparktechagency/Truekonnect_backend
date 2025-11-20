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
                'name' => 'Admin Nazmul',
                'email' => 'admin@gmail.com', 
                'phone' => '018906255852',
                'country_id' => 1,
                'role' => 'admin',
                'status' => 'active',
                'password' => Hash::make('12345678'),
            ],
            [
                'name' => 'Performer Nazmul',
                'email' => 'performer@gmail.com', 
                'phone' => '018906255853',
                'country_id' => 1,
                'role' => 'performer',
                'status' => 'active',
                'password' => Hash::make('12345678'),
            ],
            [
                'name' => 'Reviewer Nazmul',
                'email' => 'reviewer@gmail.com', 
                'phone' => '018906255854',
                'country_id' => 1,
                'role' => 'reviewer',
                'status' => 'active',
                'password' => Hash::make('12345678'),
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
            ]);
        }
    }
}
