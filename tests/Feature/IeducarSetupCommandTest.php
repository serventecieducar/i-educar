<?php

namespace Tests\Feature;

use App\Models\LegacyCalendarYear;
use App\Models\LegacySchool;
use App\Models\LegacySchoolAcademicYear;
use Database\Factories\LegacyEvaluationRuleFactory;
use Database\Factories\LegacySchoolFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class IeducarSetupCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_runs_successfully_without_schools(): void
    {
        $this->artisan('ieducar:setup')
            ->assertSuccessful();
    }

    public function test_command_creates_calendar_and_academic_year_for_existing_school(): void
    {
        $school = LegacySchoolFactory::new()->create();

        LegacyEvaluationRuleFactory::new()->create([
            'instituicao_id' => $school->ref_cod_instituicao,
        ]);

        $this->artisan('ieducar:setup')
            ->assertSuccessful();

        $anoAtual = now()->year;

        $this->assertDatabaseHas('pmieducar.escola_ano_letivo', [
            'ref_cod_escola' => $school->cod_escola,
            'ano' => $anoAtual,
        ]);

        $this->assertDatabaseHas('pmieducar.calendario_ano_letivo', [
            'ref_cod_escola' => $school->cod_escola,
            'ano' => $anoAtual,
        ]);
    }

    public function test_command_displays_report_with_counts(): void
    {
        LegacySchoolFactory::new()->create();

        $this->artisan('ieducar:setup')
            ->assertSuccessful()
            ->expectsOutputToContain('Configuração concluída com sucesso!');
    }
}
