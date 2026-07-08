<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DefaultCadastroRacaTableSeeder extends Seeder
{
    public function run()
    {
        $this->call(RacaSeeder::class);
    }
}
