<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SeederMaster extends Seeder
{
    public function run(): void
    {
        $this->call(EncerrarAno2024Seeder::class);
        $this->call(ConfiguracaoEscolarSeeder::class);
        $this->call(CalendarioEscolarSeeder::class);
        $this->call(ReligiaoSeeder::class);
    }
}
