<?php

namespace App\Console\Commands;

use App\Menu;
use App\Services\MenuCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PreMatriculaMenuCommand extends Command
{
    protected $signature = 'pmd:menu';

    protected $description = 'Mostra Pré-matrícula no menu lateral e libera para os tipos de usuário ativos';

    public function handle(): int
    {
        $raiz = Menu::query()->updateOrCreate(
            ['process' => 56],
            [
                'parent_id' => null,
                'title' => 'Pré-matrícula',
                'description' => 'Pré-matrícula digital',
                'link' => '/pre-matricula-digital/inscricoes',
                'icon' => 'fa-share-square-o',
                'order' => 5,
                'type' => 1,
                'active' => true,
            ]
        );

        Menu::query()->updateOrCreate(
            ['process' => 5656],
            [
                'parent_id' => $raiz->getKey(),
                'title' => 'Inscrições',
                'description' => 'Pré-matrícula > Inscrições',
                'link' => '/pre-matricula-digital/inscricoes',
                'type' => 2,
                'active' => true,
            ]
        );

        $ids = Menu::query()->whereIn('process', [56, 5656])->pluck('id');
        $tipos = DB::table('pmieducar.tipo_usuario')->where('ativo', 1)->pluck('cod_tipo_usuario');

        foreach ($ids as $menuId) {
            foreach ($tipos as $tipo) {
                DB::table('pmieducar.menu_tipo_usuario')->updateOrInsert(
                    [
                        'ref_cod_tipo_usuario' => $tipo,
                        'menu_id' => $menuId,
                    ],
                    [
                        'visualiza' => 1,
                        'cadastra' => 1,
                        'exclui' => 0,
                    ]
                );
            }
        }

        try {
            app(MenuCacheService::class)->flushAll();
        } catch (Throwable $e) {
            report($e);
        }

        $this->callSilent('cache:clear');
        $this->info('Menu Pré-matrícula liberado para ' . $tipos->count() . ' tipo(s) de usuário.');

        return self::SUCCESS;
    }
}
