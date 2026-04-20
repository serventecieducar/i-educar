<?php

namespace Database\Seeders\Setup;

use App\Models\LegacyCourse;
use App\Models\LegacyEducationLevel;
use App\Models\LegacyEducationType;
use App\Models\LegacyGrade;
use App\Models\LegacyPeriod;
use App\Models\LegacyRegimeType;
use App\Models\LegacySchool;
use App\Models\LegacySchoolAcademicYear;
use App\Models\LegacySchoolClass;
use App\Models\LegacySchoolClassType;
use App\Models\SchoolInep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Garante turmas de AEE (matutino e vespertino) no ano vigente para as escolas
 * importadas do município de Itaparica/BA (fonte MUNICIPIO_ITAPARICA_BA_CSV).
 *
 * Observação:
 * - Existe um seed padrão `Database\Seeders\AeeSeeder` que cria AEE em todas as escolas ativas;
 *   este seeder replica a mesma ideia, mas restringe às escolas do município (CSV).
 */
class ItaparicaBaAeeSchoolClassesSeeder extends Seeder
{
    private const USUARIO_CAD = 1;
    private const VAGAS_AEE = 25;

    public function run(): void
    {
        $ano = now()->year;

        $schoolIds = SchoolInep::query()
            ->where('fonte', 'MUNICIPIO_ITAPARICA_BA_CSV')
            ->pluck('cod_escola')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        if ($schoolIds === []) {
            return;
        }

        $schools = LegacySchool::query()
            ->whereIn('cod_escola', $schoolIds)
            ->where('ativo', 1)
            ->get();

        if ($schools->isEmpty()) {
            return;
        }

        $instituicoes = $schools->pluck('ref_cod_instituicao')->unique()->filter()->values();

        foreach ($instituicoes as $instituicaoId) {
            $this->garantirAeeCursoESerie((int) $instituicaoId);
        }

        foreach ($schools as $school) {
            $instituicaoId = (int) $school->ref_cod_instituicao;

            $cursoAee = LegacyCourse::query()
                ->where('ref_cod_instituicao', $instituicaoId)
                ->where('nm_curso', 'Atendimento Educacional Especializado - AEE')
                ->first();

            if ($cursoAee === null) {
                continue;
            }

            $serieAee = LegacyGrade::query()
                ->where('ref_cod_curso', $cursoAee->cod_curso)
                ->where('nm_serie', 'AEE')
                ->first();

            if ($serieAee === null) {
                continue;
            }

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

            DB::table('pmieducar.escola_curso')->updateOrInsert(
                [
                    'ref_cod_escola' => $school->cod_escola,
                    'ref_cod_curso' => $cursoAee->cod_curso,
                ],
                [
                    'ref_usuario_cad' => self::USUARIO_CAD,
                    'data_cadastro' => now(),
                    'ativo' => 1,
                    'anos_letivos' => '{' . $ano . '}',
                ]
            );

            DB::table('pmieducar.escola_serie')->updateOrInsert(
                [
                    'ref_cod_escola' => $school->cod_escola,
                    'ref_cod_serie' => $serieAee->cod_serie,
                ],
                [
                    'ref_usuario_cad' => self::USUARIO_CAD,
                    'data_cadastro' => now(),
                    'anos_letivos' => '{' . $ano . '}',
                ]
            );

            $turmaTipo = LegacySchoolClassType::query()
                ->where('ativo', 1)
                ->where(function ($q) use ($instituicaoId) {
                    $q->where('ref_cod_instituicao', $instituicaoId)->orWhereNull('ref_cod_instituicao');
                })
                ->orderByRaw('CASE WHEN ref_cod_instituicao = ? THEN 0 WHEN ref_cod_instituicao IS NULL THEN 1 ELSE 2 END', [$instituicaoId])
                ->first();

            if ($turmaTipo === null) {
                continue;
            }

            $this->garantirTurmaAee(
                codEscola: (int) $school->cod_escola,
                instituicaoId: $instituicaoId,
                codCurso: (int) $cursoAee->cod_curso,
                codSerie: (int) $serieAee->cod_serie,
                ano: $ano,
                codTurmaTipo: (int) $turmaTipo->cod_turma_tipo,
                turnoId: 1,
                nome: 'AEE - Matutino'
            );
            $this->garantirTurmaAee(
                codEscola: (int) $school->cod_escola,
                instituicaoId: $instituicaoId,
                codCurso: (int) $cursoAee->cod_curso,
                codSerie: (int) $serieAee->cod_serie,
                ano: $ano,
                codTurmaTipo: (int) $turmaTipo->cod_turma_tipo,
                turnoId: 2,
                nome: 'AEE - Vespertino'
            );
        }

        Log::channel('daily')->info('ieducar:setup [itaparica-ba] — turmas AEE garantidas (ano vigente).', [
            'ano' => $ano,
            'escolas' => $schools->count(),
        ]);
    }

