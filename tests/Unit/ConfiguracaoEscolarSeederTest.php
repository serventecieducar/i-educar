<?php

namespace Tests\Unit;

use App\Models\LegacyCourse;
use App\Models\LegacyGrade;
use App\Models\LegacySchool;
use App\Models\LegacySchoolAcademicYear;
use Database\Factories\LegacyEducationLevelFactory;
use Database\Factories\LegacyEducationTypeFactory;
use Database\Factories\LegacyEvaluationRuleFactory;
use Database\Factories\LegacyKnowledgeAreaFactory;
use Database\Factories\LegacyRegimeTypeFactory;
use Database\Factories\LegacySchoolFactory;
use Database\Seeders\ConfiguracaoEscolarSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ConfiguracaoEscolarSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_academic_years_for_school(): void
    {
        $school = LegacySchoolFactory::new()->create();
        $this->criarDadosInstituicao($school);

        $seeder = new ConfiguracaoEscolarSeeder;
        $seeder->run();

        $anoAtual = now()->year;
        $this->assertDatabaseHas('pmieducar.escola_ano_letivo', [
            'ref_cod_escola' => $school->cod_escola,
            'ano' => $anoAtual,
        ]);
    }

    public function test_does_not_fail_when_no_schools(): void
    {
        LegacySchool::query()->delete();

        $seeder = new ConfiguracaoEscolarSeeder;
        $seeder->run();

        $this->assertDatabaseCount('pmieducar.escola_ano_letivo', 0);
    }

    public function test_creates_courses_and_grades_when_institution_has_required_data(): void
    {
        $school = LegacySchoolFactory::new()->create();
        $this->criarDadosInstituicao($school);

        $seeder = new ConfiguracaoEscolarSeeder;
        $seeder->run();

        $curso = LegacyCourse::where('ref_cod_instituicao', $school->ref_cod_instituicao)
            ->where('nm_curso', 'Ensino Fundamental')
            ->first();

        $this->assertNotNull($curso);

        $grades = LegacyGrade::where('ref_cod_curso', $curso->cod_curso)->get();
        $this->assertGreaterThanOrEqual(1, $grades->count());
    }

    private function criarDadosInstituicao(LegacySchool $school): void
    {
        $instituicaoId = $school->ref_cod_instituicao;

        LegacyEducationLevelFactory::new()->create(['ref_cod_instituicao' => $instituicaoId]);
        LegacyEducationTypeFactory::new()->create(['ref_cod_instituicao' => $instituicaoId]);
        LegacyRegimeTypeFactory::new()->create(['ref_cod_instituicao' => $instituicaoId]);
        LegacyEvaluationRuleFactory::new()->create(['instituicao_id' => $instituicaoId]);
        LegacyKnowledgeAreaFactory::new()->create(['instituicao_id' => $instituicaoId]);
    }
}
