<?php

namespace App\Http\Middleware;

use App\Services\CacheService;
use App\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class LoadSettings
{
    /**
     * Return configurations for institution.
     *
     * @return array
     */
    private function getConfig()
    {
        $config = CacheService::rememberGeneralConfiguration(
            fn () => (array) DB::table('pmieducar.configuracoes_gerais as cg')
                ->select('cg.*', 'i.cidade', 'i.ref_sigla_uf')
                ->join('pmieducar.instituicao as i', 'cod_instituicao', '=', 'ref_cod_instituicao')
                ->where('i.ativo', 1)
                ->first()
        );

        return ['legacy.config' => $config];
    }

    /**
     * Return database configuration.
     *
     * @return array
     */
    private function getDatabaseConfig()
    {
        $config = DB::connection()->getConfig();

        return [
            'legacy.app.database.hostname' => $config['host'],
            'legacy.app.database.port' => $config['port'],
            'legacy.app.database.dbname' => $config['database'],
            'legacy.app.database.username' => $config['username'],
            'legacy.app.database.password' => $config['password'],
        ];
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $settings = CacheService::rememberSettings(
            fn () => Setting::all()->pluck('value', 'key')->toArray()
        );

        Config::set($settings);
        Config::set($this->getConfig());
        Config::set($this->getDatabaseConfig());
        $this->applyInstitutionToPreMatricula();

        return $next($request);
    }

    /**
     * A pré-matrícula traz Içara/SC como valor de fábrica. A cidade exibida
     * segue a instituição ativa desta instalação.
     */
    private function applyInstitutionToPreMatricula(): void
    {
        $institution = config('legacy.config');

        if (!is_object($institution) && !is_array($institution)) {
            return;
        }

        $city = data_get($institution, 'cidade');
        $state = data_get($institution, 'ref_sigla_uf');

        if (filled($city)) {
            Config::set('prematricula.city', $city);
        }

        if (filled($state)) {
            Config::set('prematricula.state', $state);
        }
    }
}
