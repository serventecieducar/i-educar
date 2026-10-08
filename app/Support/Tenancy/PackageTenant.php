<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Facades\DB;

class PackageTenant
{
    public static function allows(string $package, ?string $connection = null): bool
    {
        $allowed = config('tenants.packages.' . $package);

        if (!is_array($allowed)) {
            return true;
        }

        $connection ??= self::connectionName();

        return in_array($connection, $allowed, true);
    }

    public static function connectionName(): string
    {
        if (app()->runningInConsole()) {
            return self::consoleConnection();
        }

        return (string) DB::getDefaultConnection();
    }

    public static function consoleConnection(): string
    {
        $argv = $_SERVER['argv'] ?? [];

        foreach ($argv as $index => $argument) {
            if (str_starts_with($argument, '--database=')) {
                return substr($argument, strlen('--database='));
            }

            if ($argument === '--database' && isset($argv[$index + 1])) {
                return (string) $argv[$index + 1];
            }
        }

        return (string) config('database.default');
    }
}
