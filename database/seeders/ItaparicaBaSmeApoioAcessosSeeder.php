<?php

declare(strict_types=1);

namespace Database\Seeders;

use iEducar\Packages\BuritiSetup\Database\Seeders\Setup\ItaparicaBaSmeApoioUsersSeeder;
use Illuminate\Database\Seeder;

/**
 * Ponto de entrada no app para os acessos SME Apoio de Itaparica/BA (pacote buriti/i-educar-setup-package).
 *
 * O Artisan só resolve automaticamente classes em `Database\Seeders\` quando o nome passado em
 * `--class=` não contém `\`. Use este seeder para o “caminho curto” do `db:seed`.
 *
 * Na raiz do projeto:
 *
 *     php artisan db:seed --class=Database\\Seeders\\ItaparicaBaSmeApoioAcessosSeeder --force
 *
 * Equivale a garantir perfis municipais e, em seguida, o seeder de contas (como `ieducar:acessos itaparica-ba`).
 *
 * Caminho alternativo (FQCN do pacote, útil em CI ou scripts):
 *
 *     php artisan db:seed --class='iEducar\Packages\BuritiSetup\Database\Seeders\Setup\ItaparicaBaSmeApoioUsersSeeder' --force
 *
 * Nesse caso, execute antes o {@see PerfisUsuariosMunicipioSeeder} se os tipos de usuário ainda não existirem.
 */
final class ItaparicaBaSmeApoioAcessosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PerfisUsuariosMunicipioSeeder::class);
        $this->call(ItaparicaBaSmeApoioUsersSeeder::class);
    }
}
