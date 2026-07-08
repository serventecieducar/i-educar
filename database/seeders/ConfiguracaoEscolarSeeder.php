<?php

namespace Database\Seeders;

use App\Models\LegacyCourse;
use App\Models\LegacyDiscipline;
use App\Models\LegacyDisciplineAcademicYear;
use App\Models\LegacyEducationLevel;
use App\Models\LegacyEducationType;
use App\Models\LegacyEvaluationRule;
use App\Models\LegacyEvaluationRuleGradeYear;
use App\Models\LegacyGrade;
use App\Models\LegacyKnowledgeArea;
use App\Models\LegacyPeriod;
use App\Models\LegacyRegimeType;
use App\Models\LegacySchool;
use App\Models\LegacySchoolAcademicYear;
use App\Models\LegacySchoolClassType;
use App\Models\LegacySchoolCourse;
use App\Models\LegacySchoolGrade;
use App\Models\LegacySchoolGradeDiscipline;
use App\Models\LegacySequenceGrade;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\OutputInterface;

class ConfiguracaoEscolarSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    private const ESCOLA_SERIE_DISCIPLINA_UPSERT_CHUNK = 500;

    /** Tripletas escola:série:disciplina já sincronizadas nesta execução (evita retrabalho no pós-processamento). */
    private array $escolaSerieDisciplinaJaSincronizados = [];

    /** Instituições com cadastros BNCC (nível, disciplinas, cursos) já preparados nesta execução. */
    private array $instituicaoBnccPreparada = [];

    /** @var array<int, LegacyEvaluationRule> */
    private array $regraAvaliacaoPorInstituicao = [];

    /** @var array<int, array{nivel: LegacyEducationLevel, tipo: LegacyEducationType, regime: ?LegacyRegimeType}> */
    private array $cadastrosEnsinoPorInstituicao = [];

    /** @var array<int, array<string, LegacyCourse>> */
    private array $cursosBnccPorInstituicao = [];

    /** @var array<int, array<int, array<string, LegacyGrade>>> */
    private array $seriesBnccPorInstituicao = [];

    /** @var array<int, array<string, LegacyDiscipline>> nome (lower) => model */
    private array $disciplinasBnccPorInstituicao = [];

    /** @var array<int, true> cod_escola já configurada (pré-carga em lote). */
    private array $escolasJaConfiguradasMapa = [];

    /** Quando true, loga cada escola/instituição e micro-etapas (env ou `-v` no artisan). */
    private function traceEnabled(): bool
    {
        if (filter_var(env('CONFIGURACAO_ESCOLAR_SEEDER_TRACE', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $cmd = $this->command;

        return $cmd !== null && $cmd->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }

    /**
     * Sempre Log::info (útil em produção com `tail -f storage/logs/laravel.log`);
     * em TTY também imprime na saída do artisan.
     */
    private function step(string $message): void
    {
        $line = '[ConfiguracaoEscolarSeeder] '.$message;
        Log::info($line);
        $this->command?->info($line);
    }

    /** Só quando {@see traceEnabled()} — não poluir log em seeds longos sem necessidade. */
    private function trace(string $message): void
    {
        if (!$this->traceEnabled()) {
            return;
        }
        $line = '[ConfiguracaoEscolarSeeder] '.$message;
        Log::info($line);
        $this->command?->line($line);
    }

    /**
     * Progresso por iteração em loops (consola + log), para acompanhar cada etapa e o estado.
     *
     * @param  string  $contexto  Onde está (ex.: escola, curso, série)
     * @param  string  $status    Estado curto: EM CURSO, OK, PULADA, AVISO, IGNORADA
     * @param  string  $acao      O que está a ser feito neste passo
     */
    private function relatorioEtapa(string $contexto, string $status, string $acao): void
    {
        if (!$this->traceEnabled()) {
            return;
        }

        $line = sprintf('[ConfiguracaoEscolarSeeder] %s | [%s] %s', $contexto, $status, $acao);
        Log::info($line);
        $this->command?->line($line);
    }

    /** @return array{0: int, 1: int} Ano anterior e ano atual */
    private function obterAnosLetivos(): array
    {
        $anoAtual = Carbon::now()->year;

        return [$anoAtual - 1, $anoAtual];
    }

    /** Retorna anos_letivos como string PostgreSQL array */
    private function anosLetivosParaPg(array $anos): string
    {
        return '{' . implode(',', $anos) . '}';
    }

    /**
     * Séries, vínculos escola-série, disciplinas BNCC e sequência de enturmação do Ensino Médio.
     * Use após o fluxo padrão ({@see IeducarSetupContext::ensinoMedioApenasCursoNoFluxoPadrao}) ou via `ieducar:ensino-medio`.
     */
    public function complementarEnsinoMedio(): void
    {
        $tInicio = microtime(true);
        [$anoAnterior, $anoAtual] = $this->obterAnosLetivos();
        $anos = [$anoAnterior, $anoAtual];
        $fromEscola = $this->resolveFromEscola();
        $nomeCurso = 'Ensino Médio';

        $query = LegacySchool::query()->orderBy('cod_escola');
        if ($fromEscola !== null) {
            $query->where('cod_escola', '>=', $fromEscola);
        }

        $schools = $query->get(['cod_escola', 'ref_cod_instituicao']);
        if ($schools->isEmpty()) {
            $this->command?->warn('Nenhuma escola encontrada para complementar Ensino Médio.');

            return;
        }

        $this->step(sprintf(
            'Complemento Ensino Médio (anos %d e %d, %d escolas%s).',
            $anoAnterior,
            $anoAtual,
            $schools->count(),
            $fromEscola !== null ? ", a partir da escola {$fromEscola}" : ''
        ));

        foreach ($schools as $school) {
            /** @var LegacySchool $school */
            $instituicaoId = (int) $school->ref_cod_instituicao;
            $this->criarCursosEVinculosSemTurmas($school, $instituicaoId, $anos, [$nomeCurso]);
        }

        $instituicoes = $schools->pluck('ref_cod_instituicao')->unique()->filter()->values();
        foreach ($instituicoes as $instituicaoId) {
            $this->garantirSequenciasEnturmacaoBncc((int) $instituicaoId, [$nomeCurso]);
            $this->garantirEscolaSerieDisciplinasInstituicao((int) $instituicaoId, $anos);
        }

        $this->habilitarBloqueioMatriculaSerieNaoSeguinte($instituicoes->all());
        $this->step(sprintf('Complemento Ensino Médio finalizado em %.2fs.', microtime(true) - $tInicio));
    }

    public function run(): void
    {
        $tInicio = microtime(true);
        [$anoAnterior, $anoAtual] = $this->obterAnosLetivos();
        $anos = [$anoAnterior, $anoAtual];

        $fromEscola = $this->resolveFromEscola();

        $query = LegacySchool::query()->orderBy('cod_escola');
        if ($fromEscola !== null) {
            $query->where('cod_escola', '>=', $fromEscola);
        }

        $schools = $query->get(['cod_escola', 'ref_cod_instituicao']);

        if ($schools->isEmpty()) {
            $this->command?->warn('Nenhuma escola encontrada. Execute o setup em um ambiente com escolas cadastradas.');

            return;
        }

        $totalEscolas = $schools->count();
        $this->step(sprintf(
            'Início (anos %d e %d, %d escolas%s). Cada escola/curso/etapa e pós-processamento por instituição são listados abaixo. Trace extra: CONFIGURACAO_ESCOLAR_SEEDER_TRACE=true ou `php artisan db:seed ... -v`.',
            $anoAnterior,
            $anoAtual,
            $totalEscolas,
            $fromEscola !== null ? ", a partir da escola {$fromEscola}" : ''
        ));

        $cursosEsperados = count($this->definicaoCursosBnccEtapas());
        $this->escolasJaConfiguradasMapa = $this->mapaEscolasJaConfiguradas(
            $schools->pluck('cod_escola')->map(fn ($id) => (int) $id)->all(),
            $anoAtual,
            $cursosEsperados
        );

        foreach ($schools->pluck('ref_cod_instituicao')->unique()->filter() as $instituicaoId) {
            $this->prepararInstituicaoBncc((int) $instituicaoId);
        }

        $indice = 0;
        $puladas = 0;
        foreach ($schools as $school) {
            /** @var LegacySchool $school */
            $indice++;
            $instituicaoId = $school->ref_cod_instituicao;

            if (isset($this->escolasJaConfiguradasMapa[(int) $school->cod_escola])) {
                $puladas++;
                $this->relatorioEtapa(
                    sprintf('Escola %d/%d | cod_escola=%s', $indice, $totalEscolas, $school->cod_escola),
                    'PULADA',
                    sprintf('Já configurada para o ano %d (anos letivos + vínculos BNCC); sem alterações.', $anoAtual)
                );
                $this->trace(sprintf(
                    'Escola %d/%d cod_escola=%s — já configurada para %d, pulando.',
                    $indice,
                    $totalEscolas,
                    $school->cod_escola,
                    $anoAtual
                ));

                continue;
            }

            $this->relatorioEtapa(
                sprintf('Escola %d/%d | cod_escola=%s | instituicao=%s', $indice, $totalEscolas, $school->cod_escola, $instituicaoId ?? 'null'),
                'EM CURSO',
                'Criar anos letivos, cursos BNCC, séries, regras por ano, vínculos escola-curso-série e disciplinas (sem turmas).'
            );
            $this->trace(sprintf(
                'Escola %d/%d cod_escola=%s instituicao=%s — criar anos + cursos/vínculos',
                $indice,
                $totalEscolas,
                $school->cod_escola,
                $instituicaoId ?? 'null'
            ));

            $this->criarAnosLetivos($school, $anos);
            $this->criarCursosEVinculosSemTurmas($school, $instituicaoId, $anos);

            if ($indice % 25 === 0 || $indice === $totalEscolas) {
                $this->command?->info(sprintf(
                    'Escolas processadas: %d/%d (última cod_escola=%s).',
                    $indice,
                    $totalEscolas,
                    $school->cod_escola
                ));
            }

            $this->relatorioEtapa(
                sprintf('Escola %d/%d | cod_escola=%s', $indice, $totalEscolas, $school->cod_escola),
                'OK',
                'Ciclo desta escola concluído (anos + cursos/etapas/disciplinas).'
            );
        }

        if ($puladas > 0) {
            $this->step(sprintf('%d escola(s) já configurada(s) foram puladas.', $puladas));
        }
        $this->step(sprintf('Loop por escola concluído em %.2fs.', microtime(true) - $tInicio));

        $instituicoes = $schools->pluck('ref_cod_instituicao')->unique()->filter()->values();
        $tInst = microtime(true);
        foreach ($instituicoes as $instituicaoId) {
            $this->relatorioEtapa(
                sprintf('Instituição cod_instituicao=%s', $instituicaoId),
                'EM CURSO',
                'Garantir sequências de série → série (BNCC) para validação de matrícula em série seguinte.'
            );
            $this->trace('Instituição '.$instituicaoId.' — sequências BNCC (enturmação)');
            $this->garantirSequenciasEnturmacaoBncc((int) $instituicaoId);
            $this->relatorioEtapa(
                sprintf('Instituição cod_instituicao=%s', $instituicaoId),
                'OK',
                'Sequências BNCC (serie_origem → serie_destino) atualizadas.'
            );

            $this->relatorioEtapa(
                sprintf('Instituição cod_instituicao=%s', $instituicaoId),
                'EM CURSO',
                'Sincronizar pmieducar.escola_serie_disciplina para todas as escolas/séries/componentes da instituição.'
            );
            $this->trace('Instituição '.$instituicaoId.' — sincronizar escola_serie_disciplina');
            $this->garantirEscolaSerieDisciplinasInstituicao((int) $instituicaoId, $anos);
            $this->relatorioEtapa(
                sprintf('Instituição cod_instituicao=%s', $instituicaoId),
                'OK',
                'Sincronização escola_serie_disciplina concluída para esta instituição.'
            );
        }
        $this->step(sprintf(
            'Pós-processamento por instituição concluído em %.2fs.',
            microtime(true) - $tInst
        ));

        $this->habilitarBloqueioMatriculaSerieNaoSeguinte($instituicoes->all());

        $this->step(sprintf('Finalizado em %.2fs.', microtime(true) - $tInicio));
    }

    /**
     * Env `CONFIGURACAO_ESCOLAR_FROM_ESCOLA=123` permite retomar a partir de uma escola específica.
     * Útil quando o seed falhou no meio de centenas de escolas.
     */
    private function resolveFromEscola(): ?int
    {
        $val = env('CONFIGURACAO_ESCOLAR_FROM_ESCOLA');
        if ($val === null || $val === '' || $val === false) {
            return null;
        }

        $id = (int) $val;
        $this->step("Variável CONFIGURACAO_ESCOLAR_FROM_ESCOLA={$id} detectada — escolas anteriores serão ignoradas.");

        return $id;
    }

    /**
     * Cursos BNCC com etapas (séries) em ordem — usado em cursos/turmas e na sequência de enturmação.
     *
     * @return array<string, array{qtd_etapas: int, grades: list<string>}>
     */
    /**
     * @param  list<string>|null  $apenasCursos  Quando informado (ex.: complemento EM), etapas são sempre processadas.
     */
    private function omitirEtapasEnsinoMedioNoFluxoPadrao(string $nomeCurso, ?array $apenasCursos = null): bool
    {
        if ($apenasCursos !== null) {
            return false;
        }

        if ($nomeCurso !== 'Ensino Médio') {
            return false;
        }

        if (!class_exists(\iEducar\Packages\BuritiSetup\Shared\IeducarSetupContext::class)) {
            return false;
        }

        return \iEducar\Packages\BuritiSetup\Shared\IeducarSetupContext::ensinoMedioApenasCursoNoFluxoPadrao();
    }

    /**
     * @return array<string, array{qtd_etapas: int, grades: list<string>}>
     */
    private function definicaoCursosBnccEtapas(): array
    {
        return [
            'Educação Infantil' => [
                'qtd_etapas' => 4,
                'grades' => ['Berçário I', 'Berçário II', 'Maternal', 'Pré-Escolar'],
            ],
            'Ensino Fundamental' => [
                'qtd_etapas' => 9,
                'grades' => ['1º ano', '2º ano', '3º ano', '4º ano', '5º ano', '6º ano', '7º ano', '8º ano', '9º ano'],
            ],
            'Ensino Médio' => [
                'qtd_etapas' => 3,
                'grades' => ['1º ano', '2º ano', '3º ano'],
            ],
        ];
    }

    /**
     * Liga cada série à próxima dentro do mesmo curso (Berçário I → … → Pré-Escolar, 1º ano → … → 9º ano, etc.),
     * para o i-Educar validar matrículas quando a instituição bloqueia série não sequente.
     */
    /**
     * @param  list<string>|null  $apenasCursos  null = todos os cursos BNCC; lista = só esses nomes de curso.
     */
    private function garantirSequenciasEnturmacaoBncc(int $instituicaoId, ?array $apenasCursos = null): void
    {
        $this->prepararInstituicaoBncc($instituicaoId);

        foreach ($this->definicaoCursosBnccEtapas() as $nomeCurso => $meta) {
            if ($apenasCursos !== null && !in_array($nomeCurso, $apenasCursos, true)) {
                continue;
            }

            if ($this->omitirEtapasEnsinoMedioNoFluxoPadrao($nomeCurso, $apenasCursos)) {
                $this->relatorioEtapa(
                    sprintf('Instituição %d | Curso «%s» | Sequências enturmação', $instituicaoId, $nomeCurso),
                    'IGNORADA',
                    'Ensino Médio no fluxo padrão: apenas curso; use ieducar:ensino-medio para séries e sequência.'
                );

                continue;
            }
            $this->relatorioEtapa(
                sprintf('Instituição %d | Curso «%s» | Sequências enturmação', $instituicaoId, $nomeCurso),
                'EM CURSO',
                'Localizar curso e séries BNCC; criar/atualizar pmieducar.sequencia_serie_origem_destino (origem→destino).'
            );

            $curso = $this->cursosBnccPorInstituicao[$instituicaoId][$nomeCurso] ?? null;

            if ($curso === null) {
                $this->relatorioEtapa(
                    sprintf('Instituição %d | Curso «%s» | Sequências enturmação', $instituicaoId, $nomeCurso),
                    'IGNORADA',
                    'Curso não encontrado nesta instituição; sem sequências a gerar.'
                );

                continue;
            }

            $seriesCurso = $this->seriesBnccPorInstituicao[$instituicaoId][$curso->cod_curso] ?? [];
            $serieIds = [];
            foreach ($meta['grades'] as $nmSerie) {
                $grade = $seriesCurso[$nmSerie] ?? null;
                if ($grade !== null) {
                    $serieIds[] = (int) $grade->cod_serie;
                }
            }

            $sequenciasUpsert = [];
            $pares = 0;
            for ($i = 0, $n = count($serieIds); $i < $n - 1; $i++) {
                $sequenciasUpsert[] = [
                    'ref_serie_origem' => $serieIds[$i],
                    'ref_serie_destino' => $serieIds[$i + 1],
                    'ref_usuario_cad' => self::USUARIO_CAD,
                    'ativo' => 1,
                    'data_cadastro' => now(),
                ];
                $pares++;
                $this->trace(sprintf(
                    'Instituição %d | %s | Ligação série %d → série %d',
                    $instituicaoId,
                    $nomeCurso,
                    $serieIds[$i],
                    $serieIds[$i + 1]
                ));
            }

            if ($sequenciasUpsert !== []) {
                DB::table('pmieducar.sequencia_serie')->upsert(
                    $sequenciasUpsert,
                    ['ref_serie_origem'],
                    ['ref_serie_destino', 'ref_usuario_cad', 'ativo', 'data_cadastro']
                );
            }

            $this->relatorioEtapa(
                sprintf('Instituição %d | Curso «%s» | Sequências enturmação', $instituicaoId, $nomeCurso),
                'OK',
                sprintf('%d ligação(ões) série→série garantida(s) (%d séries encontradas).', $pares, count($serieIds))
            );
        }
    }

    /**
     * @param  array<int|string>  $codigosInstituicao
     */
    private function habilitarBloqueioMatriculaSerieNaoSeguinte(array $codigosInstituicao): void
    {
        $ids = array_values(array_filter(array_map('intval', $codigosInstituicao)));
        if ($ids === []) {
            return;
        }

        DB::table('pmieducar.instituicao')
            ->whereIn('cod_instituicao', $ids)
            ->update(['bloqueia_matricula_serie_nao_seguinte' => true]);
    }

    /** @param array<int> $anos */
    private function criarAnosLetivos(LegacySchool $school, array $anos): void
    {
        foreach ($anos as $ano) {
            $this->relatorioEtapa(
                sprintf('Escola cod_escola=%s | Ano letivo %d', $school->cod_escola, $ano),
                'EM CURSO',
                'Garantir registo em escola_ano_letivo (firstOrCreate, andamento em curso, ativo).'
            );

            $anoLetivo = LegacySchoolAcademicYear::firstOrCreate(
                [
                    'ref_cod_escola' => $school->cod_escola,
                    'ano' => $ano,
                ],
                [
                    'ref_usuario_cad' => self::USUARIO_CAD,
                    'andamento' => LegacySchoolAcademicYear::IN_PROGRESS,
                    'ativo' => 1,
                ]
            );

            $this->relatorioEtapa(
                sprintf('Escola cod_escola=%s | Ano letivo %d', $school->cod_escola, $ano),
                'OK',
                $anoLetivo->wasRecentlyCreated
                    ? 'Ano letivo criado.'
                    : 'Ano letivo já existia; registo mantido/confirmado.'
            );
        }
    }

    /**
     * Instalações só com migrations costumam não ter nível/tipo de ensino nem regime na instituição.
     * Sem isso, não é possível criar cursos no setup.
     */
    private function garantirNivelTipoRegimeEnsino(int $instituicaoId): void
    {
        if (!LegacyEducationLevel::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->exists()) {
            LegacyEducationLevel::query()->create([
                'ref_cod_instituicao' => $instituicaoId,
                'ref_usuario_cad' => self::USUARIO_CAD,
                'nm_nivel' => 'Educação Básica',
                'descricao' => 'Nível gerado automaticamente pelo ConfiguracaoEscolarSeeder.',
                'ativo' => 1,
                'data_cadastro' => now(),
            ]);
            $this->command?->info("Instituição {$instituicaoId}: nível de ensino \"Educação Básica\" criado.");
        }

        if (!LegacyEducationType::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->exists()) {
            LegacyEducationType::query()->create([
                'ref_cod_instituicao' => $instituicaoId,
                'ref_usuario_cad' => self::USUARIO_CAD,
                'nm_tipo' => 'Escolar',
                'ativo' => 1,
                'atividade_complementar' => false,
                'data_cadastro' => now(),
            ]);
            $this->command?->info("Instituição {$instituicaoId}: tipo de ensino \"Escolar\" criado.");
        }

        if (!LegacyRegimeType::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->exists()) {
            LegacyRegimeType::query()->create([
                'ref_cod_instituicao' => $instituicaoId,
                'ref_usuario_cad' => self::USUARIO_CAD,
                'nm_tipo' => 'Seriado',
                'ativo' => 1,
                'data_cadastro' => now(),
            ]);
            $this->command?->info("Instituição {$instituicaoId}: tipo de regime \"Seriado\" criado.");
        }
    }

    /**
     * Sem turnos ou tipo de turma o seeder não chama criarTurmas(); a intranet monta o filtro "Ano"
     * só com DISTINCT em pmieducar.turma (ativo=1), resultando em "Indisponível" se não houver turmas.
     */
    private function garantirTipoTurmaETurno(int $instituicaoId): void
    {
        if (!LegacyPeriod::query()->exists()) {
            DB::table('pmieducar.turma_turno')->insert([
                ['id' => 1, 'nome' => 'Matutino', 'ativo' => 1],
                ['id' => 2, 'nome' => 'Vespertino', 'ativo' => 1],
                ['id' => 3, 'nome' => 'Noturno', 'ativo' => 1],
                ['id' => 4, 'nome' => 'Integral', 'ativo' => 1],
            ]);
            DB::statement(
                "SELECT setval('pmieducar.turma_turno_id_seq', (SELECT COALESCE(MAX(id), 1) FROM pmieducar.turma_turno), true)"
            );
            $this->command?->info('Turnos padrão (Matutino, Vespertino, Noturno, Integral) criados em pmieducar.turma_turno.');
        }

        $temTipo = LegacySchoolClassType::query()
            ->where('ativo', 1)
            ->where(function ($q) use ($instituicaoId) {
                $q->where('ref_cod_instituicao', $instituicaoId)
                    ->orWhereNull('ref_cod_instituicao');
            })
            ->exists();

        if (!$temTipo) {
            DB::table('pmieducar.turma_tipo')->insert([
                'ref_usuario_cad' => self::USUARIO_CAD,
                'nm_tipo' => 'Regular',
                'sgl_tipo' => 'REG',
                'data_cadastro' => now(),
                'ativo' => 1,
                'ref_cod_instituicao' => $instituicaoId,
            ]);
            $this->command?->info("Instituição {$instituicaoId}: tipo de turma \"Regular\" criado.");
        }
    }

    /**
     * @param  array<int>  $anos
     * @param  list<string>|null  $apenasCursos  null = todos; lista = processar só esses cursos (criar curso + etapas).
     */
    private function criarCursosEVinculosSemTurmas(LegacySchool $school, int $instituicaoId, array $anos, ?array $apenasCursos = null): void
    {
        $this->prepararInstituicaoBncc($instituicaoId);

        $regraAvaliacao = $this->regraAvaliacaoPorInstituicao[$instituicaoId] ?? null;

        if (!$regraAvaliacao) {
            $this->relatorioEtapa(
                sprintf('Escola cod_escola=%s | Instituição %d', $school->cod_escola, $instituicaoId),
                'AVISO',
                'Sem regra de avaliação na instituição; cursos/séries/disciplinas não serão gerados. Cadastre em Cadastros > Regras de avaliação.'
            );
            $this->command?->warn("Instituição {$instituicaoId} sem regra de avaliação. Configure em Cadastros > Regras de avaliação.");

            return;
        }

        $turmaTipo = LegacySchoolClassType::query()
            ->where('ativo', 1)
            ->where(function ($q) use ($instituicaoId) {
                $q->where('ref_cod_instituicao', $instituicaoId)
                    ->orWhereNull('ref_cod_instituicao');
            })
            ->orderByRaw('CASE WHEN ref_cod_instituicao = ? THEN 0 WHEN ref_cod_instituicao IS NULL THEN 1 ELSE 2 END', [$instituicaoId])
            ->first();

        $turmaTurno = LegacyPeriod::query()->where('ativo', 1)->orderBy('id')->first();

        if (!$turmaTipo || !$turmaTurno) {
            $this->relatorioEtapa(
                sprintf('Escola cod_escola=%s | Instituição %d', $school->cod_escola, $instituicaoId),
                'AVISO',
                'Tipo ou turno de turma indisponível após garantia automática; não é possível preparar turmas (este seeder não cria turmas).'
            );
            $this->command?->warn(
                "Instituição {$instituicaoId}: tipo ou turno de turma indisponível após garantia automática; turmas não serão geradas."
            );

            return;
        }

        $disciplinasPorCurso = AreaConhecimentoBnccSeeder::getDisciplinasPorCurso();

        foreach ($this->definicaoCursosBnccEtapas() as $nomeCurso => $meta) {
            if ($apenasCursos !== null && !in_array($nomeCurso, $apenasCursos, true)) {
                continue;
            }

            $config = array_merge($meta, [
                'disciplinas' => $disciplinasPorCurso[$nomeCurso],
            ]);
            $curso = $this->cursosBnccPorInstituicao[$instituicaoId][$nomeCurso] ?? null;

            $cursoJaExistia = $curso !== null;

            $this->relatorioEtapa(
                sprintf('Escola cod_escola=%s | Inst %d | Curso «%s»', $school->cod_escola, $instituicaoId, $nomeCurso),
                'EM CURSO',
                $cursoJaExistia
                    ? 'Curso já existente na instituição; vincular à escola e processar cada etapa (série) BNCC.'
                    : 'Criar curso na instituição, vincular à escola e processar cada etapa (série) BNCC.'
            );

            if (!$curso) {
                $cadastros = $this->cadastrosEnsinoPorInstituicao[$instituicaoId] ?? null;
                $nivel = $cadastros['nivel'] ?? null;
                $tipo = $cadastros['tipo'] ?? null;
                $regime = $cadastros['regime'] ?? null;

                if (!$nivel || !$tipo) {
                    $this->relatorioEtapa(
                        sprintf('Escola cod_escola=%s | Inst %d | Curso «%s»', $school->cod_escola, $instituicaoId, $nomeCurso),
                        'AVISO',
                        'Instituição sem nível ou tipo de ensino; não é possível criar o curso. Configure em Cadastros.'
                    );
                    $this->command?->warn("Instituição {$instituicaoId} sem nível de ensino ou tipo de ensino. Configure em Cadastros.");

                    continue;
                }

                $curso = LegacyCourse::create([
                    'nm_curso' => $nomeCurso,
                    'descricao' => $nomeCurso,
                    'sgl_curso' => substr($nomeCurso, 0, 10),
                    'ref_cod_instituicao' => $instituicaoId,
                    'ref_cod_nivel_ensino' => $nivel->cod_nivel_ensino,
                    'ref_cod_tipo_ensino' => $tipo->cod_tipo_ensino,
                    'ref_cod_tipo_regime' => $regime?->cod_tipo_regime,
                    'qtd_etapas' => $config['qtd_etapas'],
                    'carga_horaria' => 800,
                    'hora_falta' => 0.75,
                    'padrao_ano_escolar' => true,
                    'ref_usuario_cad' => self::USUARIO_CAD,
                    'data_cadastro' => now(),
                    'ativo' => 1,
                ]);
                $this->cursosBnccPorInstituicao[$instituicaoId][$nomeCurso] = $curso;
                $this->relatorioEtapa(
                    sprintf('Escola cod_escola=%s | Inst %d | Curso «%s»', $school->cod_escola, $instituicaoId, $nomeCurso),
                    'OK',
                    sprintf('Curso criado (cod_curso=%d).', $curso->cod_curso)
                );
            }

            $curso->update(['padrao_ano_escolar' => true]);
            $this->vincularCursoEscola($school, $curso, $anos);

            if ($this->omitirEtapasEnsinoMedioNoFluxoPadrao($nomeCurso, $apenasCursos)) {
                $this->relatorioEtapa(
                    sprintf('Escola cod_escola=%s | Inst %d | Curso «%s»', $school->cod_escola, $instituicaoId, $nomeCurso),
                    'OK',
                    'Curso e vínculo escola-curso garantidos; séries e disciplinas ficam para ieducar:ensino-medio.'
                );

                continue;
            }

            $totalEtapasCurso = count($config['grades']);
            foreach ($config['grades'] as $etapa => $nmSerie) {
                $this->relatorioEtapa(
                    sprintf(
                        'Escola cod_escola=%s | «%s» | Etapa %d/%d «%s»',
                        $school->cod_escola,
                        $nomeCurso,
                        $etapa + 1,
                        $totalEtapasCurso,
                        $nmSerie
                    ),
                    'EM CURSO',
                    'Garantir série (pmieducar.serie), regra por ano letivo, vínculo escola_serie e disciplinas BNCC (componente_curricular_ano_escolar + escola_serie_disciplina).'
                );

                $grade = $this->seriesBnccPorInstituicao[$instituicaoId][$curso->cod_curso][$nmSerie] ?? null;

                $serieJaExistia = $grade !== null;

                if (!$grade) {
                    $grade = LegacyGrade::create([
                        'ref_cod_curso' => $curso->cod_curso,
                        'nm_serie' => $nmSerie,
                        'etapa_curso' => $etapa + 1,
                        'carga_horaria' => 800,
                        'dias_letivos' => 200,
                        'ref_usuario_cad' => self::USUARIO_CAD,
                        'data_cadastro' => now(),
                        'concluinte' => ($etapa + 1) === $config['qtd_etapas'] ? 2 : 1,
                        'ativo' => 1,
                    ]);
                    $this->seriesBnccPorInstituicao[$instituicaoId][$curso->cod_curso][$nmSerie] = $grade;
                }

                $this->vincularRegrasAvaliacaoSerieAnos($grade, $regraAvaliacao, $anos);
                $this->vincularSerieEscola($school, $grade, $anos);
                $this->vincularDisciplinasSerie($school, $grade, $config['disciplinas'], $instituicaoId, $anos);

                $qDisc = count($config['disciplinas']);
                $this->relatorioEtapa(
                    sprintf(
                        'Escola cod_escola=%s | «%s» | Etapa %d/%d «%s»',
                        $school->cod_escola,
                        $nomeCurso,
                        $etapa + 1,
                        $totalEtapasCurso,
                        $nmSerie
                    ),
                    'OK',
                    sprintf(
                        'cod_serie=%d; série %s; %d ano(s) com regra; %d disciplina(s) BNCC processadas.',
                        $grade->cod_serie,
                        $serieJaExistia ? 'já existia' : 'criada',
                        count($anos),
                        $qDisc
                    )
                );
            }

            $this->relatorioEtapa(
                sprintf('Escola cod_escola=%s | Inst %d | Curso «%s»', $school->cod_escola, $instituicaoId, $nomeCurso),
                'OK',
                'Curso e todas as etapas (séries) concluídos para esta escola.'
            );
        }
    }

    /** @param array<int> $anos */
    private function vincularCursoEscola(LegacySchool $school, LegacyCourse $curso, array $anos): void
    {
        LegacySchoolCourse::updateOrCreate(
            [
                'ref_cod_escola' => $school->cod_escola,
                'ref_cod_curso' => $curso->cod_curso,
            ],
            [
                'ref_usuario_cad' => self::USUARIO_CAD,
                'data_cadastro' => now(),
                'ativo' => 1,
                'anos_letivos' => $this->anosLetivosParaPg($anos),
            ]
        );
    }

    /**
     * @param  array<int>  $anos
     */
    private function vincularRegrasAvaliacaoSerieAnos(LegacyGrade $grade, LegacyEvaluationRule $regra, array $anos): void
    {
        $linhas = [];
        foreach ($anos as $ano) {
            $linhas[] = [
                'serie_id' => $grade->cod_serie,
                'ano_letivo' => (int) $ano,
                'regra_avaliacao_id' => $regra->id,
            ];
        }

        if ($linhas === []) {
            return;
        }

        DB::table('modules.regra_avaliacao_serie_ano')->upsert(
            $linhas,
            ['serie_id', 'ano_letivo'],
            ['regra_avaliacao_id']
        );
    }

    /** @param array<int> $anos */
    private function vincularSerieEscola(LegacySchool $school, LegacyGrade $grade, array $anos): void
    {
        LegacySchoolGrade::updateOrCreate(
            [
                'ref_cod_escola' => $school->cod_escola,
                'ref_cod_serie' => $grade->cod_serie,
            ],
            [
                'ref_usuario_cad' => self::USUARIO_CAD,
                'data_cadastro' => now(),
                'anos_letivos' => $this->anosLetivosParaPg($anos),
            ]
        );
    }

    /**
     * A view relatorio.view_componente_curricular (usada em turma/série) exige pmieducar.escola_serie_disciplina
     * com o ano da turma em anos_letivos; só modules.componente_curricular_ano_escolar não basta.
     *
     * @param array<int, array{nome: string, area: string}> $disciplinasConfig Cada item: ['nome' => string, 'area' => string]
     * @param array<int> $anos
     */
    /**
     * @param  array<int, array{nome: string, area: string}>  $disciplinasConfig
     * @param  array<int>  $anos
     */
    private function vincularDisciplinasSerie(LegacySchool $school, LegacyGrade $grade, array $disciplinasConfig, int $instituicaoId, array $anos): void
    {
        $anosPg = $this->anosLetivosParaPg($anos);
        $nomesBncc = [];
        $linhasCcae = [];
        $linhasEsd = [];

        $existentesEsd = LegacySchoolGradeDiscipline::query()
            ->where('ref_ref_cod_escola', $school->cod_escola)
            ->where('ref_ref_cod_serie', $grade->cod_serie)
            ->get()
            ->keyBy('ref_cod_disciplina');

        foreach ($disciplinasConfig as $config) {
            $nome = $config['nome'];
            $nomesBncc[] = $nome;
            $disciplina = $this->obterDisciplinaBncc($instituicaoId, $nome, $config['area']);
            $codDisciplina = (int) $disciplina->id;

            $linhasCcae[] = [
                'componente_curricular_id' => $codDisciplina,
                'ano_escolar_id' => $grade->cod_serie,
                'carga_horaria' => 40,
                'tipo_nota' => 1,
                'anos_letivos' => $anosPg,
            ];

            $existing = $existentesEsd->get($codDisciplina);
            $linhasEsd[] = [
                'ref_ref_cod_escola' => $school->cod_escola,
                'ref_ref_cod_serie' => $grade->cod_serie,
                'ref_cod_disciplina' => $codDisciplina,
                'ativo' => 1,
                'carga_horaria' => 40,
                'etapas_especificas' => $existing?->etapas_especificas ?? 0,
                'etapas_utilizadas' => $existing?->etapas_utilizadas ?? '',
                'anos_letivos' => $this->mesclarAnosLetivosParaCampoPostgres($existing?->anos_letivos, $anosPg, $anos),
            ];

            $this->escolaSerieDisciplinaJaSincronizados[$this->chaveEscolaSerieDisciplina(
                (int) $school->cod_escola,
                (int) $grade->cod_serie,
                $codDisciplina
            )] = true;
        }

        if ($linhasCcae !== []) {
            DB::table('modules.componente_curricular_ano_escolar')->upsert(
                $linhasCcae,
                ['componente_curricular_id', 'ano_escolar_id'],
                ['carga_horaria', 'tipo_nota', 'anos_letivos']
            );
        }

        foreach (array_chunk($linhasEsd, self::ESCOLA_SERIE_DISCIPLINA_UPSERT_CHUNK) as $chunk) {
            DB::table('pmieducar.escola_serie_disciplina')->upsert(
                $chunk,
                ['ref_ref_cod_serie', 'ref_ref_cod_escola', 'ref_cod_disciplina'],
                ['ativo', 'carga_horaria', 'etapas_especificas', 'etapas_utilizadas', 'anos_letivos']
            );
        }

        $this->desativarComponentesNaoBncc($grade, $nomesBncc, $instituicaoId);
    }

    /**
     * Desativa componentes curriculares existentes que não estão na lista BNCC,
     * removendo-os do vínculo com a série para que não possam ser usados novamente.
     *
     * @param array<string> $nomesDisciplinasBncc Lista de nomes das disciplinas BNCC
     */
    private function desativarComponentesNaoBncc(LegacyGrade $grade, array $nomesDisciplinasBncc, int $instituicaoId): void
    {
        $nomesBncc = array_map('mb_strtolower', $nomesDisciplinasBncc);

        $componentesVinculados = LegacyDisciplineAcademicYear::query()
            ->where('ano_escolar_id', $grade->cod_serie)
            ->pluck('componente_curricular_id');

        if ($componentesVinculados->isEmpty()) {
            return;
        }

        $disciplinasVinculadas = LegacyDiscipline::query()
            ->where('instituicao_id', $instituicaoId)
            ->whereIn('id', $componentesVinculados)
            ->get(['id', 'nome']);

        $componentesNaoBncc = $disciplinasVinculadas
            ->filter(fn ($d) => !in_array(mb_strtolower($d->nome), $nomesBncc))
            ->pluck('id');

        if ($componentesNaoBncc->isNotEmpty()) {
            LegacyDisciplineAcademicYear::query()
                ->where('ano_escolar_id', $grade->cod_serie)
                ->whereIn('componente_curricular_id', $componentesNaoBncc)
                ->delete();

            LegacySchoolGradeDiscipline::query()
                ->where('ref_ref_cod_serie', $grade->cod_serie)
                ->whereIn('ref_cod_disciplina', $componentesNaoBncc)
                ->delete();

            $this->command?->info(
                "Componentes curriculares desativados da série '{$grade->nm_serie}': " . $componentesNaoBncc->count()
            );
        }
    }

    /**
     * Garante escola_serie_disciplina para toda escola/série da instituição que já tenha vínculo em componente_curricular_ano_escolar.
     * Cobre cursos fora do padrão BNCC do loop principal e bases já parcialmente configuradas.
     *
     * Otimizado: consultas em lote + upsert (evita N+1 por escola/série/disciplina).
     *
     * @param  array<int>  $anos
     */
    private function garantirEscolaSerieDisciplinasInstituicao(int $instituicaoId, array $anos): void
    {
        $t0 = microtime(true);

        $escolaIds = LegacySchool::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->pluck('cod_escola')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($escolaIds === []) {
            return;
        }

        $disciplinasDaInstituicao = LegacyDiscipline::query()
            ->where('instituicao_id', $instituicaoId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $vinculosEscolaSerie = LegacySchoolGrade::query()
            ->whereIn('ref_cod_escola', $escolaIds)
            ->where('ativo', 1)
            ->get(['ref_cod_escola', 'ref_cod_serie']);

        if ($vinculosEscolaSerie->isEmpty()) {
            return;
        }

        $serieIds = $vinculosEscolaSerie->pluck('ref_cod_serie')->unique()->map(fn ($id) => (int) $id)->values()->all();

        /** @var array<int, array<int, LegacyDisciplineAcademicYear>> $ccaePorSerie */
        $ccaePorSerie = [];
        LegacyDisciplineAcademicYear::query()
            ->whereIn('ano_escolar_id', $serieIds)
            ->get()
            ->each(function (LegacyDisciplineAcademicYear $ccae) use (&$ccaePorSerie, $disciplinasDaInstituicao) {
                $codDisciplina = (int) $ccae->componente_curricular_id;
                if (!isset($disciplinasDaInstituicao[$codDisciplina])) {
                    return;
                }
                $ccaePorSerie[(int) $ccae->ano_escolar_id][$codDisciplina] = $ccae;
            });

        $existentes = LegacySchoolGradeDiscipline::query()
            ->whereIn('ref_ref_cod_escola', $escolaIds)
            ->get()
            ->keyBy(fn (LegacySchoolGradeDiscipline $r) => $this->chaveEscolaSerieDisciplina(
                (int) $r->ref_ref_cod_escola,
                (int) $r->ref_ref_cod_serie,
                (int) $r->ref_cod_disciplina
            ));

        $linhasUpsert = [];
        $puladosJaSincronizados = 0;

        foreach ($vinculosEscolaSerie as $vinculo) {
            $codEscola = (int) $vinculo->ref_cod_escola;
            $codSerie = (int) $vinculo->ref_cod_serie;
            $componentes = $ccaePorSerie[$codSerie] ?? [];

            foreach ($componentes as $codDisciplina => $ccae) {
                $chave = $this->chaveEscolaSerieDisciplina($codEscola, $codSerie, $codDisciplina);
                if (isset($this->escolaSerieDisciplinaJaSincronizados[$chave])) {
                    $puladosJaSincronizados++;

                    continue;
                }

                $existing = $existentes->get($chave);
                $carga = (int) ($ccae->carga_horaria ?: 40);
                $anosPg = $this->mesclarAnosLetivosParaCampoPostgres(
                    $existing?->anos_letivos,
                    $ccae->anos_letivos,
                    $anos
                );

                $linhasUpsert[] = [
                    'ref_ref_cod_escola' => $codEscola,
                    'ref_ref_cod_serie' => $codSerie,
                    'ref_cod_disciplina' => $codDisciplina,
                    'ativo' => 1,
                    'carga_horaria' => $carga,
                    'etapas_especificas' => $existing?->etapas_especificas ?? 0,
                    'etapas_utilizadas' => $existing?->etapas_utilizadas ?? '',
                    'anos_letivos' => $anosPg,
                ];
            }
        }

        if ($linhasUpsert === []) {
            $this->trace(sprintf(
                'Instituição %d: escola_serie_disciplina — nada pendente (%d já sincronizados no loop por escola).',
                $instituicaoId,
                $puladosJaSincronizados
            ));

            return;
        }

        foreach (array_chunk($linhasUpsert, self::ESCOLA_SERIE_DISCIPLINA_UPSERT_CHUNK) as $chunk) {
            DB::table('pmieducar.escola_serie_disciplina')->upsert(
                $chunk,
                ['ref_ref_cod_serie', 'ref_ref_cod_escola', 'ref_cod_disciplina'],
                ['ativo', 'carga_horaria', 'etapas_especificas', 'etapas_utilizadas', 'anos_letivos']
            );
        }

        $this->trace(sprintf(
            'Instituição %d: escola_serie_disciplina — %d upsert(s), %d pulado(s) (já no loop), %.2fs.',
            $instituicaoId,
            count($linhasUpsert),
            $puladosJaSincronizados,
            microtime(true) - $t0
        ));
    }

    private function chaveEscolaSerieDisciplina(int $codEscola, int $codSerie, int $codDisciplina): string
    {
        return "{$codEscola}:{$codSerie}:{$codDisciplina}";
    }

    /**
     * @param array<int> $anosExtras
     */
    private function mesclarAnosLetivosParaCampoPostgres(mixed $anosEsd, mixed $anosCcae, array $anosExtras): string
    {
        $unidos = [];

        foreach ([$anosEsd, $anosCcae] as $origem) {
            if ($origem === null || $origem === '') {
                continue;
            }
            if (is_array($origem)) {
                $unidos = array_merge($unidos, $origem);

                continue;
            }
            if (is_string($origem)) {
                $unidos = array_merge($unidos, transformStringFromDBInArray($origem) ?? []);
            }
        }

        $unidos = array_merge($unidos, $anosExtras);
        $unidos = array_values(array_unique(array_map('intval', array_filter($unidos, fn ($v) => $v !== null && $v !== ''))));
        sort($unidos);

        return '{' . implode(',', $unidos) . '}';
    }

    /**
     * Pré-carrega cadastros da instituição (uma vez por execução) para evitar N+1 no loop por escola.
     */
    private function prepararInstituicaoBncc(int $instituicaoId): void
    {
        if (isset($this->instituicaoBnccPreparada[$instituicaoId])) {
            return;
        }

        $this->garantirNivelTipoRegimeEnsino($instituicaoId);
        $this->garantirTipoTurmaETurno($instituicaoId);

        $regra = LegacyEvaluationRule::query()
            ->where('instituicao_id', $instituicaoId)
            ->first();
        if ($regra instanceof LegacyEvaluationRule) {
            $this->regraAvaliacaoPorInstituicao[$instituicaoId] = $regra;
        }

        $nivel = LegacyEducationLevel::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->first();
        $tipo = LegacyEducationType::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->first();
        $regime = LegacyRegimeType::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('ativo', 1)
            ->first();

        if ($nivel && $tipo) {
            $this->cadastrosEnsinoPorInstituicao[$instituicaoId] = [
                'nivel' => $nivel,
                'tipo' => $tipo,
                'regime' => $regime,
            ];
        }

        $disciplinasPorCurso = AreaConhecimentoBnccSeeder::getDisciplinasPorCurso();
        $componentesUnicos = [];
        foreach ($disciplinasPorCurso as $lista) {
            foreach ($lista as $item) {
                $componentesUnicos[$item['nome']] = $item;
            }
        }

        $this->disciplinasBnccPorInstituicao[$instituicaoId] = [];
        foreach ($componentesUnicos as $nome => $item) {
            $area = LegacyKnowledgeArea::firstOrCreate(
                ['instituicao_id' => $instituicaoId, 'nome' => $item['area']],
                ['instituicao_id' => $instituicaoId, 'nome' => $item['area']]
            );

            $disciplina = LegacyDiscipline::query()
                ->where('instituicao_id', $instituicaoId)
                ->where('nome', $nome)
                ->first();

            if (!$disciplina) {
                $disciplina = LegacyDiscipline::create([
                    'instituicao_id' => $instituicaoId,
                    'area_conhecimento_id' => $area->id,
                    'nome' => $nome,
                    'abreviatura' => substr($nome, 0, 10),
                    'tipo_base' => 1,
                    'ordenamento' => 1,
                ]);
            } else {
                $disciplina->update(['area_conhecimento_id' => $area->id]);
            }

            $this->disciplinasBnccPorInstituicao[$instituicaoId][mb_strtolower($nome)] = $disciplina;
        }

        $nomesCursos = array_keys($this->definicaoCursosBnccEtapas());
        $this->cursosBnccPorInstituicao[$instituicaoId] = [];
        $this->seriesBnccPorInstituicao[$instituicaoId] = [];

        $cursos = LegacyCourse::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->whereIn('nm_curso', $nomesCursos)
            ->get();

        $codCursos = [];
        foreach ($cursos as $curso) {
            $this->cursosBnccPorInstituicao[$instituicaoId][$curso->nm_curso] = $curso;
            $codCursos[] = $curso->cod_curso;
        }

        if ($codCursos !== []) {
            LegacyGrade::query()
                ->whereIn('ref_cod_curso', $codCursos)
                ->where('ativo', 1)
                ->get()
                ->each(function (LegacyGrade $grade) use ($instituicaoId) {
                    $this->seriesBnccPorInstituicao[$instituicaoId][$grade->ref_cod_curso][$grade->nm_serie] = $grade;
                });
        }

        $this->instituicaoBnccPreparada[$instituicaoId] = true;
        $this->trace(sprintf('Instituição %d: cache BNCC preparado (%d cursos, %d componentes).', $instituicaoId, count($codCursos), count($componentesUnicos)));
    }

    private function obterDisciplinaBncc(int $instituicaoId, string $nome, string $area): LegacyDiscipline
    {
        $this->prepararInstituicaoBncc($instituicaoId);
        $chave = mb_strtolower($nome);
        $disciplina = $this->disciplinasBnccPorInstituicao[$instituicaoId][$chave] ?? null;

        if ($disciplina instanceof LegacyDiscipline) {
            return $disciplina;
        }

        $areaModel = LegacyKnowledgeArea::firstOrCreate(
            ['instituicao_id' => $instituicaoId, 'nome' => $area],
            ['instituicao_id' => $instituicaoId, 'nome' => $area]
        );

        $disciplina = LegacyDiscipline::create([
            'instituicao_id' => $instituicaoId,
            'area_conhecimento_id' => $areaModel->id,
            'nome' => $nome,
            'abreviatura' => substr($nome, 0, 10),
            'tipo_base' => 1,
            'ordenamento' => 1,
        ]);
        $this->disciplinasBnccPorInstituicao[$instituicaoId][$chave] = $disciplina;

        return $disciplina;
    }

    /**
     * @param  array<int>  $codEscolas
     * @return array<int, true>
     */
    private function mapaEscolasJaConfiguradas(array $codEscolas, int $anoAtual, int $cursosEsperados): array
    {
        if ($codEscolas === []) {
            return [];
        }

        $comAnoLetivo = LegacySchoolAcademicYear::query()
            ->whereIn('ref_cod_escola', $codEscolas)
            ->where('ano', $anoAtual)
            ->where('ativo', 1)
            ->pluck('ref_cod_escola')
            ->flip()
            ->all();

        $cursosPorEscola = DB::table('pmieducar.escola_curso')
            ->whereIn('ref_cod_escola', $codEscolas)
            ->where('ativo', 1)
            ->groupBy('ref_cod_escola')
            ->selectRaw('ref_cod_escola, COUNT(*) as total')
            ->pluck('total', 'ref_cod_escola');

        $seriesPorEscola = DB::table('pmieducar.escola_serie')
            ->whereIn('ref_cod_escola', $codEscolas)
            ->where('ativo', 1)
            ->groupBy('ref_cod_escola')
            ->selectRaw('ref_cod_escola, COUNT(*) as total')
            ->pluck('total', 'ref_cod_escola');

        $mapa = [];
        foreach ($codEscolas as $codEscola) {
            if (!isset($comAnoLetivo[$codEscola])) {
                continue;
            }
            if ((int) ($cursosPorEscola[$codEscola] ?? 0) < $cursosEsperados) {
                continue;
            }
            if ((int) ($seriesPorEscola[$codEscola] ?? 0) < 1) {
                continue;
            }
            $mapa[$codEscola] = true;
        }

        return $mapa;
    }

    // A geração de turmas foi movida para um comando opcional (`setup:matriculas`).
}
