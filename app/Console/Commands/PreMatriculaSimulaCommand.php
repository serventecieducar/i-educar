<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreMatriculaSimulaCommand extends Command
{
    protected $signature = 'pmd:simula {--ano=} {--database=}';

    protected $description = 'Cria um processo de pré-matrícula aberto, com séries, turnos e vagas das turmas já existentes';

    public function __construct()
    {
        parent::__construct();
        $this->setAliases(['pwd:simula']);
    }

    public function handle(): int
    {
        if ($this->option('database')) {
            $conexao = (string) $this->option('database');
            config(['database.default' => $conexao]);
            DB::purge($conexao);
            DB::setDefaultConnection($conexao);
        }

        foreach (['processes', 'process_stages', 'process_grade', 'process_period', 'classrooms'] as $relacao) {
            if (!$this->relacaoExiste($relacao)) {
                $this->error('A relação ' . $relacao . ' não existe. Rode php artisan pmd:instalar antes da simulação.');

                return self::FAILURE;
            }
        }

        $ano = $this->anoVigente();

        if (!$ano) {
            $this->error('Não há turmas ativas nem ano letivo em andamento para montar as vagas.');

            return self::FAILURE;
        }

        $this->atualizarVisaoTurmas();

        $series = $this->seriesDoAno($ano);
        $turnos = DB::table('pmieducar.turma')
            ->where('ativo', 1)
            ->where('ano', $ano)
            ->whereNotNull('turma_turno_id')
            ->distinct()
            ->pluck('turma_turno_id');

        if ($series->isEmpty() || $turnos->isEmpty()) {
            $this->error('O ano ' . $ano . ' não tem turma ativa com série e turno. A turma precisa estar ativa, no ano vigente, com série e turno preenchidos.');

            return self::FAILURE;
        }

        $nome = 'Simulação pré-matrícula ' . $ano;
        $agora = now();
        $inicio = $agora->copy()->subDay();
        $fim = $agora->copy()->addDays(90);

        $processoId = DB::transaction(function () use ($ano, $nome, $series, $turnos, $inicio, $fim, $agora) {
            $processoId = DB::table('processes')->where('name', $nome)->value('id');

            if ($processoId === null) {
                $processoId = DB::table('processes')->insertGetId([
                    'school_year_id' => $ano,
                    'name' => $nome,
                    'active' => true,
                    'message_footer' => 'Processo gerado para teste. Não usar como inscrição oficial.',
                    'show_waiting_list' => true,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            } else {
                DB::table('processes')->where('id', $processoId)->update([
                    'school_year_id' => $ano,
                    'active' => true,
                    'show_waiting_list' => true,
                    'selected_schools' => false,
                    'updated_at' => $agora,
                ]);
            }

            foreach ($series as $serie) {
                $existe = DB::table('process_grade')
                    ->where('process_id', $processoId)
                    ->where('grade_id', $serie)
                    ->exists();

                if (!$existe) {
                    DB::table('process_grade')->insert([
                        'process_id' => $processoId,
                        'grade_id' => $serie,
                    ]);
                }
            }

            foreach ($turnos as $turno) {
                $existe = DB::table('process_period')
                    ->where('process_id', $processoId)
                    ->where('period_id', $turno)
                    ->exists();

                if (!$existe) {
                    DB::table('process_period')->insert([
                        'process_id' => $processoId,
                        'period_id' => $turno,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ]);
                }
            }

            $etapas = [
                [2, 'Matrícula', false],
                [1, 'Rematrícula', false],
                [3, 'Lista de espera', true],
            ];

            foreach ($etapas as [$tipo, $titulo, $espera]) {
                $etapa = DB::table('process_stages')
                    ->where('process_id', $processoId)
                    ->where('process_stage_type_id', $tipo)
                    ->first();

                $dados = [
                    'name' => $titulo,
                    'description' => 'Etapa de teste do processo ' . $nome . '.',
                    'start_at' => $inicio,
                    'end_at' => $fim,
                    'allow_waiting_list' => $espera,
                    'allow_search' => true,
                    'restriction_type' => 1,
                    'updated_at' => $agora,
                ];

                if ($etapa === null) {
                    DB::table('process_stages')->insert($dados + [
                        'process_id' => $processoId,
                        'process_stage_type_id' => $tipo,
                        'created_at' => $agora,
                    ]);
                } else {
                    DB::table('process_stages')->where('id', $etapa->id)->update($dados);
                }
            }

            if ($this->relacaoExiste('fields') && $this->relacaoExiste('process_fields')) {
                $ordem = 1;
                foreach (DB::table('fields')->orderBy('id')->pluck('id') as $campo) {
                    $existe = DB::table('process_fields')
                        ->where('process_id', $processoId)
                        ->where('field_id', $campo)
                        ->exists();

                    if ($existe) {
                        continue;
                    }

                    DB::table('process_fields')->insert([
                        'process_id' => $processoId,
                        'field_id' => $campo,
                        'order' => $ordem,
                        'required' => false,
                        'weight' => 0,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ]);
                    $ordem++;
                }
            }

            return $processoId;
        });

        $vagas = 0;
        if ($this->relacaoExiste('process_vacancy')) {
            $vagas = (int) DB::table('process_vacancy')->where('process_id', $processoId)->sum('total');
        }

        $this->info('Processo ' . $processoId . ': ' . $nome);
        $this->line('Ano: ' . $ano);
        $this->line('Séries: ' . $series->count());
        $this->line('Turnos: ' . $turnos->count());
        $this->line('Vagas nas turmas: ' . $vagas);
        $this->line('Inscrição: /pre-matricula-digital/inscricao/' . $processoId);
        $this->line('Lista: /pre-matricula-digital/inscricoes');

        return self::SUCCESS;
    }

    private function anoVigente(): ?int
    {
        if ($this->option('ano')) {
            return (int) $this->option('ano');
        }

        $emAndamento = DB::table('pmieducar.escola_ano_letivo')
            ->where('andamento', 1)
            ->where('ativo', 1)
            ->max('ano');

        if ($emAndamento) {
            $this->line('Ano vigente em andamento: ' . $emAndamento);

            return (int) $emAndamento;
        }

        $anoTurma = DB::table('pmieducar.turma')->where('ativo', 1)->max('ano');

        if ($anoTurma) {
            $this->line('Ano vigente pelas turmas ativas: ' . $anoTurma);

            return (int) $anoTurma;
        }

        return null;
    }

    private function seriesDoAno(int $ano)
    {
        $normais = DB::table('pmieducar.turma')
            ->where('ativo', 1)
            ->where('ano', $ano)
            ->where('multiseriada', 0)
            ->whereNotNull('ref_ref_cod_serie')
            ->distinct()
            ->pluck('ref_ref_cod_serie');

        $multisseriadas = DB::table('pmieducar.turma as turma')
            ->join('pmieducar.turma_serie', 'turma_serie.turma_id', '=', 'turma.cod_turma')
            ->where('turma.ativo', 1)
            ->where('turma.ano', $ano)
            ->where('turma.multiseriada', 1)
            ->distinct()
            ->pluck('turma_serie.serie_id');

        return $normais->merge($multisseriadas)->unique()->values();
    }

    private function atualizarVisaoTurmas(): void
    {
        $arquivo = base_path('database/sqls/pmd-classrooms.sql');

        if (!is_file($arquivo)) {
            return;
        }

        DB::unprepared((string) file_get_contents($arquivo));
    }

    private function relacaoExiste(string $nome): bool
    {
        $achou = DB::selectOne('select to_regclass(?) as nome', [$nome]);

        return $achou !== null && $achou->nome !== null;
    }
}
