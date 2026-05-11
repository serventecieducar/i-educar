<?php

namespace Database\Seeders;

use App\Models\LegacySchoolAcademicYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Encerra (finaliza) todos os anos letivos anteriores ao ano corrente.
 *
 * Isso faz com que o próprio i-Educar bloqueie operações de matrícula/enturmação
 * em anos que não estejam "em andamento" (sem precisar alterar regras no core).
 */
class EncerrarAnosAnterioresSeeder extends Seeder
{
    private const USUARIO_SISTEMA = 1;

    /**
     * Finaliza todos os anos letivos anteriores ao corrente e desabilita
     * matrícula fora do período letivo na instituição.
     */
    public function run(): void
    {
        $anoCorrente = (int) now()->year;

        // Reforço por parâmetro: não permitir matrícula/enturmação fora do período letivo.
        // (A verificação é usada em fluxos de enturmação/cancelamento por data vs. início/fim do ano letivo.)
        DB::table('pmieducar.instituicao')->update([
            'permitir_matricula_fora_periodo_letivo' => false,
        ]);

        $anosEncerrados = LegacySchoolAcademicYear::query()
            ->where('ano', '<', $anoCorrente)
            ->where('ativo', 1)
            ->where('andamento', '!=', LegacySchoolAcademicYear::FINALIZED)
            ->update([
                'ref_usuario_exc' => self::USUARIO_SISTEMA,
                'andamento' => LegacySchoolAcademicYear::FINALIZED,
            ]);

        if ($anosEncerrados > 0) {
            $this->command?->info(sprintf(
                'Anos anteriores a %d encerrados: %d registro(s) atualizado(s).',
                $anoCorrente,
                $anosEncerrados
            ));
        }
    }
}

