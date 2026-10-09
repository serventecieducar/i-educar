<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PreMatriculaInscricoesCommand extends Command
{
    private const SCHEMA = 'packages/serventec/pre-matricula-digital/graphql/preregistration.graphql';

    protected $signature = 'pmd:inscricoes';

    protected $description = 'Libera a lista de inscrições para quem já está logado no i-Educar';

    public function handle(): int
    {
        $arquivo = base_path(self::SCHEMA);

        if (!is_file($arquivo)) {
            $this->error('Schema não encontrado em ' . self::SCHEMA . '.');

            return self::FAILURE;
        }

        $conteudo = (string) file_get_contents($arquivo);
        $antigo = 'extend type Query @guard(with: ["prematricula"]) {';
        $novo = 'extend type Query @guard(with: ["web", "sanctum", "prematricula"]) {';
        $posicao = strpos($conteudo, $antigo);

        if ($posicao !== false) {
            $conteudo = substr_replace($conteudo, $novo, $posicao, strlen($antigo));
            file_put_contents($arquivo, $conteudo);
            $this->info('A lista de inscrições aceita a sessão do i-Educar.');
        } else {
            $this->info('A lista de inscrições já aceita a sessão do i-Educar.');
        }

        $this->callSilent('optimize:clear');

        if ($this->getApplication()->has('lighthouse:clear-cache')) {
            $this->callSilent('lighthouse:clear-cache');
        }

        return self::SUCCESS;
    }
}
