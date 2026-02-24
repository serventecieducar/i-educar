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

        $cursosConfig = [
            'Educação Infantil' => [
                'qtd_etapas' => 4,
                'grades' => ['Berçário I', 'Berçário II', 'Maternal', 'Pré-Escolar'],
                'disciplinas' => [
                    'Campos de Experiência: O eu, o outro e o nós',
                    'Campos de Experiência: Corpo, gestos e movimentos',
                    'Campos de Experiência: Traços, sons, cores e formas',
                    'Campos de Experiência: Escuta, fala, pensamento e imaginação',
                    'Campos de Experiência: Espaços, tempos, quantidades, relações e transformações',
                ],
            ],
            'Ensino Fundamental' => [
                'qtd_etapas' => 9,
                'grades' => ['1º ano', '2º ano', '3º ano', '4º ano', '5º ano', '6º ano', '7º ano', '8º ano', '9º ano'],
                'disciplinas' => [
                    'Língua Portuguesa', 'Matemática', 'Ciências', 'História', 'Geografia',
                    'Arte', 'Educação Física', 'Ensino Religioso', 'Inglês',
                ],
            ],
            'Ensino Médio' => [
                'qtd_etapas' => 3,
                'grades' => ['1º ano', '2º ano', '3º ano'],
                'disciplinas' => [
                    'Língua Portuguesa', 'Matemática', 'Biologia', 'Física', 'Química',
                    'História', 'Geografia', 'Filosofia', 'Sociologia',
                    'Educação Física', 'Arte', 'Inglês',
                ],
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

    /** @param array<int> $anos */
    private function vincularDisciplinasSerie(LegacyGrade $grade, array $nomesDisciplinas, int $instituicaoId, array $anos): void
    {
        $anosPg = $this->anosLetivosParaPg($anos);

        foreach ($nomesDisciplinas as $nome) {
            $disciplina = LegacyDiscipline::query()
                ->where('instituicao_id', $instituicaoId)
                ->where('nome', $nome)
                ->first();

            if (!$disciplina) {
                $areaConhecimento = LegacyKnowledgeArea::query()
                    ->where('instituicao_id', $instituicaoId)
                    ->first();

                if (!$areaConhecimento) {
                    $areaConhecimento = LegacyKnowledgeArea::create([
                        'instituicao_id' => $instituicaoId,
                        'nome' => 'Área de Conhecimento Padrão',
                    ]);
                }

                $disciplina = LegacyDiscipline::create([
                    'instituicao_id' => $instituicaoId,
                    'area_conhecimento_id' => $areaConhecimento->id,
                    'nome' => $nome,
                    'abreviatura' => substr($nome, 0, 10),
                    'tipo_base' => 1,
                    'ordenamento' => 1,
                ]);
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

            LegacySchoolClass::firstOrCreate(
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
        }
    }
}
