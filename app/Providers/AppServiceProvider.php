<?php

namespace App\Providers;

use App\Models\LegacyInstitution;
use App\Providers\Postgres\DatabaseServiceProvider;
use App\Services\CacheManager;
use App\Services\StudentUnificationService;
use Exception;
use iEducar\Modules\ErrorTracking\HoneyBadgerTracker;
use iEducar\Modules\ErrorTracking\Tracker;
use iEducar\Support\Navigation\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Load migrations from other repositories or packages.
     *
     * @return void
     */
    private function loadLegacyMigrations()
    {
        foreach (config('legacy.migrations') as $path) {
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }

    /**
     * Load legacy bootstrap application.
     *
     * @return void
     *
     * @throws Exception
     */
    private function loadLegacyBootstrap()
    {
        setlocale(LC_ALL, 'en_US.UTF-8');
        date_default_timezone_set(config('legacy.app.locale.timezone'));
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     *
     * @throws Exception
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom([
                database_path('migrations/addressing'),
                database_path('migrations/audit'),
                database_path('migrations/educacenso'),
                database_path('migrations/exporter'),
                database_path('migrations/misc'),
                database_path('migrations/report'),
                database_path('migrations/table'),
            ]);
            $this->loadLegacyMigrations();
        }

        if (env('ASSETS_SECURE')) {
            URL::forceScheme('https');
        }

        $this->loadLegacyBootstrap();

        Collection::macro('getKeyValueArray', function ($valueField) {
            $keyValueArray = [];
            foreach ($this->items as $item) {
                $keyValueArray[$item->getKey()] = $item->getAttribute($valueField);
            }

            return $keyValueArray;
        });

        SchemaBuilder::defaultStringLength(191);

        Paginator::defaultView('vendor.pagination.default');

        QueryBuilder::macro('whereUnaccent', function ($column, $value) {
            $this->whereRaw('unaccent(' . $column . ') ilike unaccent(\'%\' || ? || \'%\')', [$value]);
        });

        View::composer('components.bi-print-header', function ($view) {
            $institution = LegacyInstitution::query()
                ->where('ativo', 1)
                ->whereExists(function ($q) {
                    $q->selectRaw(1)
                        ->from('pmieducar.configuracoes_gerais as cg')
                        ->whereColumn('cg.ref_cod_instituicao', 'pmieducar.instituicao.cod_instituicao');
                })
                ->first();

            $nmInstituicao = config('legacy.config.ieducar_entity_name')
                ?? $institution?->nm_instituicao
                ?? config('legacy.app.entity.name')
                ?? 'i-Educar';
            $nmResponsavel = $institution?->nm_responsavel ?? $institution?->orgao_regional ?? '';
            $logradouro = $institution?->logradouro ?? '';
            $numero = $institution?->numero ?? '';
            $bairro = $institution?->bairro ?? '';
            $cidade = $institution?->cidade ?? '';
            $uf = $institution?->ref_sigla_uf ?? '';
            $cep = $institution?->cep
                ? \App\Services\Reports\Util::formatPostcode((string) $institution->cep)
                : '';
            $foneDdd = $institution?->ddd_telefone ?? '';
            $fone = $institution?->telefone ? (string) $institution->telefone : '';

            $enderecoParts = array_filter([
                $logradouro ? $logradouro . ',' : '',
                $numero ? 'Nº ' . $numero : 'S/N',
                $bairro ? ' - ' . $bairro : '',
                $cidade ? ' - ' . $cidade : '',
                $uf ? ' - ' . $uf : '',
                $cep ? ' - CEP: ' . $cep : '',
            ]);
            $endereco = trim(implode(' ', $enderecoParts), ' -,');
            $telefone = $foneDdd ? '(' . $foneDdd . ') ' . $fone : $fone;

            $view->with([
                'headerNmInstituicao' => mb_strtoupper($nmInstituicao, 'UTF-8'),
                'headerNmResponsavel' => mb_strtoupper($nmResponsavel, 'UTF-8'),
                'headerEndereco' => $endereco,
                'headerTelefone' => $telefone,
            ]);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Breadcrumb::class);

        if ($this->app->environment('development', 'local', 'testing')) {
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->bind(Tracker::class, HoneyBadgerTracker::class);

        $this->app->bind(LegacyInstitution::class, function () {
            return LegacyInstitution::query()->where('ativo', 1)->firstOrFail();
        });

        $this->app->bind(StudentUnificationService::class, function () {
            return new StudentUnificationService(Auth::user());
        });

        Cache::swap(new CacheManager(app()));
        $this->app->register(DatabaseServiceProvider::class);
    }
}
