<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConnectTenantDatabase
{
    /**
     * @var Closure
     */
    private static $resolver;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($this->isLandingHost($request)) {
            return response()->view('tenants.landing', [
                'tenants' => config('tenants.catalog', []),
            ]);
        }

        $connections = config('database.connections');

        $tenant = $this->getTenant($request);

        if (isset($connections[$tenant])) {
            DB::setDefaultConnection($tenant);
        } elseif (config('app.multi_tenant')) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * Return tenant default connection name.
     *
     *
     * @return string
     */
    public function getTenant(Request $request)
    {
        $resolver = self::$resolver;

        if (empty($resolver)) {
            $resolver = $this->getDefaultTenantResolver();
        }

        return $resolver($request);
    }

    /**
     * Return default tenant resolver.
     *
     * @return Closure
     */
    public function getDefaultTenantResolver()
    {
        return function (Request $request) {
            $host = $request->getHost();
            $aliases = config('tenants.hosts', []);

            if (isset($aliases[$host])) {
                return $aliases[$host];
            }

            $defaultHost = (string) config('app.default_host');

            if ($defaultHost === 'localhost') {
                return $host;
            }

            $host = str_replace('-', '', $host);

            return Str::replaceFirst('.' . $defaultHost, '', $host);
        };
    }

    private function isLandingHost(Request $request): bool
    {
        if (!config('app.multi_tenant')) {
            return false;
        }

        return in_array($request->getHost(), config('tenants.landing_hosts', []), true);
    }

    /**
     * Set default tenant resolver.
     *
     *
     * @return bool
     */
    public static function setTenantResolver(Closure $resolver)
    {
        static::$resolver = $resolver;

        return true;
    }
}
