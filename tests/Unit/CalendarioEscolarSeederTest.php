<?php

namespace Tests\Unit;

use App\Models\LegacyCalendarDay;
use App\Models\LegacyCalendarYear;
use App\Models\LegacySchool;
use Carbon\Carbon;
use Database\Factories\LegacySchoolFactory;
use Database\Seeders\CalendarioEscolarSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CalendarioEscolarSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_calendar_for_each_school_for_current_and_previous_year(): void
    {
        $school = LegacySchoolFactory::new()->create();
        $anoAtual = Carbon::now()->year;
        $anoAnterior = $anoAtual - 1;

        $seeder = new CalendarioEscolarSeeder;
        $seeder->run();

        $calendarios = LegacyCalendarYear::where('ref_cod_escola', $school->cod_escola)->get();

        $this->assertGreaterThanOrEqual(1, $calendarios->count());
        $this->assertTrue(
            $calendarios->contains('ano', $anoAtual) || $calendarios->contains('ano', $anoAnterior)
        );
    }

    public function test_creates_school_days_in_calendar(): void
    {
        $school = LegacySchoolFactory::new()->create();
        $anoAtual = Carbon::now()->year;

        $calendario = LegacyCalendarYear::create([
            'ref_cod_escola' => $school->cod_escola,
            'ano' => $anoAtual,
            'ref_usuario_cad' => 1,
            'data_cadastra' => now(),
            'ativo' => 1,
        ]);

        $seeder = new CalendarioEscolarSeeder;
        $seeder->run();

        $diasLetivos = LegacyCalendarDay::where('ref_cod_calendario_ano_letivo', $calendario->cod_calendario_ano_letivo)
            ->count();

        $this->assertGreaterThan(0, $diasLetivos);
    }

    public function test_does_not_fail_when_no_schools(): void
    {
        LegacySchool::query()->delete();

        $seeder = new CalendarioEscolarSeeder;
        $seeder->run();

        $this->assertDatabaseCount('pmieducar.calendario_ano_letivo', 0);
    }
}
