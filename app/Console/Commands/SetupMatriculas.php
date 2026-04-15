<?php

namespace App\Console\Commands;

use Database\Seeders\GerarTurmasSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SetupMatriculas extends Command
{
    protected $signature = 'setup:matriculas
                            {--ano= : Ano letivo para gerar turmas (default: ano corrente)}
                            {--ano-anterior : Também gera turmas para o ano anterior}';

    protected $description = 'Passo opcional: gera turmas automaticamente para iniciar matrícula/enturmação';

    public function handle(): int
    {
        $ano = $this->option('ano') ? (int) $this->option('ano') : now()->year;
        $anos = [$ano];
        if ((bool) $this->option('ano-anterior')) {
            $anos[] = $ano - 1;
        }
        $anos = array_values(array_unique($anos));
        sort($anos);

        $this->info('🏫 Gerando turmas automaticamente...');
        $this->line('Anos: ' . implode(', ', $anos));

        Log::channel('daily')->info('Iniciando execução do comando setup:matriculas', ['anos' => $anos]);

        /** @var GerarTurmasSeeder $seeder */
        $seeder = app(GerarTurmasSeeder::class)->setContainer(app())->setCommand($this);
        $seeder->run(['anos' => $anos]);

        $this->info('✅ Turmas geradas.');

        return 0;
    }
}

