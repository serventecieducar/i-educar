<?php

namespace Tests\Unit\AdvancedReports;

use iEducar\Packages\AdvancedReports\Http\Controllers\LookupController;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Caminhos que retornam antes de consultar o banco (evita falhas de SQL/schema em sqlite de teste).
 */
class LookupControllerEarlyExitTest extends TestCase
{
    public function test_ready_school_histories_returns_empty_when_year_is_zero(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/historico-prontos', 'GET', [
            'ano' => 0,
            'escola_id' => 1,
            'instituicao_id' => 1,
        ]);

        $this->assertSame([], $c->readySchoolHistories($req)->getData(true));
    }

    public function test_ready_school_histories_returns_empty_when_escola_id_is_zero(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/historico-prontos', 'GET', [
            'ano' => 2026,
            'escola_id' => 0,
            'instituicao_id' => 1,
        ]);

        $this->assertSame([], $c->readySchoolHistories($req)->getData(true));
    }

    public function test_ready_school_histories_returns_empty_when_instituicao_id_is_zero(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/historico-prontos', 'GET', [
            'ano' => 2026,
            'escola_id' => 1,
            'instituicao_id' => 0,
        ]);

        $this->assertSame([], $c->readySchoolHistories($req)->getData(true));
    }

    public function test_ready_school_histories_coerces_non_numeric_turma_id_to_int_without_exception(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/historico-prontos', 'GET', [
            'turma_id' => "1; DROP TABLE aluno--",
            'ano' => 0,
            'escola_id' => 1,
            'instituicao_id' => 1,
        ]);

        $this->assertSame([], $c->readySchoolHistories($req)->getData(true));
    }

    public function test_matriculas_returns_empty_when_query_too_short(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/matriculas', 'GET', ['q' => 'ab']);

        $this->assertSame([], $c->matriculas($req)->getData(true));
    }

    public function test_alunos_returns_empty_when_query_too_short(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/alunos', 'GET', ['q' => '']);

        $this->assertSame([], $c->alunos($req)->getData(true));
    }

    public function test_users_returns_empty_when_query_too_short(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/usuarios', 'GET', ['q' => '  x  ']);

        $this->assertSame([], $c->users($req)->getData(true));
    }

    public function test_class_enrollments_returns_empty_when_turma_id_missing(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/turma-matriculas', 'GET', []);

        $this->assertSame([], $c->classEnrollments($req)->getData(true));
    }

    public function test_class_enrollment_counters_returns_empty_when_turma_id_missing(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/turma-matriculas-contadores', 'GET', []);

        $this->assertSame([], $c->classEnrollmentCounters($req)->getData(true));
    }

    public function test_school_history_meta_returns_422_when_aluno_id_missing(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/historico-meta', 'GET', []);

        $res = $c->schoolHistoryMeta($req);

        $this->assertSame(422, $res->getStatusCode());
        $this->assertFalse($res->getData(true)['ok']);
    }

    public function test_school_history_meta_returns_422_when_aluno_id_is_zero(): void
    {
        $c = new LookupController;
        $req = Request::create('/relatorios-avancados/api/historico-meta', 'GET', ['aluno_id' => 0]);

        $res = $c->schoolHistoryMeta($req);

        $this->assertSame(422, $res->getStatusCode());
    }
}
