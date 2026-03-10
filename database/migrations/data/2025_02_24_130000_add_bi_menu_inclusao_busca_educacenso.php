<?php

use App\Menu;
use App\Process;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddBiMenuInclusaoBuscaEducacenso extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $biMenu = Menu::query()->where('process', Process::MENU_BI)->first();
        if (!$biMenu) {
            return;
        }

        $temas = [
            ['title' => 'Inclusão e Diversidade', 'link' => '/bis/inclusao-diversidade', 'order' => 5, 'process' => Process::BI_INCLUSAO_DIVERSIDADE],
            ['title' => 'Busca Ativa', 'link' => '/bis/busca-ativa', 'order' => 6, 'process' => Process::BI_BUSCA_ATIVA],
            ['title' => 'Educacenso/INEP', 'link' => '/bis/educacenso', 'order' => 7, 'process' => Process::BI_EDUCACENSO],
        ];

        foreach ($temas as $tema) {
            Menu::query()->updateOrCreate(
                [
                    'parent_id' => $biMenu->getKey(),
                    'title' => $tema['title'],
                ],
                [
                    'description' => "BI - {$tema['title']}",
                    'link' => $tema['link'],
                    'order' => $tema['order'],
                    'type' => 3,
                    'process' => $tema['process'],
                    'parent_old' => Process::MENU_BI,
                    'active' => true,
                ]
            );
        }

        $schoolProcess = Process::MENU_SCHOOL;
        foreach ([Process::BI_INCLUSAO_DIVERSIDADE, Process::BI_BUSCA_ATIVA, Process::BI_EDUCACENSO] as $biProcess) {
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
     */
    public function down(): void
    {
        foreach ([Process::BI_INCLUSAO_DIVERSIDADE, Process::BI_BUSCA_ATIVA, Process::BI_EDUCACENSO] as $process) {
            DB::statement('DELETE FROM pmieducar.menu_tipo_usuario WHERE menu_id = (SELECT id FROM public.menus WHERE process = ' . $process . ')');
            Menu::query()->where('process', $process)->delete();
        }
    }
}
