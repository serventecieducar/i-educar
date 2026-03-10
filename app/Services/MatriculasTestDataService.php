<?php

namespace App\Services;

use App\Models\LegacyDiscipline;
use App\Models\LegacyDisciplineScore;
use App\Models\LegacyEnrollment;
use App\Models\LegacyEvaluationRule;
use App\Models\LegacyGeneralAbsence;
use App\Models\LegacyIndividual;
use App\Models\LegacyPerson;
use App\Models\LegacyRegistration;
use App\Models\LegacyRegistrationScore;
use App\Models\LegacySchool;
use App\Models\LegacySchoolAcademicYear;
use App\Models\LegacySchoolClass;
use App\Models\LegacyDisciplineAbsence;
use App\Models\LegacySchoolGradeDiscipline;
use App\Models\LegacyStudent;
use App\Models\LegacyStudentAbsence;
use Illuminate\Support\Collection;
use RegraAvaliacao_Model_TipoPresenca;

class MatriculasTestDataService
{
    private const USUARIO_CAD = 1;

    /** Gera matrículas de teste apenas em anos sem matrículas (por escola). Use --force para ignorar. */
    public function getEscolasAnosVazios(bool $force = false): Collection
    {
        $anosAbertos = LegacySchoolAcademicYear::query()
            ->where('andamento', LegacySchoolAcademicYear::IN_PROGRESS)
            ->where('ativo', 1)
            ->with('school')
            ->get();

        $resultado = collect();
        foreach ($anosAbertos as $eal) {
            if (!$eal->school) {
                continue;
            }
            if (!$force) {
                $existeMatricula = LegacyRegistration::query()
                    ->where('ref_ref_cod_escola', $eal->ref_cod_escola)
                    ->where('ano', $eal->ano)
                    ->where('ativo', 1)
                    ->exists();
                if ($existeMatricula) {
                    continue;
                }
            }
            $resultado->push([
                'school' => $eal->school,
                'ano' => $eal->ano,
            ]);
        }

        return $resultado;
    }

    /** @return Collection<int, LegacySchoolClass> */
    public function getTurmasParaAno(int $schoolId, int $ano): Collection
    {
        return LegacySchoolClass::query()
            ->where('ref_ref_cod_escola', $schoolId)
            ->where('ano', $ano)
            ->where('ativo', 1)
            ->with(['grade', 'course', 'school'])
            ->get();
    }

    /**
     * Cria matrículas, enturmações, notas e faltas para uma turma.
     *
     * @return array{matriculas: int, alunos: int}
     */
    public function popularTurma(LegacySchoolClass $turma, int $quantidade): array
    {
        $vagas = max(0, ($turma->max_aluno ?? 40) - $turma->getTotalEnrolled());
        $criar = min($quantidade, $vagas);
        if ($criar <= 0) {
            return ['matriculas' => 0, 'alunos' => 0];
        }

        $curso = $turma->course;
        $qtdEtapas = $curso ? (int) $curso->qtd_etapas : 4;
        $etapas = range(1, min($qtdEtapas, 4));

        $disciplinas = $this->getDisciplinasTurma($turma);
        $regra = $this->getRegraAvaliacao($turma);
        $tipoFalta = $regra && $regra->tipo_presenca == RegraAvaliacao_Model_TipoPresenca::POR_COMPONENTE
            ? RegraAvaliacao_Model_TipoPresenca::POR_COMPONENTE
            : RegraAvaliacao_Model_TipoPresenca::GERAL;

        $religiaoId = $this->getPrimeiraReligiao();
        $alunosCriados = 0;

        for ($i = 0; $i < $criar; $i++) {
            $student = $this->criarAluno($religiaoId);
            if (!$student) {
                continue;
            }
            $alunosCriados++;

            $matricula = LegacyRegistration::create([
                'ref_cod_aluno' => $student->cod_aluno,
                'ref_ref_cod_serie' => $turma->ref_ref_cod_serie,
                'ref_ref_cod_escola' => $turma->ref_ref_cod_escola,
                'ref_cod_curso' => $turma->ref_cod_curso,
                'ano' => $turma->ano,
                'ref_usuario_cad' => self::USUARIO_CAD,
                'data_matricula' => now(),
                'ultima_matricula' => 1,
                'dependencia' => false,
                'ativo' => 1,
                'aprovado' => \App_Model_MatriculaSituacao::EM_ANDAMENTO,
            ]);

            LegacyEnrollment::create([
                'ref_cod_matricula' => $matricula->cod_matricula,
                'ref_cod_turma' => $turma->cod_turma,
                'sequencial' => 1,
                'sequencial_fechamento' => 1,
                'ref_usuario_cad' => self::USUARIO_CAD,
                'data_enturmacao' => now(),
                'ativo' => 1,
            ]);

            $notaAluno = LegacyRegistrationScore::create([
                'matricula_id' => $matricula->cod_matricula,
            ]);

            foreach ($disciplinas as $disciplina) {
                foreach ($etapas as $etapa) {
                    $nota = (string) fake()->randomFloat(1, 5, 10);
                    LegacyDisciplineScore::create([
                        'nota_aluno_id' => $notaAluno->id,
                        'componente_curricular_id' => $disciplina->id,
                        'nota' => $nota,
                        'nota_arredondada' => $nota,
                        'etapa' => (string) $etapa,
                    ]);
                }
            }

            $faltaAluno = LegacyStudentAbsence::create([
                'matricula_id' => $matricula->cod_matricula,
                'tipo_falta' => $tipoFalta,
            ]);

            foreach ($etapas as $etapa) {
                if ($tipoFalta == RegraAvaliacao_Model_TipoPresenca::GERAL) {
                    LegacyGeneralAbsence::create([
                        'falta_aluno_id' => $faltaAluno->id,
                        'quantidade' => fake()->numberBetween(0, 5),
                        'etapa' => (string) $etapa,
                    ]);
                } else {
                    foreach ($disciplinas as $disciplina) {
                        LegacyDisciplineAbsence::create([
                            'falta_aluno_id' => $faltaAluno->id,
                            'componente_curricular_id' => $disciplina->id,
                            'quantidade' => fake()->numberBetween(0, 5),
                            'etapa' => (string) $etapa,
                        ]);
                    }
                }
            }
        }

        return ['matriculas' => $alunosCriados, 'alunos' => $alunosCriados];
    }

