<?php

namespace Tests\Feature\AdvancedReports;

use iEducar\Packages\AdvancedReports\Models\AdvancedReportsDocument;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunicationsTest extends TestCase
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

    public function test_pdf_preview_does_not_persist_document(): void
    {
        $this->withoutMiddleware();

        AdvancedReportsDocument::query()->delete();

        $this->get('/relatorios-avancados/comunicados/reuniao/pdf?preview=1')->assertOk();

        $this->assertSame(0, AdvancedReportsDocument::query()->count());
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->withoutMiddleware();

        $this->get('/relatorios-avancados/comunicados/ocorrencias')->assertNotFound();
        $this->get('/relatorios-avancados/comunicados/ocorrencias/pdf?preview=1')->assertNotFound();
    }
}