    private function garantirAeeCursoESerie(int $instituicaoId): void
    {
        $nivel = LegacyEducationLevel::query()->where('ref_cod_instituicao', $instituicaoId)->where('ativo', 1)->first();
        $tipo = LegacyEducationType::query()->where('ref_cod_instituicao', $instituicaoId)->where('ativo', 1)->first();
        $regime = LegacyRegimeType::query()->where('ref_cod_instituicao', $instituicaoId)->where('ativo', 1)->first();

        if ($nivel === null || $tipo === null) {
            return;
        }

        $curso = LegacyCourse::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('nm_curso', 'Atendimento Educacional Especializado - AEE')
            ->first();

        if ($curso === null) {
            $curso = LegacyCourse::query()->create([
                'nm_curso' => 'Atendimento Educacional Especializado - AEE',
                'descricao' => 'AEE',
                'sgl_curso' => 'AEE',
                'ref_cod_instituicao' => $instituicaoId,
                'ref_cod_nivel_ensino' => $nivel->cod_nivel_ensino,
                'ref_cod_tipo_ensino' => $tipo->cod_tipo_ensino,
                'ref_cod_tipo_regime' => $regime?->cod_tipo_regime,
                'qtd_etapas' => 1,
                'carga_horaria' => 800,
                'hora_falta' => 0.75,
                'padrao_ano_escolar' => true,
                'ref_usuario_cad' => self::USUARIO_CAD,
                'data_cadastro' => now(),
                'ativo' => 1,
            ]);
        } else {
            $curso->update(['ativo' => 1, 'qtd_etapas' => 1, 'padrao_ano_escolar' => true]);
        }

        LegacyGrade::query()->firstOrCreate(
            [
                'ref_cod_curso' => $curso->cod_curso,
                'nm_serie' => 'AEE',
            ],
            [
                'etapa_curso' => 1,
                'carga_horaria' => 800,
                'dias_letivos' => 200,
                'ref_usuario_cad' => self::USUARIO_CAD,
                'data_cadastro' => now(),
                'concluinte' => 2,
                'ativo' => 1,
            ]
        );

        // Garante que os turnos existam (1=Matutino, 2=Vespertino) como no seed padrão.
        LegacyPeriod::query()->whereIn('id', [1, 2])->where('ativo', 1)->exists();
    }

    private function garantirTurmaAee(
        int $codEscola,
        int $instituicaoId,
        int $codCurso,
        int $codSerie,
        int $ano,
        int $codTurmaTipo,
        int $turnoId,
        string $nome
    ): void {
        $turma = LegacySchoolClass::query()->firstOrCreate(
            [
                'ref_ref_cod_escola' => $codEscola,
                'ref_ref_cod_serie' => $codSerie,
                'ref_cod_curso' => $codCurso,
                'ano' => $ano,
                'turma_turno_id' => $turnoId,
            ],
            [
                'ref_usuario_cad' => self::USUARIO_CAD,
                'nm_turma' => $nome,
                'sgl_turma' => 'AEE' . $turnoId,
                'max_aluno' => self::VAGAS_AEE,
                'ref_cod_turma_tipo' => $codTurmaTipo,
                'ref_cod_instituicao' => $instituicaoId,
                'multiseriada' => false,
                'visivel' => true,
                'ativo' => 1,
                'dias_semana' => [2, 3, 4, 5, 6],
            ]
        );

        // Se já existia, garante que as vagas fiquem com o valor municipal.
        if ((int) $turma->max_aluno !== self::VAGAS_AEE) {
            $turma->update(['max_aluno' => self::VAGAS_AEE]);
        }
    }
}

