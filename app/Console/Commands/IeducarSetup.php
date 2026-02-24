<?php

namespace App\Console\Commands;

use App\Models\LegacyCourse;
use App\Models\LegacyGrade;
use App\Models\LegacySchool;
use Database\Seeders\SeederMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IeducarSetup extends Command
{
    protected $signature = 'ieducar:setup';

    protected $description = 'Configura automaticamente o i-Educar com anos letivos, turmas, disciplinas BNCC e calendário escolar';

    public function handle(): int
    {
        $this->info('🚀 Iniciando configuração automática do i-Educar...');
        Log::channel('daily')->info('Iniciando execução do comando ieducar:setup');

        $startTime = microtime(true);

        $bar = $this->output->createProgressBar(2);
        $bar->start();

        try {
            $this->call('db:seed', ['--class' => SeederMaster::class]);
            $bar->advance();
            Log::channel('daily')->info('SeederMaster executado com sucesso');
        } catch (\Exception $e) {
            $this->error('❌ Erro na configuração: ' . $e->getMessage());
            Log::channel('daily')->error('Erro no setup', ['exception' => $e]);
            return 1;
        }

        $bar->advance();
        $bar->finish();
        $this->newLine();

        $executionTime = round(microtime(true) - $startTime, 2);

        $this->exibirRelatorio($executionTime);

        return 0;
    }

    private function exibirRelatorio(float $executionTime): void
    {
        try {
            $schools = LegacySchool::count();
            $courses = LegacyCourse::count();
            $grades = LegacyGrade::count();

            $infantil = LegacyGrade::whereHas('course', fn ($q) => $q->where('nm_curso', 'Educação Infantil'))->count();
            $fundamental = LegacyGrade::whereHas('course', fn ($q) => $q->where('nm_curso', 'Ensino Fundamental'))->count();
            $medio = LegacyGrade::whereHas('course', fn ($q) => $q->where('nm_curso', 'Ensino Médio'))->count();

            $this->info('✅ Configuração concluída com sucesso!');
            $this->line('----------------------------------------');
            $this->line("🏫 Escolas configuradas:   {$schools}");
            $this->line("📘 Cursos criados:         {$courses}");
            $this->line("📚 Turmas geradas:         {$grades}");
            $this->line('');
            $this->line("   ➡ Educação Infantil:    {$infantil}");
            $this->line("   ➡ Ensino Fundamental:   {$fundamental}");
            $this->line("   ➡ Ensino Médio:         {$medio}");
            $this->line('----------------------------------------');
            $this->line("⏱ Tempo total de execução: {$executionTime} segundos");
            $this->line('Agora o sistema está pronto para matrícula!');

            Log::channel('daily')->info('Relatório final', [
                'schools' => $schools,
                'courses' => $courses,
                'grades' => $grades,
                'infantil' => $infantil,
                'fundamental' => $fundamental,
                'medio' => $medio,
                'execution_time' => $executionTime,
            ]);
        } catch (\Exception $e) {
            $this->error('❌ Erro ao gerar relatório final: ' . $e->getMessage());
            Log::channel('daily')->error('Erro ao gerar relatório final', ['exception' => $e]);
        }
    }
}
