<?php

namespace Database\Seeders;

use App\Models\SocialMedia;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SocialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $social=[
            [
                'name'=>'Facebook',
                'icon_url'=>'social_icons/facebook.png',
            ], 
            [
                'name'=>'Instagram',
                'icon_url'=>'social_icons/instagram.png',
            ],
        ];

        foreach($social as $item){
            SocialMedia::create([
                'name'=>$item['name'],
                'icon_url'=>$item['icon_url'],
            ]);
        }
        
    }
}
