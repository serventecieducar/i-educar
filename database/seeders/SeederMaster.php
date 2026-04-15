<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SeederMaster extends Seeder
{
    public function run(): void
    {
        $this->call(BrasilLocalidadesSeeder::class);
        $this->call(EncerrarAno2024Seeder::class);
        $this->call(EncerrarAnosAnterioresSeeder::class);
        $this->call(AreaConhecimentoBnccSeeder::class);
        $this->call(ConfiguracaoEscolarSeeder::class);
        $this->call(AeeSeeder::class);
        $this->call(CalendarioEscolarSeeder::class);
        $this->call(BeneficioSocialSeeder::class);
        $this->call(ReligiaoSeeder::class);
        $this->call(TransferenciaTipoSeeder::class);
    }
}

