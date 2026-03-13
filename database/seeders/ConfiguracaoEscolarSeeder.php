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
use App\Models\LegacyRegimeType;
use App\Models\LegacySchool;
use App\Models\LegacySchoolAcademicYear;
use App\Models\LegacySchoolCourse;
use App\Models\LegacyDisciplineSchoolClass;
use App\Models\LegacySchoolClass;
use App\Models\LegacySchoolGrade;
use App\Models\LegacyPeriod;
use App\Models\LegacySchoolClassType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ConfiguracaoEscolarSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

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

    public function run(): void
    {
        [$anoAnterior, $anoAtual] = $this->obterAnosLetivos();
        $anos = [$anoAnterior, $anoAtual];
        $schools = LegacySchool::all();

        if ($schools->isEmpty()) {
            $this->command?->warn('Nenhuma escola encontrada. Execute o setup em um ambiente com escolas cadastradas.');
            return;
        }

        foreach ($schools as $school) {
            $instituicaoId = $school->ref_cod_instituicao;

            $this->criarAnosLetivos($school, $anos);
            $this->criarCursosETurmas($school, $instituicaoId, $anos);
        }
    }

    /** @param array<int> $anos */
    private function criarAnosLetivos(LegacySchool $school, array $anos): void
    {
        foreach ($anos as $ano) {
            LegacySchoolAcademicYear::firstOrCreate(
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
        }
    }

    /** @param array<int> $anos */
    private function criarCursosETurmas(LegacySchool $school, int $instituicaoId, array $anos): void
    {
        $regraAvaliacao = LegacyEvaluationRule::query()
            ->where('instituicao_id', $instituicaoId)
            ->first();

        if (!$regraAvaliacao) {
            $this->command?->warn("Instituição {$instituicaoId} sem regra de avaliação. Configure em Cadastros > Regras de avaliação.");
            return;
        }

        $turmaTipo = LegacySchoolClassType::query()->first();
        $turmaTurno = LegacyPeriod::query()->first();

        $disciplinasPorCurso = AreaConhecimentoBnccSeeder::getDisciplinasPorCurso();

        $cursosConfig = [
            'Educação Infantil' => [
                'qtd_etapas' => 4,
                'grades' => ['Berçário I', 'Berçário II', 'Maternal', 'Pré-Escolar'],
                'disciplinas' => $disciplinasPorCurso['Educação Infantil'],
            ],
            'Ensino Fundamental' => [
                'qtd_etapas' => 9,
                'grades' => ['1º ano', '2º ano', '3º ano', '4º ano', '5º ano', '6º ano', '7º ano', '8º ano', '9º ano'],
                'disciplinas' => $disciplinasPorCurso['Ensino Fundamental'],
            ],
            'Ensino Médio' => [
                'qtd_etapas' => 3,
                'grades' => ['1º ano', '2º ano', '3º ano'],
                'disciplinas' => $disciplinasPorCurso['Ensino Médio'],
            ],
        ];

        foreach ($cursosConfig as $nomeCurso => $config) {
            $curso = LegacyCourse::query()
                ->where('ref_cod_instituicao', $instituicaoId)
                ->where('nm_curso', $nomeCurso)
                ->first();

            if (!$curso) {
                $nivel = LegacyEducationLevel::query()
                    ->where('ref_cod_instituicao', $instituicaoId)
                    ->first();

                $tipo = LegacyEducationType::query()
                    ->where('ref_cod_instituicao', $instituicaoId)
                    ->first();

                $regime = LegacyRegimeType::query()
                    ->where('ref_cod_instituicao', $instituicaoId)
                    ->first();

                if (!$nivel || !$tipo) {
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
            }

            $curso->update(['padrao_ano_escolar' => true]);
            $this->vincularCursoEscola($school, $curso, $anos);

            foreach ($config['grades'] as $etapa => $nmSerie) {
                $grade = LegacyGrade::query()
                    ->where('ref_cod_curso', $curso->cod_curso)
                    ->where('nm_serie', $nmSerie)
                    ->first();

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
                }

                foreach ($anos as $ano) {
                    $this->vincularRegraAvaliacaoSerie($grade, $regraAvaliacao, $ano);
                }
                $this->vincularSerieEscola($school, $grade, $anos);
                $this->vincularDisciplinasSerie($grade, $config['disciplinas'], $instituicaoId, $anos);

                if ($turmaTipo && $turmaTurno) {
                    $this->criarTurmas($school, $curso, $grade, $anos, $turmaTipo, $turmaTurno);
                }
            }
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

    private function vincularRegraAvaliacaoSerie(LegacyGrade $grade, LegacyEvaluationRule $regra, int $anoAtual): void
    {
        LegacyEvaluationRuleGradeYear::firstOrCreate(
            [
                'serie_id' => $grade->cod_serie,
                'ano_letivo' => $anoAtual,
            ],
            [
                'regra_avaliacao_id' => $regra->id,
            ]
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
     * @param array<int, array{nome: string, area: string}> $disciplinasConfig Cada item: ['nome' => string, 'area' => string]
     * @param array<int> $anos
     */
    private function vincularDisciplinasSerie(LegacyGrade $grade, array $disciplinasConfig, int $instituicaoId, array $anos): void
    {
        $anosPg = $this->anosLetivosParaPg($anos);
        $nomesBncc = [];

        foreach ($disciplinasConfig as $config) {
            $nome = $config['nome'];
            $nomeArea = $config['area'];
            $nomesBncc[] = $nome;

            $areaConhecimento = LegacyKnowledgeArea::firstOrCreate(
                [
                    'instituicao_id' => $instituicaoId,
                    'nome' => $nomeArea,
                ],
                [
                    'instituicao_id' => $instituicaoId,
                    'nome' => $nomeArea,
                ]
            );

            $disciplina = LegacyDiscipline::query()
                ->where('instituicao_id', $instituicaoId)
                ->where('nome', $nome)
                ->first();

            if (!$disciplina) {
                $disciplina = LegacyDiscipline::create([
                    'instituicao_id' => $instituicaoId,
                    'area_conhecimento_id' => $areaConhecimento->id,
                    'nome' => $nome,
                    'abreviatura' => substr($nome, 0, 10),
                    'tipo_base' => 1,
                    'ordenamento' => 1,
                ]);
            } else {
                $disciplina->update(['area_conhecimento_id' => $areaConhecimento->id]);
            }

            LegacyDisciplineAcademicYear::updateOrCreate(
                [
                    'componente_curricular_id' => $disciplina->id,
                    'ano_escolar_id' => $grade->cod_serie,
                ],
                [
                    'carga_horaria' => 40,
                    'tipo_nota' => 1,
                    'anos_letivos' => $anosPg,
                ]
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

            $this->command?->info(
                "Componentes curriculares desativados da série '{$grade->nm_serie}': " . $componentesNaoBncc->count()
            );
        }
    }

    /**
     * Cria turmas para cada série em cada ano letivo (atual e anterior).
     *
     * @param array<int> $anos
     */
    private function criarTurmas(
        LegacySchool $school,
        LegacyCourse $curso,
        LegacyGrade $grade,
        array $anos,
        LegacySchoolClassType $turmaTipo,
        LegacyPeriod $turmaTurno
    ): void {
        $instituicaoId = $school->ref_cod_instituicao;

        foreach ($anos as $ano) {
            $nmTurma = $grade->nm_serie . ' - ' . $ano;
            $sglTurma = mb_substr($grade->nm_serie, 0, 3) . $ano;

            $turma = LegacySchoolClass::firstOrCreate(
                [
                    'ref_ref_cod_escola' => $school->cod_escola,
                    'ref_ref_cod_serie' => $grade->cod_serie,
                    'ref_cod_curso' => $curso->cod_curso,
                    'ano' => $ano,
                ],
                [
                    'ref_usuario_cad' => self::USUARIO_CAD,
                    'nm_turma' => $nmTurma,
                    'sgl_turma' => $sglTurma,
                    'max_aluno' => 40,
                    'ref_cod_turma_tipo' => $turmaTipo->cod_turma_tipo,
                    'turma_turno_id' => $turmaTurno->id,
                    'ref_cod_instituicao' => $instituicaoId,
                    'multiseriada' => false,
                    'visivel' => true,
                    'ativo' => 1,
                    'dias_semana' => [2, 3, 4, 5, 6],
                ]
            );

            $this->vincularComponentesCurricularesTurma($school, $grade, $turma);
        }
    }

    /**
     * Vincula os componentes curriculares (disciplinas BNCC) da série à turma,
     * permitindo lançamento de notas e faltas no iDiário.
     */
    private function vincularComponentesCurricularesTurma(
        LegacySchool $school,
        LegacyGrade $grade,
        LegacySchoolClass $turma
    ): void {
        $componentesSerie = LegacyDisciplineAcademicYear::query()
            ->where('ano_escolar_id', $grade->cod_serie)
            ->get();

        foreach ($componentesSerie as $ccAno) {
            LegacyDisciplineSchoolClass::firstOrCreate(
                [
                    'componente_curricular_id' => $ccAno->componente_curricular_id,
                    'turma_id' => $turma->cod_turma,
                ],
                [
                    'ano_escolar_id' => $grade->cod_serie,
                    'escola_id' => $school->cod_escola,
                    'carga_horaria' => $ccAno->carga_horaria ?? 40,
                    'docente_vinculado' => 0,
                    'etapas_especificas' => 0,
                    'etapas_utilizadas' => '',
                ]
            );
        }
    }
}
