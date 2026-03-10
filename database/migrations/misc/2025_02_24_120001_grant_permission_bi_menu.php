<?php

use App\Process;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class GrantPermissionBiMenu extends Migration
{
    private const BI_PROCESSES = [
        Process::MENU_BI,
        Process::BI_MATRICULAS,
        Process::BI_TURMAS,
        Process::BI_LANCAMENTOS,
        Process::BI_INDICADORES,
        Process::BI_INCLUSAO_DIVERSIDADE,
        Process::BI_BUSCA_ATIVA,
        Process::BI_EDUCACENSO,
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $schoolProcess = Process::MENU_SCHOOL;

        foreach (self::BI_PROCESSES as $biProcess) {
            DB::statement(
                "INSERT INTO pmieducar.menu_tipo_usuario (ref_cod_tipo_usuario, cadastra, visualiza, exclui, menu_id)
                 SELECT ref_cod_tipo_usuario, 1, 1, 1, (SELECT id FROM public.menus WHERE process = {$biProcess} LIMIT 1)
                 FROM pmieducar.menu_tipo_usuario
                 WHERE menu_id = (SELECT id FROM public.menus WHERE process = {$schoolProcess} LIMIT 1)
                 AND (SELECT id FROM public.menus WHERE process = {$biProcess} LIMIT 1) IS NOT NULL
                 AND NOT EXISTS (
                     SELECT 1 FROM pmieducar.menu_tipo_usuario mtu
                     WHERE mtu.ref_cod_tipo_usuario = menu_tipo_usuario.ref_cod_tipo_usuario
                       AND mtu.menu_id = (SELECT id FROM public.menus WHERE process = {$biProcess} LIMIT 1)
                 )"
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach (self::BI_PROCESSES as $process) {
            DB::statement(
                'DELETE FROM pmieducar.menu_tipo_usuario WHERE menu_id = (SELECT id FROM public.menus WHERE process = ' . $process . ')'
            );
        }
    }
}
