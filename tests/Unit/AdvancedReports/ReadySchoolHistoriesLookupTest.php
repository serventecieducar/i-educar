<?php

namespace Tests\Unit\AdvancedReports;

use iEducar\Packages\AdvancedReports\Http\Controllers\LookupController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReadySchoolHistoriesLookupTest extends TestCase
{
    public function test_ready_school_histories_returns_empty_when_no_class_is_informed(): void
    {
        $controller = new LookupController;
        $request = Request::create('/relatorios-avancados/api/historico-prontos', 'GET', []);

        $res = $controller->readySchoolHistories($request);

        $this->assertSame([], $res->getData(true));
    }
}
