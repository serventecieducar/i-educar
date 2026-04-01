<?php

namespace App\Console\Commands;

use App\IeducarSetup\IeducarSetupProfiles;
use App\Models\LegacyCourse;
use App\Models\LegacyGrade;
use App\Models\LegacySchool;
use Database\Seeders\SeederMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IeducarSetup extends Command
{
    protected $signature = 'ieducar:setup
                            {cidade-uf=default-br : Slug cidade-UF (ex.: default-br, itamari-ba, belem-pa)}';

    protected $description = 'Configura o i-Educar por perfil cidade-UF: padrão nacional (default-br) + rotinas municipais opcionais';

    public function handle(): int
    {
        $cidadeUf = strtolower(trim((string) $this->argument('cidade-uf')));
        $profiles = IeducarSetupProfiles::all();

        if (!isset($profiles[$cidadeUf])) {
            $this->error("❌ Perfil desconhecido: {$cidadeUf}");
            $this->line('Perfis disponíveis: ' . implode(', ', IeducarSetupProfiles::slugs()));

            return 1;
        }

        $profile = $profiles[$cidadeUf];
        IeducarSetupProfiles::setActiveSlug($cidadeUf);

        $this->info('🚀 Iniciando configuração automática do i-Educar...');
        $this->line("📍 Perfil: {$profile['label']} ({$cidadeUf})");
        if (!empty($profile['description'])) {
            $this->line('   ' . $profile['description']);
        }
        Log::channel('daily')->info('Iniciando execução do comando ieducar:setup', [
            'cidade_uf' => $cidadeUf,
            'label' => $profile['label'],
        ]);

        $startTime = microtime(true);

        $extra = $profile['extra_seeders'] ?? [];
        $steps = 1 + count($extra);
        $bar = $this->output->createProgressBar($steps);
        $bar->start();

        try {
            $this->call('db:seed', ['--class' => SeederMaster::class]);
            $bar->advance();
            Log::channel('daily')->info('SeederMaster executado com sucesso', ['cidade_uf' => $cidadeUf]);

            foreach ($extra as $seederClass) {
                $this->call('db:seed', ['--class' => $seederClass]);
                $bar->advance();
                Log::channel('daily')->info('Seeder municipal executado', [
                    'cidade_uf' => $cidadeUf,
                    'seeder' => $seederClass,
                ]);
            }
        } catch (\Exception $e) {
            $this->error('❌ Erro na configuração: ' . $e->getMessage());
            Log::channel('daily')->error('Erro no setup', ['exception' => $e, 'cidade_uf' => $cidadeUf]);

            return 1;
        }

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
