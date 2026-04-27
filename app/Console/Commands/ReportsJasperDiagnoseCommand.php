<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReportsJasperDiagnoseCommand extends Command
{
    protected $signature = 'reports:jasper-diagnose';

    protected $description = 'Verifica exec, caminhos geekcom/phpjasper e fontes de relatório (multi-domínio / FPM)';

    public function handle(): int
    {
        $this->line('Base path: '.base_path());
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

        $moduleReports = base_path('ieducar/modules/Reports');
        $rows[] = ['ieducar/modules/Reports', is_link($moduleReports) ? 'symlink → '.readlink($moduleReports) : (is_dir($moduleReports) ? 'directório' : 'ausente')];

        $this->table(['Verificação', 'Estado'], $rows);

        $java = 'n/d';
        if (function_exists('shell_exec')) {
            $java = trim((string) shell_exec('command -v java 2>/dev/null') ?: '');
            $java = $java !== '' ? $java : 'java não encontrado no PATH deste ambiente';
        }
        $this->line('Java (command -v): '.$java);
        $this->newLine();
        $this->comment('Em vários domínios no mesmo servidor: cada pool PHP-FPM pode ter disable_functions diferente. Corra este comando no mesmo utilizador/pool do site que falha.');

        $jasperOk = function_exists('exec') && is_dir($binDir) && is_file($binary) && is_executable($binary);
        if (!is_dir($sources)) {
            $this->warn('ReportSources em falta: os relatórios não vão encontrar .jrxml até ao link do pacote (community:reports:link).');
        }

        return $jasperOk ? self::SUCCESS : self::FAILURE;
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
}
