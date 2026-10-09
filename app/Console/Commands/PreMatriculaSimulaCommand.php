<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreMatriculaSimulaCommand extends Command
{
    protected $signature = 'pmd:simula {--ano=} {--database=}';

    protected $description = 'Cria um processo de pré-matrícula no ano letivo seguinte, sem alterar o ano em andamento';

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

        $anoAtual = $this->anoVigente();

        if (!$anoAtual) {
            $this->error('Não há turmas ativas nem ano letivo em andamento para copiar ao ano seguinte.');

            return self::FAILURE;
        }

        $ano = $this->anoDaPreMatricula($anoAtual);

        if (!$ano) {
            return self::FAILURE;
        }

        $criados = $this->abrirAnoSeguinte($anoAtual, $ano);
        $this->marcarSeriesDaPreMatricula($ano);

        $series = $this->seriesDoAno($ano);
        $turnos = DB::table('pmieducar.turma')
            ->where('ativo', 1)
            ->where('ano', $ano)
            ->whereNotNull('turma_turno_id')
            ->distinct()
            ->pluck('turma_turno_id');

        if ($series->isEmpty() || $turnos->isEmpty()) {
            $this->error('O ano ' . $ano . ' não ficou com turma ativa, série ativa, curso ativo e turno. A escola também precisa estar ativa e em atividade.');

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

            $this->completarProcesso((int) $processoId);

            return $processoId;
        });

        if ($this->call('pmd:visoes') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $vagas = 0;
        if ($this->relacaoExiste('process_vacancy')) {
            $vagas = (int) DB::table('process_vacancy')->where('process_id', $processoId)->sum('total');
        }

        $this->info('Processo ' . $processoId . ': ' . $nome);
        $this->line('Ano em andamento, sem alteração: ' . $anoAtual);
        $this->line('Ano da pré-matrícula: ' . $ano);
        $this->line('Anos letivos criados: ' . $criados['anos']);
        $this->line('Turmas copiadas: ' . $criados['turmas']);
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

    private function anoDaPreMatricula(int $anoAtual): ?int
    {
        if ($this->option('ano')) {
            $ano = (int) $this->option('ano');

            if ($ano <= $anoAtual) {
                $this->error('O ano da pré-matrícula precisa ser posterior a ' . $anoAtual . '. O ano em andamento permanece como está.');

                return null;
            }

            return $ano;
        }

        return $anoAtual + 1;
    }

    /**
     * @return array{anos: int, turmas: int}
     */
    private function abrirAnoSeguinte(int $anoAtual, int $ano): array
    {
        $existentes = collect(DB::select(
            "select column_name from information_schema.columns where table_schema = 'pmieducar' and table_name = 'escola_ano_letivo'"
        ))->pluck('column_name');

        $ano = (int) $ano;
        $anoAtual = (int) $anoAtual;
        $campos = [
            'ref_cod_escola' => 'turma.ref_ref_cod_escola',
            'ano' => (string) $ano,
            'ref_usuario_cad' => 'coalesce(atual.ref_usuario_cad, turma.ref_usuario_cad)',
            'andamento' => '0',
            'ativo' => '1',
        ];

        if ($existentes->contains('created_at')) {
            $campos['created_at'] = 'now()';
        } elseif ($existentes->contains('data_cadastro')) {
            $campos['data_cadastro'] = 'now()';
        }

        if ($existentes->contains('updated_at')) {
            $campos['updated_at'] = 'now()';
        }

        $anos = DB::affectingStatement(
            'insert into pmieducar.escola_ano_letivo (' . implode(', ', array_keys($campos)) . ')
             select distinct ' . implode(', ', $campos) . '
             from pmieducar.turma turma
             join pmieducar.escola escola on escola.cod_escola = turma.ref_ref_cod_escola
             left join pmieducar.escola_ano_letivo atual
               on atual.ref_cod_escola = turma.ref_ref_cod_escola
              and atual.ano = ' . $anoAtual . '
             where turma.ativo = 1
               and turma.ano = ' . $anoAtual . '
               and turma.ref_ref_cod_escola is not null
               and escola.ativo = 1
               and escola.situacao_funcionamento = 1
               and not exists (
                 select 1
                 from pmieducar.escola_ano_letivo ja
                 where ja.ref_cod_escola = turma.ref_ref_cod_escola
                   and ja.ano = ' . $ano . '
               )'
        );

        $turmas = $this->copiarTurmas($anoAtual, $ano, false) + $this->copiarTurmas($anoAtual, $ano, true);
        $this->copiarSeriesDaTurma($anoAtual, $ano);

        return ['anos' => $anos, 'turmas' => $turmas];
    }

    private function copiarTurmas(int $anoAtual, int $ano, bool $multisseriada): int
    {
        $colunas = collect(DB::select(
            "select column_name from information_schema.columns where table_schema = 'pmieducar' and table_name = 'turma' and column_name <> 'cod_turma' order by ordinal_position"
        ))->pluck('column_name');

        $nomes = $colunas->map(fn ($coluna) => '"' . $coluna . '"')->implode(', ');
        $origem = $colunas->map(function ($coluna) use ($ano) {
            return match ($coluna) {
                'ano' => $ano . ' as ano',
                'data_cadastro' => 'now() as data_cadastro',
                'data_exclusao' => 'null::timestamp as data_exclusao',
                'ref_usuario_exc' => 'null::integer as ref_usuario_exc',
                'ativo' => '1 as ativo',
                default => 'origem."' . $coluna . '"',
            };
        })->implode(', ');

        $serie = $multisseriada
            ? 'join pmieducar.turma_serie turma_serie on turma_serie.turma_id = origem.cod_turma
               join pmieducar.serie serie on serie.cod_serie = turma_serie.serie_id'
            : 'join pmieducar.serie serie on serie.cod_serie = origem.ref_ref_cod_serie';

        $filtroSerie = $multisseriada
            ? 'and origem.multiseriada = 1'
            : 'and origem.multiseriada = 0 and origem.ref_ref_cod_serie is not null';

        return DB::affectingStatement(
            "insert into pmieducar.turma ($nomes)
             select distinct on (origem.cod_turma) $origem
             from pmieducar.turma origem
             join pmieducar.escola escola on escola.cod_escola = origem.ref_ref_cod_escola
             $serie
             join pmieducar.curso curso on curso.cod_curso = serie.ref_cod_curso
             where origem.ativo = 1
               and origem.ano = ?
               and origem.turma_turno_id is not null
               $filtroSerie
               and escola.ativo = 1
               and escola.situacao_funcionamento = 1
               and serie.ativo = 1
               and curso.ativo = 1
               and not exists (
                 select 1
                 from pmieducar.turma ja
                 where ja.ano = ?
                   and ja.ativo = 1
                   and ja.ref_ref_cod_escola = origem.ref_ref_cod_escola
                   and ja.nm_turma = origem.nm_turma
                   and ja.turma_turno_id = origem.turma_turno_id
               )
             order by origem.cod_turma",
            [$anoAtual, $ano]
        );
    }

    private function copiarSeriesDaTurma(int $anoAtual, int $ano): void
    {
        if (!$this->relacaoExiste('pmieducar.turma_serie')) {
            return;
        }

        DB::affectingStatement(
            'insert into pmieducar.turma_serie (escola_id, serie_id, turma_id, boletim_id, boletim_diferenciado_id, created_at, updated_at)
             select origem_serie.escola_id, origem_serie.serie_id, destino.cod_turma, origem_serie.boletim_id, origem_serie.boletim_diferenciado_id, now(), now()
             from pmieducar.turma origem
             join pmieducar.turma_serie origem_serie on origem_serie.turma_id = origem.cod_turma
             join pmieducar.turma destino
               on destino.ano = ?
              and destino.ativo = 1
              and destino.ref_ref_cod_escola = origem.ref_ref_cod_escola
              and destino.nm_turma = origem.nm_turma
              and destino.turma_turno_id = origem.turma_turno_id
             where origem.ano = ?
               and origem.multiseriada = 1
               and origem.ativo = 1
               and not exists (
                 select 1
                 from pmieducar.turma_serie ja
                 where ja.turma_id = destino.cod_turma
                   and ja.serie_id = origem_serie.serie_id
               )',
            [$ano, $anoAtual]
        );
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

    private function marcarSeriesDaPreMatricula(int $ano): void
    {
        if ($this->colunaExiste('pmieducar.serie', 'importar_serie_pre_matricula')) {
            DB::statement(
                'update pmieducar.serie serie
                 set importar_serie_pre_matricula = true
                 where serie.ativo = 1
                   and (
                     exists (
                       select 1 from pmieducar.turma turma
                       where turma.ativo = 1 and turma.ano = ? and turma.ref_ref_cod_serie = serie.cod_serie
                     )
                     or exists (
                       select 1 from pmieducar.turma turma
                       join pmieducar.turma_serie turma_serie on turma_serie.turma_id = turma.cod_turma
                       where turma.ativo = 1 and turma.ano = ? and turma_serie.serie_id = serie.cod_serie
                     )
                   )',
                [$ano, $ano]
            );
        }

        if ($this->colunaExiste('pmieducar.escola_serie', 'anos_letivos')) {
            DB::statement(
                'update pmieducar.escola_serie escola_serie
                 set anos_letivos = escola_serie.anos_letivos || ?::smallint
                 where escola_serie.ativo = 1
                   and not (escola_serie.anos_letivos @> array[?]::smallint[])
                   and exists (
                     select 1 from pmieducar.turma turma
                     where turma.ativo = 1
                       and turma.ano = ?
                       and turma.ref_ref_cod_escola = escola_serie.ref_cod_escola
                       and turma.ref_ref_cod_serie = escola_serie.ref_cod_serie
                   )',
                [$ano, $ano, $ano]
            );
        }

        if ($this->colunaExiste('pmieducar.escola_curso', 'anos_letivos')) {
            DB::statement(
                'update pmieducar.escola_curso escola_curso
                 set anos_letivos = escola_curso.anos_letivos || ?::smallint
                 where not (escola_curso.anos_letivos @> array[?]::smallint[])
                   and exists (
                     select 1
                     from pmieducar.turma turma
                     join pmieducar.serie serie on serie.cod_serie = turma.ref_ref_cod_serie
                     where turma.ativo = 1
                       and turma.ano = ?
                       and turma.ref_ref_cod_escola = escola_curso.ref_cod_escola
                       and serie.ref_cod_curso = escola_curso.ref_cod_curso
                   )',
                [$ano, $ano, $ano]
            );
        }
    }

    private function completarProcesso(int $processoId): void
    {
        $existentes = collect(DB::select(
            "select column_name from information_schema.columns where table_schema = 'public' and table_name = 'processes'"
        ))->pluck('column_name');

        $valores = [
            'force_suggested_grade' => false,
            'show_priority_protocol' => false,
            'allow_responsible_select_map_address' => false,
            'block_incompatible_age_group' => false,
            'auto_reject_by_days' => false,
            'selected_schools' => false,
            'waiting_list_limit' => 0,
            'one_per_year' => false,
            'show_waiting_list' => true,
            'reject_type_id' => 0,
            'priority_custom' => false,
            'active' => true,
        ];

        $gravar = [];
        foreach ($valores as $coluna => $valor) {
            if ($existentes->contains($coluna)) {
                $gravar[$coluna] = $valor;
            }
        }

        if ($gravar !== []) {
            DB::table('processes')->where('id', $processoId)->update($gravar);
        }
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        [$esquema, $nome] = explode('.', $tabela);

        return collect(DB::select(
            'select 1 from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?',
            [$esquema, $nome, $coluna]
        ))->isNotEmpty();
    }

    private function relacaoExiste(string $nome): bool
    {
        $achou = DB::selectOne('select to_regclass(?) as nome', [$nome]);

        return $achou !== null && $achou->nome !== null;
    }
}
