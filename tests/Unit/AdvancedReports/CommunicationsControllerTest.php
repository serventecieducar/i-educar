<?php

namespace Tests\Unit\AdvancedReports;

use iEducar\Packages\AdvancedReports\Http\Controllers\CommunicationsController;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class CommunicationsControllerTest extends TestCase
{
    public function test_show_returns_view_for_each_official_slug(): void
    {
        $controller = new CommunicationsController;
        $request = Request::create('/');

        foreach (['convocacao', 'reuniao', 'advertencia', 'comunicado-geral'] as $slug) {
            $view = $controller->show($request, $slug);
            $this->assertInstanceOf(View::class, $view);
            $this->assertNotEmpty($view->getData()['title'] ?? '');
        }
    }

    public function test_show_aborts_for_unknown_slug(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $controller = new CommunicationsController;
        $controller->show(Request::create('/'), 'ocorrencias');
    }
}
