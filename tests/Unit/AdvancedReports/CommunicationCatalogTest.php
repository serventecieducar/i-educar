<?php

namespace Tests\Unit\AdvancedReports;

use iEducar\Packages\AdvancedReports\Services\CommunicationCatalog;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class CommunicationCatalogTest extends TestCase
{
    public function test_document_type_contains_slug(): void
    {
        $this->assertSame('communication:convocacao', CommunicationCatalog::documentType('convocacao'));
        $this->assertSame('communication:comunicado-geral', CommunicationCatalog::documentType('comunicado-geral'));
    }

    public function test_assert_slug_throws_for_invalid(): void
    {
        $this->expectException(NotFoundHttpException::class);
        CommunicationCatalog::assertSlug('invalid-slug');
    }

    public function test_each_catalog_slug_has_definition_and_document_type(): void
    {
        foreach (CommunicationCatalog::slugs() as $slug) {
            $def = CommunicationCatalog::definition($slug);
            $this->assertArrayHasKey('title', $def);
            $this->assertArrayHasKey('default_corpo', $def);
            $this->assertSame('communication:' . $slug, CommunicationCatalog::documentType($slug));
        }
    }
}
