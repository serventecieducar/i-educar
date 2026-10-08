<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $funcoes = [
            'relatorio.get_ddd_escola',
            'relatorio.get_media_geral_turma',
            'relatorio.get_media_turma',
            'relatorio.get_nacionalidade',
            'relatorio.get_nota_exame',
            'relatorio.get_qtde_alunos',
            'relatorio.get_situacao_historico_abreviado',
            'relatorio.get_situacao_historico',
            'relatorio.get_total_falta_componente',
            'relatorio.prioridade_historico',
            'relatorio.get_max_sequencial_matricula',
            'relatorio.get_pai_aluno',
            'relatorio.get_telefone_escola',
            'relatorio.get_total_geral_falta_componente',
            'relatorio.get_mae_aluno',
        ];

        foreach ($funcoes as $funcao) {
            $depende = DB::selectOne("
                SELECT 1
                FROM pg_depend d
                JOIN pg_proc p ON p.oid = d.refobjid
                JOIN pg_namespace n ON n.oid = p.pronamespace
                WHERE n.nspname || '.' || p.proname = ?
                  AND d.deptype = 'n'
                LIMIT 1
            ", [$funcao]);

            if ($depende) {
                continue;
            }

            DB::unprepared('DROP FUNCTION IF EXISTS ' . $funcao);
        }
    }
};
