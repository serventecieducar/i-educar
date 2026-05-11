<?php

namespace Database\Seeders;

use App\Support\Database\IncrementSequence;
use Illuminate\Database\Seeder;

/**
 * Cadastra país Brasil, todos os Estados, Municípios e Distritos
 * conforme dados do Censo/IBGE no modelo de dados do sistema.
 *
 * Utiliza os CSVs em database/csvs/ com dados oficiais do IBGE.
 */
class BrasilLocalidadesSeeder extends Seeder
{
    use IncrementSequence;

    /**
     * Executa seeders de localidades na ordem país → estados → municípios → distritos
     * e ajusta as sequences do PostgreSQL para evitar conflito de ID em inserções futuras.
     */
    public function run(): void
    {
        $this->call(CountriesTableSeeder::class);
        $this->call(StatesTableSeeder::class);
        $this->call(CitiesTableSeeder::class);
        $this->call(DistrictsTableSeeder::class);

        $this->incrementSequence('countries');
        $this->incrementSequence('states');
        $this->incrementSequence('cities');
        $this->incrementSequence('districts');
    }
}
