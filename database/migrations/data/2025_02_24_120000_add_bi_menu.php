<?php

use App\Menu;
use App\Process;
use Illuminate\Database\Migrations\Migration;

class AddBiMenu extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $escolaMenuId = Menu::query()->where('process', Process::MENU_SCHOOL)->firstOrFail()->getKey();

        $biMenu = Menu::query()->updateOrCreate([
            'parent_id' => $escolaMenuId,
            'old' => Process::MENU_BI,
        ], [
            'title' => 'BI',
            'description' => 'Business Intelligence - Dashboards e relatórios analíticos',
            'link' => null,
            'icon' => 'fa-chart-line',
            'order' => 8,
            'type' => 2,
            'process' => Process::MENU_BI,
            'parent_old' => Process::MENU_SCHOOL,
            'active' => true,
        ]);

        $temas = [
            ['title' => 'Matrículas', 'link' => '/bis/matriculas', 'order' => 1, 'process' => Process::BI_MATRICULAS],
            ['title' => 'Turmas', 'link' => '/bis/turmas', 'order' => 2, 'process' => Process::BI_TURMAS],
            ['title' => 'Lançamentos', 'link' => '/bis/lancamentos', 'order' => 3, 'process' => Process::BI_LANCAMENTOS],
            ['title' => 'Indicadores', 'link' => '/bis/indicadores', 'order' => 4, 'process' => Process::BI_INDICADORES],
        ];

        foreach ($temas as $tema) {
            Menu::query()->updateOrCreate([
                'parent_id' => $biMenu->getKey(),
                'title' => $tema['title'],
            ], [
                'description' => "BI - {$tema['title']}",
                'link' => $tema['link'],
                'order' => $tema['order'],
                'type' => 3,
                'process' => $tema['process'],
                'parent_old' => Process::MENU_BI,
                'active' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach ([Process::BI_MATRICULAS, Process::BI_TURMAS, Process::BI_LANCAMENTOS, Process::BI_INDICADORES] as $process) {
            Menu::query()->where('process', $process)->delete();
        }

        $biMenu = Menu::query()->where('old', Process::MENU_BI)->first();
        if ($biMenu) {
            $biMenu->delete();
        }
    }
}
