<?php

namespace App\Console\Commands;

use App\Services\MatriculasTestDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IeducarMatriculas extends Command
{
    protected $signature = 'ieducar:matriculas
                            {--por-turma=10 : Quantidade de matrículas a criar por turma}
                            {--force : Criar mesmo em anos que já possuam matrículas}';

    protected $description = 'Gera carga de matrículas de teste em anos letivos em aberto, com dados de turmas, notas e faltas para relatórios e gráficos';

    public function handle(MatriculasTestDataService $service): int
    {
        $this->info('📚 Iniciando geração de matrículas de teste...');
        Log::channel('daily')->info('Iniciando execução do comando ieducar:matriculas');

        $porTurma = (int) $this->option('por-turma');
        $force = $this->option('force');

        if ($porTurma < 1 || $porTurma > 50) {
            $this->error('--por-turma deve estar entre 1 e 50.');
            return 1;
        }

        $escolasAnos = $service->getEscolasAnosVazios($force);
        if ($escolasAnos->isEmpty()) {
            $this->warn('Nenhum ano letivo em aberto sem matrículas encontrado.');
            if (!$force) {
                $this->line('Use --force para criar mesmo em anos que já possuam matrículas.');
            }
            return 0;
        }

        $totalMatriculas = 0;
        $totalTurmas = 0;
        $escolasProcessadas = 0;

        $bar = $this->output->createProgressBar($escolasAnos->count());
        $bar->start();

        foreach ($escolasAnos as $item) {
            $school = $item['school'];
            $ano = $item['ano'];
            $turmas = $service->getTurmasParaAno($school->cod_escola, $ano);

            foreach ($turmas as $turma) {
                try {
                    DB::beginTransaction();
                    $resultado = $service->popularTurma($turma, $porTurma);
                    DB::commit();
                    $totalMatriculas += $resultado['matriculas'];
                    $totalTurmas++;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    Log::channel('daily')->error('Erro ao popular turma', [
                        'turma' => $turma->cod_turma,
                        'exception' => $e,
                    ]);
                    $this->error("Erro na turma {$turma->nm_turma}: " . $e->getMessage());
                }
            }
            $escolasProcessadas++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->exibirRelatorio($totalMatriculas, $totalTurmas, $escolasProcessadas);

        return 0;
    }

    private function exibirRelatorio(int $totalMatriculas, int $totalTurmas, int $escolasProcessadas): void
    {
        $this->info('✅ Geração de matrículas concluída!');
        $this->line('----------------------------------------');
        $this->line("📋 Matrículas criadas:    {$totalMatriculas}");
        $this->line("🏫 Turmas populadas:      {$totalTurmas}");
        $this->line("🏛 Escolas processadas:   {$escolasProcessadas}");
        $this->line('----------------------------------------');
        $this->line('Dados de notas e faltas foram lançados para relatórios e gráficos.');
    }
}
