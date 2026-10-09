<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PreMatriculaInscricoesCommand extends Command
{
    protected $signature = 'pmd:inscricoes';

    protected $description = 'Libera a lista de inscrições para quem já está logado no i-Educar';

    public function handle(): int
    {
        $pastas = [
            base_path('packages/serventec/pre-matricula-digital/graphql'),
            base_path('packages/portabilis/pre-matricula-digital/graphql'),
        ];
        $alterou = false;

        foreach ($pastas as $pasta) {
            if (!is_dir($pasta)) {
                continue;
            }

            foreach (glob($pasta . '/*.graphql') ?: [] as $arquivo) {
                $conteudo = (string) file_get_contents($arquivo);
                $novo = str_replace(
                    '@guard(with: ["prematricula"])',
                    '@guard(with: ["web", "sanctum", "prematricula"])',
                    $conteudo
                );

                if ($novo === $conteudo) {
                    continue;
                }

                file_put_contents($arquivo, $novo);
                $alterou = true;
            }
        }

        if ($alterou) {
            $this->info('As consultas da pré-matrícula aceitam a sessão do i-Educar.');
        } else {
            $this->info('As consultas da pré-matrícula já aceitam a sessão do i-Educar.');
        }

        $this->callSilent('optimize:clear');

        if ($this->getApplication()->has('lighthouse:clear-cache')) {
            $this->callSilent('lighthouse:clear-cache');
        }

        return self::SUCCESS;
    }
}
