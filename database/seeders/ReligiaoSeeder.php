<?php

namespace Database\Seeders;

use App\Models\Religion;
use Illuminate\Database\Seeder;

/**
 * Garante a existência das religiões padrão na tabela pmieducar.religions.
 *
 * Usa firstOrCreate (incluindo soft-deleted) para não violar FK em bases
 * que já possuem registros referenciados pela tabela cadastro.fisica.
 * Religiões soft-deleted são restauradas automaticamente.
 */
class ReligiaoSeeder extends Seeder
{
    /**
     * Cria ou restaura cada religião padrão sem apagar registros existentes.
     */
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
