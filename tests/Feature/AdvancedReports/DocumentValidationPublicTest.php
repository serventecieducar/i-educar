<?php

namespace Tests\Feature\AdvancedReports;

use iEducar\Packages\AdvancedReports\Models\AdvancedReportsDocument;
use iEducar\Packages\AdvancedReports\Services\DocumentSigningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentValidationPublicTest extends TestCase
{
    public function test_public_validation_page_shows_only_summary(): void
    {
        config(['app.key' => 'base64:' . base64_encode('test-key-32-bytes-aaaaaaaaaaaaaaaa')]);

        if (!Schema::hasTable('settings')) {
            $this->markTestSkipped('Ambiente de teste sem tabela settings (middleware LoadSettings).');
        }

        if (!Schema::hasTable('advanced_reports_documents')) {
            Schema::create('advanced_reports_documents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 32)->unique();
                $table->string('type', 40);
                $table->timestamp('issued_at');
                $table->string('mac', 64)->nullable()->index();
                $table->unsignedSmallInteger('version')->default(1);
                $table->jsonb('payload');
                $table->timestamps();
            });
        }

        $code = strtoupper(bin2hex(random_bytes(8)));
        $payload = [
            'issuer_name' => 'Secretaria',
            'issuer_role' => 'Secretário(a) escolar',
            'city_uf' => 'Saubara/BA',
            'book' => '10',
            'page' => '2',
            'record' => '99',
            'year' => '2026',
            'course' => 'EJA',
            'class' => 'A',
            'enrollment' => '123',
            'sensitive' => 'NIS: 00000000000',
        ];

        $issuedAt = now();
        $issuedAtIso = DocumentSigningService::issuedAtForMac($issuedAt);
        $mac = app(DocumentSigningService::class)->mac($code, 'declaration', $issuedAtIso, $payload);

        $doc = AdvancedReportsDocument::query()->create([
            'code' => $code,
            'type' => 'declaration',
            'issued_at' => $issuedAt,
            'version' => 1,
            'mac' => $mac,
            'payload' => $payload,
        ]);

        $res = $this->get('/documentos/validar/' . $code);
        $res->assertOk();

        // Não deve expor payload completo/sensível
        $res->assertDontSee('sensitive');
        $res->assertDontSee('NIS');

        // Deve conter campos do resumo
        $res->assertSee('Resumo oficial');
        $res->assertSee('Saubara/BA');
        $res->assertSee('Código');
        $res->assertSee('VÁLIDO');

        $doc->delete();
    }
}
