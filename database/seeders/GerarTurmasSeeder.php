<?php

namespace Database\Seeders;

use App\Models\LegacyCourse;
use App\Models\LegacyDisciplineAcademicYear;
use App\Models\LegacyDisciplineSchoolClass;
use App\Models\LegacyGrade;
use App\Models\LegacyPeriod;
use App\Models\LegacySchool;
use App\Models\LegacySchoolClass;
use App\Models\LegacySchoolClassType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder opcional para gerar turmas automaticamente por escola/série/curso.
 *
 * Motivo: a geração de turmas foi removida do fluxo padrão (`SeederMaster`) para
 * evitar criação involuntária de turmas em anos (ex.: ano anterior e ano corrente).
 *
 * Uso recomendado: executar antes de iniciar matrícula/enturmação em um ambiente novo.
 */
class GerarTurmasSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    /**
     * Por padrão gera turmas apenas para o ano corrente.
     *
     * @param  array{anos?: list<int>}  $params
     */
    public function run(array $params = []): void
    {
        $anos = $params['anos'] ?? [Carbon::now()->year];

        $schools = LegacySchool::query()->where('ativo', 1)->get();
        if ($schools->isEmpty()) {
            $this->command?->warn('Nenhuma escola encontrada para gerar turmas.');

            return;
        }

        foreach ($schools as $school) {
            $instituicaoId = (int) $school->ref_cod_instituicao;

            $turmaTipo = LegacySchoolClassType::query()
                ->where('ativo', 1)
                ->where(function ($q) use ($instituicaoId) {
                    $q->where('ref_cod_instituicao', $instituicaoId)->orWhereNull('ref_cod_instituicao');
                })
                ->orderByRaw('CASE WHEN ref_cod_instituicao = ? THEN 0 WHEN ref_cod_instituicao IS NULL THEN 1 ELSE 2 END', [$instituicaoId])
                ->first();

            $turmaTurno = LegacyPeriod::query()->where('ativo', 1)->orderBy('id')->first();

            if (!$turmaTipo || !$turmaTurno) {
                $this->command?->warn("Escola {$school->cod_escola}: sem tipo/turno de turma; pulando geração.");
                continue;
            }

            // Para cada curso da escola, para cada série do curso, cria turmas nos anos solicitados.
            $cursoIds = DB::table('pmieducar.escola_curso')
                ->where('ref_cod_escola', $school->cod_escola)
                ->where('ativo', 1)
                ->pluck('ref_cod_curso')
                ->map(fn ($v) => (int) $v)
                ->all();

            foreach ($cursoIds as $codCurso) {
                $curso = LegacyCourse::query()->whereKey($codCurso)->first();
                if ($curso === null || (int) $curso->ativo !== 1) {
                    continue;
                }

                $grades = LegacyGrade::query()
                    ->where('ref_cod_curso', $curso->cod_curso)
                    ->where('ativo', 1)
                    ->orderBy('etapa_curso')
                    ->get();

                foreach ($grades as $grade) {
                    if (!$grade instanceof LegacyGrade) {
                        continue;
                    }
                    foreach ($anos as $ano) {
                        $this->criarTurmaAno($school, $curso, $grade, (int) $ano, $turmaTipo, $turmaTurno);
                    }
                }
            }
        }
    }

    private function criarTurmaAno(
        LegacySchool $school,
        LegacyCourse $curso,
        LegacyGrade $grade,
        int $ano,
        LegacySchoolClassType $turmaTipo,
        LegacyPeriod $turmaTurno
    ): void {
        $instituicaoId = (int) $school->ref_cod_instituicao;
        $nmTurma = $grade->nm_serie . ' - ' . $ano;
        $sglTurma = mb_substr($grade->nm_serie, 0, 3) . $ano;

        $temEscolaSerie = DB::table('pmieducar.escola_serie')
            ->where('ref_cod_escola', $school->cod_escola)
            ->where('ref_cod_serie', $grade->cod_serie)
            ->where('ativo', 1)
            ->exists();

        if (!$temEscolaSerie) {
            $this->command?->line(sprintf(
                'Escola %d: série %d (%s) sem vínculo em escola_serie — turma %d não criada.',
                $school->cod_escola,
                $grade->cod_serie,
                $grade->nm_serie,
                $ano
            ));

            return;
        }

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

    private function vincularComponentesCurricularesTurma(LegacySchool $school, LegacyGrade $grade, LegacySchoolClass $turma): void
    {
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

