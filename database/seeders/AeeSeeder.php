<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed padrão: cria/garante o curso de Atendimento Educacional Especializado (AEE)
 * e gera, no ano corrente, uma turma Matutino e uma turma Vespertino (40 vagas)
 * em todas as escolas ativas da rede.
 *
 * Suporta retomada: escolas que já possuem ambas as turmas AEE no ano corrente
 * são puladas automaticamente. Para retomar de uma escola específica, defina
 * `AEE_SEEDER_FROM_ESCOLA=<cod_escola>`.
 */
class AeeSeeder extends Seeder
{
    private const USUARIO_CAD = 1;

    private const TURMAS_AEE_POR_ESCOLA = 2;

    private function step(string $message): void
    {
        $line = '[AeeSeeder] ' . $message;
        Log::info($line);
        $this->command?->info($line);
    }

    public function run(): void
    {
        $tInicio = microtime(true);
        $ano = now()->year;

        $fromEscola = $this->resolveFromEscola();

        $query = LegacySchool::query()->where('ativo', 1)->orderBy('cod_escola');
        if ($fromEscola !== null) {
            $query->where('cod_escola', '>=', $fromEscola);
        }

        $schools = $query->get();
        if ($schools->isEmpty()) {
            return;
        }

        $totalEscolas = $schools->count();
        $this->step(sprintf(
            'Início (ano %d, %d escolas%s).',
            $ano,
            $totalEscolas,
            $fromEscola !== null ? ", a partir da escola {$fromEscola}" : ''
        ));

        $instituicoes = $schools->pluck('ref_cod_instituicao')->unique()->filter()->values();

        foreach ($instituicoes as $instituicaoId) {
            $this->garantirAeeCursoESerie((int) $instituicaoId);
        }

        $indice = 0;
        $puladas = 0;
        foreach ($schools as $school) {
            $indice++;
            $instituicaoId = (int) $school->ref_cod_instituicao;

            if ($this->escolaJaTemAee($school->cod_escola, $instituicaoId, $ano)) {
                $puladas++;

                continue;
            }

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

            $this->garantirTurmaAee($school->cod_escola, $instituicaoId, $cursoAee->cod_curso, $serieAee->cod_serie, $ano, $turmaTipo->cod_turma_tipo, 1, 'AEE - Matutino');
            $this->garantirTurmaAee($school->cod_escola, $instituicaoId, $cursoAee->cod_curso, $serieAee->cod_serie, $ano, $turmaTipo->cod_turma_tipo, 2, 'AEE - Vespertino');
        }

        if ($puladas > 0) {
            $this->step(sprintf('%d escola(s) já com AEE completo foram puladas.', $puladas));
        }
        $this->step(sprintf('Finalizado em %.2fs (%d processadas, %d puladas).', microtime(true) - $tInicio, $totalEscolas - $puladas, $puladas));
    }

    private function resolveFromEscola(): ?int
    {
        $val = env('AEE_SEEDER_FROM_ESCOLA');
        if ($val === null || $val === '' || $val === false) {
            return null;
        }

        $id = (int) $val;
        $this->step("Variável AEE_SEEDER_FROM_ESCOLA={$id} detectada — escolas anteriores serão ignoradas.");

        return $id;
    }

    /**
     * Escola já possui as 2 turmas AEE (Matutino + Vespertino) ativas no ano corrente.
     */
    private function escolaJaTemAee(int $codEscola, int $instituicaoId, int $ano): bool
    {
        $cursoAee = LegacyCourse::query()
            ->where('ref_cod_instituicao', $instituicaoId)
            ->where('nm_curso', 'Atendimento Educacional Especializado - AEE')
            ->first();

        if ($cursoAee === null) {
            return false;
        }

        $turmasAee = LegacySchoolClass::query()
            ->where('ref_ref_cod_escola', $codEscola)
            ->where('ref_cod_curso', $cursoAee->cod_curso)
            ->where('ano', $ano)
            ->where('ativo', 1)
            ->count();

        return $turmasAee >= self::TURMAS_AEE_POR_ESCOLA;
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
        LegacySchoolClass::query()->firstOrCreate(
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
                'max_aluno' => 40,
                'ref_cod_turma_tipo' => $codTurmaTipo,
                'ref_cod_instituicao' => $instituicaoId,
                'multiseriada' => false,
                'visivel' => true,
                'ativo' => 1,
                'dias_semana' => [2, 3, 4, 5, 6],
            ]
        );
    }
}

