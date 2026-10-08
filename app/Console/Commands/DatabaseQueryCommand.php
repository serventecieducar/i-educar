<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseQueryCommand extends Command
{
    protected $signature = 'db:query {sql : SQL a executar na conexão padrão}';

    protected $description = 'Executa um SQL na conexão padrão do .env';

    public function handle(): int
    {
        $sql = trim((string) $this->argument('sql'));

        if ($sql === '') {
            $this->error('Informe o SQL.');

            return self::FAILURE;
        }

        $this->line('Conexão: ' . DB::getDefaultConnection());

        if (preg_match('/^\s*(select|with|show|explain)\b/i', $sql) && substr_count($sql, ';') <= 1) {
            $linhas = array_map(fn ($linha) => (array) $linha, DB::select(rtrim($sql, " \t\n\r\0\x0B;")));

            if ($linhas === []) {
                $this->info('Nenhuma linha.');

                return self::SUCCESS;
            }

            $this->table(array_keys($linhas[0]), $linhas);

            return self::SUCCESS;
        }

        DB::unprepared($sql);
        $this->info('SQL executado.');

        return self::SUCCESS;
    }
}
