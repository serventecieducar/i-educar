<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PreMatriculaTelaCommand extends Command
{
    private const PACOTE = 'packages/serventec/pre-matricula-digital';

    protected $signature = 'pmd:tela';

    protected $description = 'Aponta /pre-matricula-digital para o index.html gerado no pacote Serventec';

    public function handle(): int
    {
        $dist = base_path(self::PACOTE . '/dist');
        $index = $dist . '/index.html';

        if (!is_file($index)) {
            $this->error('A tela não foi gerada. Falta ' . self::PACOTE . '/dist/index.html.');
            $this->line('Na raiz do i-Educar, com o Node do seu usuário:');
            $this->line('yarn --cwd ' . self::PACOTE . ' install');
            $this->line('yarn --cwd ' . self::PACOTE . ' build --base=/vendor/pre-matricula-digital/');
            $this->line('php artisan pmd:tela');

            return self::FAILURE;
        }

        $this->liberarLeitura($dist);
        $this->gravarEnv();
        $this->call('vendor:publish', [
            '--tag' => 'pmd',
            '--force' => true,
        ]);

        $publico = public_path('vendor/pre-matricula-digital');
        if (is_dir($publico)) {
            $this->liberarLeitura($publico);
        }

        $this->callSilent('optimize:clear');
        $this->info('A página /pre-matricula-digital usa ' . self::PACOTE . '/dist/index.html.');

        return self::SUCCESS;
    }

    private function gravarEnv(): void
    {
        $arquivo = base_path('.env');
        $conteudo = is_file($arquivo) ? (string) file_get_contents($arquivo) : '';
        $valores = [
            'FRONTIER_ENDPOINT' => '/pre-matricula-digital',
            'FRONTIER_VIEWS_PATH' => self::PACOTE . '/dist',
            'FRONTIER_VIEW' => 'frontier::index',
        ];

        foreach ($valores as $chave => $valor) {
            $linha = $chave . '=' . $valor;
            if (preg_match('/^' . preg_quote($chave, '/') . '=.*/m', $conteudo)) {
                $conteudo = preg_replace('/^' . preg_quote($chave, '/') . '=.*/m', $linha, $conteudo);
            } else {
                $conteudo = rtrim($conteudo) . "\n" . $linha . "\n";
            }
        }

        file_put_contents($arquivo, $conteudo);
    }

    private function liberarLeitura(string $diretorio): void
    {
        chmod($diretorio, 0755);

        $arquivos = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($diretorio, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($arquivos as $arquivo) {
            chmod($arquivo->getPathname(), $arquivo->isDir() ? 0755 : 0644);
        }
    }
}
