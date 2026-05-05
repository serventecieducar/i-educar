<?php

namespace Database\Seeders;

use App\Models\Religion;
use Illuminate\Database\Seeder;

class ReligiaoSeeder extends Seeder
{
    public function run(): void
    {
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
            /** @var Religion $religiao */
            $religiao = Religion::withTrashed()->firstOrCreate(['name' => $nome], ['name' => $nome]);
            if ($religiao->trashed()) {
                $religiao->restore();
            }
        }
    }
}
