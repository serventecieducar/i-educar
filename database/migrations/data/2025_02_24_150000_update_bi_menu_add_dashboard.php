<?php

use App\Menu;
use App\Process;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateBiMenuAddDashboard extends Migration
{
    /**
     * Atualiza o menu BI: adiciona link do Dashboard e inclui item Dashboard como primeiro submenu.
     * Concede permissão aos mesmos tipos de usuário que têm acesso ao menu Escola.
     */
    public function up(): void
    {
        $biMenu = Menu::query()->where('process', Process::MENU_BI)->first();

        if (!$biMenu) {
            return;
        }

        $biMenu->update(['link' => '/bis']);

        $dashboardMenu = Menu::query()->updateOrCreate(
            [
                'parent_id' => $biMenu->getKey(),
                'title' => 'Dashboard',
            ],
            [
                'description' => 'BI - Dashboard Geral',
                'link' => '/bis',
                'order' => 0,
                'type' => 3,
                'process' => Process::MENU_BI,
                'parent_old' => Process::MENU_BI,
                'active' => true,
            ]
        );

        $schoolProcess = Process::MENU_SCHOOL;
        $dashboardId = $dashboardMenu->getKey();

        DB::statement(
            "INSERT INTO pmieducar.menu_tipo_usuario (ref_cod_tipo_usuario, cadastra, visualiza, exclui, menu_id)
             SELECT ref_cod_tipo_usuario, 1, 1, 1, {$dashboardId}
             FROM pmieducar.menu_tipo_usuario
             WHERE menu_id = (SELECT id FROM public.menus WHERE process = {$schoolProcess} LIMIT 1)
             AND NOT EXISTS (
                 SELECT 1 FROM pmieducar.menu_tipo_usuario mtu
                 WHERE mtu.ref_cod_tipo_usuario = menu_tipo_usuario.ref_cod_tipo_usuario
                   AND mtu.menu_id = {$dashboardId}
             )"
        );
    }

    public function down(): void
    {
        $dashboardMenu = Menu::query()
            ->where('process', Process::MENU_BI)
            ->where('title', 'Dashboard')
            ->first();

        if ($dashboardMenu) {
            DB::statement(
                'DELETE FROM pmieducar.menu_tipo_usuario WHERE menu_id = ' . $dashboardMenu->getKey()
            );
            $dashboardMenu->delete();
        }

        Menu::query()
            ->where('process', Process::MENU_BI)
            ->update(['link' => null]);
    }
}
