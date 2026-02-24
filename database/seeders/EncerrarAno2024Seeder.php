<?php

namespace Database\Seeders;

use App\Models\LegacyRegistration;
use App\Models\LegacySchoolAcademicYear;
use App_Model_MatriculaSituacao;
use Illuminate\Database\Seeder;

class EncerrarAno2024Seeder extends Seeder
{
    private const ANO_ENCERRAMENTO = 2024;

    private const USUARIO_SISTEMA = 1;

    /** Situações que serão convertidas para Aprovado (alunos ainda em processo) */
    private const SITUACOES_PARA_APROVAR = [
        App_Model_MatriculaSituacao::EM_ANDAMENTO,    // 3 - Cursando
        App_Model_MatriculaSituacao::EM_EXAME,        // 7 - Em exame
    ];

    public function run(): void
    {
        $matriculasAprovadas = $this->aprovarMatriculasPendentes();
        $anosEncerrados = $this->encerrarAnosLetivos();

        if ($matriculasAprovadas > 0 || $anosEncerrados > 0) {
            $this->command?->info(sprintf(
                'Ano %d encerrado: %d matrícula(s) aprovada(s), %d ano(s) letivo(s) finalizado(s).',
                self::ANO_ENCERRAMENTO,
                $matriculasAprovadas,
                $anosEncerrados
            ));
        }
    }

    /**
     * Aprova todas as matrículas do ano que estejam em andamento ou em exame.
     */
    private function aprovarMatriculasPendentes(): int
    {
        $matriculas = LegacyRegistration::query()
            ->where('ano', self::ANO_ENCERRAMENTO)
            ->where('ativo', 1)
            ->whereIn('aprovado', self::SITUACOES_PARA_APROVAR)
            ->get();

        $count = 0;
        foreach ($matriculas as $matricula) {
            $matricula->aprovado = App_Model_MatriculaSituacao::APROVADO;
            $matricula->save();
            $count++;
        }

        return $count;
    }

    /**
     * Encerra (finaliza) todos os anos letivos de 2024 no sistema.
     */
    private function encerrarAnosLetivos(): int
    {
        return LegacySchoolAcademicYear::query()
            ->where('ano', self::ANO_ENCERRAMENTO)
            ->update([
                'ref_usuario_exc' => self::USUARIO_SISTEMA,
                'andamento' => LegacySchoolAcademicYear::FINALIZED,
            ]);
    }
}
