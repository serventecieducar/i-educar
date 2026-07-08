<?php

use Carbon\Exceptions\InvalidFormatException;
use iEducar\Packages\AdvancedReports\Services\AuditUsersReportService;

it('normaliza o período e inverte quando necessário', function () {
    [$s1, $e1] = AuditUsersReportService::normalizeDates('2026-04-01', '2026-04-02');
    expect($s1->format('Y-m-d H:i:s'))->toBe('2026-04-01 00:00:00');
    expect($e1->format('Y-m-d H:i:s'))->toBe('2026-04-02 23:59:59');

    // Inverte datas fora de ordem
    [$s2, $e2] = AuditUsersReportService::normalizeDates('2026-04-10', '2026-04-03');
    expect($s2->format('Y-m-d'))->toBe('2026-04-03');
    expect($e2->format('Y-m-d'))->toBe('2026-04-10');
});

it('resolve operação de auditoria conforme before/after', function () {
    expect(AuditUsersReportService::resolveAuditOperation(null, ['x' => 1]))->toBe('INSERT');
    expect(AuditUsersReportService::resolveAuditOperation(['x' => 1], null))->toBe('DELETE');
    expect(AuditUsersReportService::resolveAuditOperation(['x' => 1], ['x' => 2]))->toBe('UPDATE');
});

it('mantém o mesmo dia com início e fim iguais (fim ao último segundo do dia)', function () {
    [$s, $e] = AuditUsersReportService::normalizeDates('2026-04-15', '2026-04-15');
    expect($s->format('Y-m-d H:i:s'))->toBe('2026-04-15 00:00:00');
    expect($e->format('Y-m-d H:i:s'))->toBe('2026-04-15 23:59:59');
});

it('lança ao receber intervalo com data inválida', function () {
    expect(fn () => AuditUsersReportService::normalizeDates('não-é-uma-data', '2026-01-01'))
        ->toThrow(InvalidFormatException::class);
});

it('classifica UPDATE quando before e after existem mesmo vazios', function () {
    expect(AuditUsersReportService::resolveAuditOperation([], []))->toBe('UPDATE');
});
