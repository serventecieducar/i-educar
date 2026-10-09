<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class InstalarPreMatriculaCommand extends Command
{
    protected $signature = 'pmd:instalar';

    protected $description = 'Instala a pré-matrícula em packages/serventec/pre-matricula-digital, sem sudo';

    public function handle(): int
    {
        $script = base_path('scripts/instalar-pre-matricula');

        $process = new Process(['bash', $script]);
        $process->setTimeout(null);
        $process->setWorkingDirectory(base_path());
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->getExitCode() ?? self::FAILURE;
    }
}
