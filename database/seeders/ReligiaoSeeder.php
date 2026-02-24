<?php

namespace Database\Seeders;

use App\Models\Religion;
use Illuminate\Database\Seeder;

class ReligiaoSeeder extends Seeder
{
    public function run(): void
    {
        Religion::withTrashed()->forceDelete();

        $religioes = [
            'Católica Apostólica Romana',
            'Evangélica',
            'Espírita',
            'Umbanda',
            'Candomblé',
            'Judaica',
            'Islâmica',
            'Sem religião',
            'Outra',
        ];

        foreach ($religioes as $nome) {
            Religion::create(['name' => $nome]);
        }
    }
}
