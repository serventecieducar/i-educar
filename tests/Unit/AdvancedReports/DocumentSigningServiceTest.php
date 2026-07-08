<?php

namespace Tests\Unit\AdvancedReports;

use iEducar\Packages\AdvancedReports\Services\DocumentSigningService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentSigningServiceTest extends TestCase
{
    public function test_mac_verification_succeeds_for_same_payload(): void
    {
        config(['app.key' => 'base64:' . base64_encode('test-key-32-bytes-aaaaaaaaaaaaaaaa')]);

        $svc = app(DocumentSigningService::class);

        $code = 'ABCDEF0123456789';
        $type = 'certificate';
        $issuedAtIso = '2026-04-28T12:00:00.000000Z';
        $payload = [
            'year' => '2026',
            'course' => 'Ensino Fundamental',
            'issuer_name' => 'Fulano',
        ];

        $mac = $svc->mac($code, $type, $issuedAtIso, $payload);

        $this->assertTrue($svc->verify($mac, $code, $type, $issuedAtIso, $payload));
    }

    public function test_mac_verification_fails_when_payload_changes(): void
    {
        config(['app.key' => 'base64:' . base64_encode('test-key-32-bytes-aaaaaaaaaaaaaaaa')]);

        $svc = app(DocumentSigningService::class);

        $code = 'ABCDEF0123456789';
        $type = 'certificate';
        $issuedAtIso = '2026-04-28T12:00:00.000000Z';
        $payload = ['year' => '2026'];

        $mac = $svc->mac($code, $type, $issuedAtIso, $payload);

        $this->assertFalse($svc->verify($mac, $code, $type, $issuedAtIso, ['year' => '2025']));
    }

    public function test_mac_is_invariant_to_payload_key_order(): void
    {
        config(['app.key' => 'base64:' . base64_encode('test-key-32-bytes-aaaaaaaaaaaaaaaa')]);

        $svc = app(DocumentSigningService::class);

        $code = 'ABCDEF0123456789';
        $type = 'certificate';
        $issuedAtIso = '2026-04-28T12:00:00Z';
        $ordered = ['year' => '2026', 'course' => 'EF', 'issuer_name' => 'Fulano'];
        $shuffled = ['issuer_name' => 'Fulano', 'year' => '2026', 'course' => 'EF'];

        $this->assertSame(
            $svc->mac($code, $type, $issuedAtIso, $ordered),
            $svc->mac($code, $type, $issuedAtIso, $shuffled)
        );
    }

    public function test_issued_at_for_mac_matches_second_precision_after_round_trip(): void
    {
        $instant = Carbon::parse('2026-04-28T15:30:45.987654Z');
        $this->assertSame('2026-04-28T15:30:45Z', DocumentSigningService::issuedAtForMac($instant));
    }

    public function test_verify_stored_document_accepts_round_trip_payload_key_order(): void
    {
        config(['app.key' => 'base64:' . base64_encode('test-key-32-bytes-aaaaaaaaaaaaaaaa')]);

        $svc = app(DocumentSigningService::class);
        $issuedAt = Carbon::parse('2026-05-01T10:00:00.000000Z');
        $code = 'ABCD1234EFGH5678';
        $type = 'historico';
        $payloadEmit = [
            'aluno_id' => 1,
            'book' => '1',
            'validation_url' => 'https://exemplo.test/validar/ABCD',
            'template' => 'classic',
        ];
        $issuedIso = DocumentSigningService::issuedAtForMac($issuedAt);
        $mac = $svc->mac($code, $type, $issuedIso, $payloadEmit);

        $payloadAsFromJsonb = [
            'aluno_id' => 1,
            'book' => '1',
            'template' => 'classic',
            'validation_url' => 'https://exemplo.test/validar/ABCD',
        ];

        $this->assertTrue($svc->verifyStoredDocument($mac, $code, $type, $issuedAt, $payloadAsFromJsonb));
    }
}
