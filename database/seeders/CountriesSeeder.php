<?php

namespace Database\Seeders;


use App\Models\Countrie;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $country=[
            [
                'name' => 'Ghana',
                'dial_code' => '+233',
                'flag' => 'country_flags/ghana.png',
                'token_rate'=>0.001,
                'currency_code'=>'GHC',
            ],
            [
                'name' => 'Nigeria',
                'dial_code' => '+234',
                'flag' => 'country_flags/nigeria.png',
                'token_rate'=>0.10,
                'currency_code'=>'NGN',
            ]
        ];
        foreach($country as $list){
            Countrie::create(
            [
                'name' => $list['name'],
                'dial_code' => $list['dial_code'],
                'flag' => $list['flag'],
                'token_rate' => $list['token_rate'],
                'currency_code' => $list['currency_code'],
            ],

            );
        }
        
    }
}
