<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreMatriculaVisoesCommand extends Command
{
    protected $signature = 'pmd:visoes {--database=}';

    protected $description = 'Atualiza as visões da pré-matrícula para o ano letivo e as séries ativas';

    public function handle(): int
    {
        if ($this->option('database')) {
            $conexao = (string) $this->option('database');
            config(['database.default' => $conexao]);
            DB::purge($conexao);
            DB::setDefaultConnection($conexao);
        }

        foreach ([
            'pmd-school-years.sql',
            'pmd-courses.sql',
            'pmd-grades.sql',
            'pmd-periods.sql',
            'pmd-classrooms.sql',
            'pmd-process-school.sql',
            'pmd-process-vacancy.sql',
            'pmd-process-grade-suggest.sql',
            'pmd-process-vacancy-statistics.sql',
        ] as $arquivo) {
            if (!is_file(base_path('database/sqls/' . $arquivo))) {
                $this->error('Arquivo ausente: database/sqls/' . $arquivo);

                return self::FAILURE;
            }
        }

        DB::transaction(function () {
            foreach ([
                'pmd-school-years.sql',
                'pmd-courses.sql',
                'pmd-grades.sql',
                'pmd-periods.sql',
                'pmd-classrooms.sql',
                'pmd-process-school.sql',
                'pmd-process-vacancy.sql',
                'pmd-process-grade-suggest.sql',
                'pmd-process-vacancy-statistics.sql',
            ] as $arquivo) {
                DB::unprepared((string) file_get_contents(base_path('database/sqls/' . $arquivo)));
            }
        });

        $this->info('Visões da pré-matrícula atualizadas.');

        return self::SUCCESS;
    }
}
