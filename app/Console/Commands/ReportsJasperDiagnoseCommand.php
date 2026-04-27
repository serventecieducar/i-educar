<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportsJasperDiagnoseCommand extends Command
{
    protected $signature = 'reports:jasper-diagnose';

    protected $description = 'Verifica exec, geekcom/phpjasper, symlink do pacote de relatórios e paridade entre instalações';

    public function handle(): int
    {
        $this->line('Base path: '.base_path());
        $this->line('PHP SAPI: '.PHP_SAPI.' | PHP_BINARY: '.PHP_BINARY);
        $this->line('Ligação DB (Artisan): '.config('database.default'));
        if (config('app.multi_tenant')) {
            $this->warn('APP_MULTI_TENANT=true: na web, legacy.report.* pode vir da tabela settings do tenant.');
        }
        $this->newLine();

        $this->printInstallationParity();
        $this->newLine();

        $rows = [
            ['exec() disponível', function_exists('exec') ? 'sim' : 'NÃO — active no php.ini / pool FPM (disable_functions)'],
            ['shell_exec disponível', function_exists('shell_exec') ? 'sim' : 'opcional'],
        ];

        $binDir = $this->resolveBinDir();
        $rows[] = ['JasperStarter bin dir', $binDir];
        $rows[] = ['É directório', is_dir($binDir) ? 'sim' : 'NÃO'];
        $binary = $binDir.DIRECTORY_SEPARATOR.(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'jasperstarter.exe' : 'jasperstarter');
        $rows[] = ['Binário', $binary];
        $rows[] = ['Ficheiro existe', is_file($binary) ? 'sim' : 'NÃO'];
        $rows[] = ['Executável', is_executable($binary) ? 'sim' : 'NÃO — chmod +x'];

        $sources = $this->normalizeSourcesPath((string) config('legacy.report.source_path'));
        $rows[] = ['ReportSources (config)', $sources];
        $rows[] = ['ReportSources existe', is_dir($sources) ? 'sim' : 'NÃO — php artisan community:reports:link / deploy do pacote'];
        $rows[] = ['ReportSources gravável', is_dir($sources) && is_writable(rtrim($sources, '/\\')) ? 'sim' : 'NÃO — chown/chmod para o utilizador FPM'];

        $moduleReports = base_path('ieducar/modules/Reports');
        $rows[] = ['ieducar/modules/Reports', $this->describeReportsModulePath($moduleReports)];

        $this->table(['Verificação', 'Estado'], $rows);

        $java = 'n/d';
        if (function_exists('shell_exec')) {
            $java = trim((string) shell_exec('command -v java 2>/dev/null') ?: '');
            $java = $java !== '' ? $java : 'java não encontrado no PATH deste ambiente';
        }
        $this->line('Java (command -v): '.$java);
        $this->newLine();

        $this->printReportSettingsOverrides();

        $this->comment('Compare a saída deste comando entre a instalação que funciona e a que falha (git, vendor, symlink, gravável, cache). O SAPI "cli" difere do "fpm-fcgi" na web — para exec, use phpinfo() via HTTP se necessário.');

        $jasperOk = function_exists('exec') && is_dir($binDir) && is_file($binary) && is_executable($binary);
        if (!is_dir($sources)) {
            $this->warn('ReportSources em falta: os relatórios não vão encontrar .jrxml até ao link do pacote (community:reports:link).');
        }
        if (is_dir($sources) && !is_writable(rtrim($sources, '/\\'))) {
            $this->warn('ReportSources sem escrita: em clones novos, alinhar permissões com a instalação que funciona.');
        }

        return $jasperOk ? self::SUCCESS : self::FAILURE;
    }

    private function printInstallationParity(): void
    {
        $this->info('Paridade de instalação (compare linha a linha entre servidores / clones)');

        $lock = base_path('composer.lock');
        $geekLine = 'ficheiro composer.lock inexistente — correr php composer.phar install';
        if (is_file($lock)) {
            $raw = @file_get_contents($lock) ?: '';
            if (str_contains($raw, '"name": "geekcom/phpjasper"')) {
                if (preg_match('/"name":\s*"geekcom\/phpjasper",\s*"version":\s*"([^"]+)"/s', $raw, $m)) {
                    $geekLine = 'geekcom/phpjasper presente no lock (versão '.$m[1].')';
                } else {
                    $geekLine = 'geekcom/phpjasper referenciado no lock (versão não lida)';
                }
            } else {
                $geekLine = 'geekcom/phpjasper NÃO está no composer.lock desta pasta — composer install incompleto ou branch antigo';
            }
        }

        $git = 'sem .git ou não legível';
        $headFile = base_path('.git/HEAD');
        if (is_readable($headFile)) {
            $ref = trim((string) file_get_contents($headFile));
            if (str_starts_with($ref, 'ref:')) {
                $refPath = base_path('.git/'.trim(substr($ref, 4)));
                if (is_readable($refPath)) {
                    $git = trim((string) file_get_contents($refPath)).' ('.trim(substr($ref, 4)).')';
                } else {
                    $git = $ref;
                }
            } else {
                $git = 'HEAD detached @ '.substr($ref, 0, 12);
            }
        }

        $configCached = is_file(base_path('bootstrap/cache/config.php'));
        $rows = [
            ['Git (HEAD)', $git],
            ['geekcom/phpjasper (lock)', $geekLine],
            ['Pasta vendor/geekcom', is_dir(base_path('vendor/geekcom/phpjasper')) ? 'sim' : 'NÃO'],
            ['bootstrap/cache/config.php', $configCached ? 'existe — valores de .env congelados; regerar neste clone após mudar env' : 'ausente'],
            ['APP_URL', (string) config('app.url')],
            ['Factory relatórios (config)', (string) config('legacy.report.default_factory')],
            ['JASPER_BIN_DIR efectivo', (string) config('legacy.report.jasper_bin_dir') !== '' ? (string) config('legacy.report.jasper_bin_dir') : '(vazio → vendor padrão)'],
            ['REPORTS_SKIP_SOURCES_WRITABLE_CHECK', config('legacy.report.skip_sources_writable_check') ? 'true (pré-check de escrita em ReportSources desligado)' : 'false'],
        ];

        $this->table(['Item', 'Valor'], $rows);
    }

    private function describeReportsModulePath(string $moduleReports): string
    {
        if (is_link($moduleReports)) {
            $target = readlink($moduleReports);
            if ($target === false) {
                return 'symlink (readlink falhou)';
            }
            $resolved = $target[0] === '/' ? $target : dirname($moduleReports).'/'.$target;
            $ok = is_dir($resolved);

            return 'symlink → '.$target.($ok ? '' : ' (DESTINO INVÁLIDO — corrigir link ou caminho do pacote)');
        }

        if (is_dir($moduleReports)) {
            return 'directório (não é symlink — pode ser cópia em vez do pacote)';
        }

        return 'ausente';
    }

    private function resolveBinDir(): string
    {
        $configured = (string) config('legacy.report.jasper_bin_dir');
        if ($configured !== '') {
            $dir = $configured[0] === '/' || preg_match('#^[a-zA-Z]:[/\\\\]#', $configured) === 1
                ? $configured
                : base_path($configured);
        } else {
            $dir = base_path('vendor/geekcom/phpjasper/bin/jasperstarter/bin');
        }

        return rtrim($dir, '/\\');
    }

    private function normalizeSourcesPath(string $path): string
    {
        if ($path === '') {
            $path = base_path('ieducar/modules/Reports/ReportSources');
        } elseif (!$this->pathIsAbsolute($path)) {
            $path = base_path($path);
        }

        return rtrim($path, '/\\').DIRECTORY_SEPARATOR;
    }

    private function pathIsAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('#^[a-zA-Z]:[/\\\\]#', $path);
    }

    private function printReportSettingsOverrides(): void
    {
        try {
            if (!Schema::hasTable('settings')) {
                return;
            }

            $keys = [
                'legacy.report.default_factory',
                'legacy.report.source_path',
            ];

            $rows = DB::table('settings')
                ->whereIn('key', $keys)
                ->orderBy('key')
                ->get(['key', 'value']);

            if ($rows->isEmpty()) {
                $this->line('Tabela settings: sem chaves legacy.report.default_factory / source_path (usa só config/.env).');

                return;
            }

            $this->warn('Sobreposições em settings (cada instalação tem a sua BD — podem diferir do .env):');
            $this->table(['key', 'value'], $rows->map(fn ($r) => [$r->key, (string) $r->value])->all());
        } catch (\Throwable $e) {
            $this->line('Tabela settings: não foi possível ler ('.$e->getMessage().').');
        }
    }
}
