<?php

namespace Tests\Feature\AdvancedReports;

use iEducar\Packages\AdvancedReports\Models\AdvancedReportsDocument;
use iEducar\Packages\AdvancedReports\Services\DocumentSigningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentFormsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('advanced_reports_documents')) {
            Schema::create('advanced_reports_documents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 32)->unique();
                $table->string('type', 40);
                $table->timestamp('issued_at');
                $table->unsignedBigInteger('issued_by_user_id')->nullable();
                $table->string('issued_ip', 45)->nullable();
                $table->string('issued_user_agent', 255)->nullable();
                $table->string('mac', 64)->nullable()->index();
                $table->unsignedSmallInteger('version')->default(1);
                $table->jsonb('payload');
                $table->timestamps();
            });
        }

        config(['app.key' => 'base64:' . base64_encode('test-key-32-bytes-aaaaaaaaaaaaaaaa')]);
    }

    public function test_preview_pdf_endpoints_work(): void
    {
        $this->withoutMiddleware();

        $this->get('/relatorios-avancados/fichas/ficha-individual/pdf?preview=1&ano=2026')->assertOk();
        $this->get('/relatorios-avancados/fichas/ficha-matricula/pdf?preview=1&ano=2026')->assertOk();
        $this->get('/relatorios-avancados/fichas/termo-autorizacao/pdf?preview=1&ano=2026')->assertOk();
    }

    public function test_preview_does_not_persist_document_record(): void
    {
        $this->withoutMiddleware();

        AdvancedReportsDocument::query()->delete();

        $this->get('/relatorios-avancados/fichas/ficha-individual/pdf?preview=1&ano=2026')->assertOk();
        $this->get('/relatorios-avancados/fichas/ficha-matricula/pdf?preview=1&ano=2026')->assertOk();
        $this->get('/relatorios-avancados/fichas/termo-autorizacao/pdf?preview=1&ano=2026')->assertOk();

        $this->assertSame(0, AdvancedReportsDocument::query()->count());
    }

    public function test_public_validation_shows_human_type_for_student_forms(): void
    {
        $this->withoutMiddleware();

        $signing = app(DocumentSigningService::class);

        $issuedAt = now();
        $issuedAtIso = DocumentSigningService::issuedAtForMac($issuedAt);

        foreach ([
            'student_form:individual' => 'Ficha individual',
            'student_form:enrollment' => 'Ficha de matrícula',
            'student_form:media_authorization' => 'Termo de autorização de uso de imagem e voz',
        ] as $type => $label) {
            $code = strtoupper(bin2hex(random_bytes(8)));
            $payload = [
                'issuer_name' => 'Emissor (Teste)',
                'city_uf' => 'Cidade/UF',
                'year' => '2026',
            ];

            $mac = $signing->mac($code, $type, $issuedAtIso, $payload);

            AdvancedReportsDocument::query()->create([
                'code' => $code,
                'type' => $type,
                'issued_at' => $issuedAt,
                'version' => 1,
                'mac' => $mac,
                'payload' => $payload,
            ]);

            $res = $this->get('/documentos/validar/' . $code);
            $res->assertOk();
            $res->assertSee('VÁLIDO');
            $res->assertSee($label);
        }
    }
}