    /** @return Collection<int, LegacyDiscipline> */
    private function getDisciplinasTurma(LegacySchoolClass $turma): Collection
    {
        $disciplinas = $turma->getDisciplines();
        if ($disciplinas->isNotEmpty()) {
            return $disciplinas;
        }

        return LegacySchoolGradeDiscipline::query()
            ->where('ref_ref_cod_escola', $turma->ref_ref_cod_escola)
            ->where('ref_ref_cod_serie', $turma->ref_ref_cod_serie)
            ->whereRaw('? = ANY(anos_letivos)', [$turma->ano])
            ->with('discipline')
            ->get()
            ->pluck('discipline')
            ->filter();
    }

    private function getRegraAvaliacao(LegacySchoolClass $turma): ?LegacyEvaluationRule
    {
        try {
            return $turma->getEvaluationRule($turma->ref_ref_cod_serie);
        } catch (\Throwable) {
            return null;
        }
    }

    private function getPrimeiraReligiao(): ?int
    {
        $id = \App\Models\Religion::query()->value('id');
        if ($id !== null) {
            return (int) $id;
        }
        try {
            return (int) \Illuminate\Support\Facades\DB::table('pmieducar.religions')->value('id');
        } catch (\Throwable) {
            return null;
        }
    }

    private function criarAluno(?int $religiaoId): ?LegacyStudent
    {
        $nome = fake()->firstName() . ' ' . fake()->lastName();
        $sexo = fake()->randomElement(['M', 'F']);

        $person = LegacyPerson::create([
            'nome' => $nome,
            'data_cad' => now(),
            'tipo' => 'F',
            'situacao' => 'A',
            'origem_gravacao' => 'M',
            'operacao' => 'I',
        ]);

        $anoNasc = now()->year - fake()->numberBetween(6, 17);
        $dataNasc = fake()->dateTimeBetween("{$anoNasc}-01-01", "{$anoNasc}-12-31");

        LegacyIndividual::create([
            'idpes' => $person->idpes,
            'data_nasc' => $dataNasc,
            'sexo' => $sexo,
            'operacao' => 'I',
            'origem_gravacao' => 'M',
            'data_cad' => now(),
            'zona_localizacao_censo' => 1,
        ]);

        return LegacyStudent::create([
            'ref_idpes' => $person->idpes,
            'ref_cod_religiao' => $religiaoId,
            'ref_usuario_cad' => self::USUARIO_CAD,
            'tipo_responsavel' => 'a',
            'ativo' => 1,
        ]);
    }
}
