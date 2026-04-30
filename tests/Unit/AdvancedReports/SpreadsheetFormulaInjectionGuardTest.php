<?php

namespace Tests\Unit\AdvancedReports;

use iEducar\Packages\AdvancedReports\Exports\SpreadsheetFormulaInjectionGuard;
use Tests\TestCase;

class SpreadsheetFormulaInjectionGuardTest extends TestCase
{
    public function test_sanitize_scalar_prefixes_potential_formulas(): void
    {
        $this->assertSame("'=1+1", SpreadsheetFormulaInjectionGuard::sanitizeScalar('=1+1'));
        $this->assertSame("'+SUM(A1:A2)", SpreadsheetFormulaInjectionGuard::sanitizeScalar('+SUM(A1:A2)'));
        $this->assertSame("'-10", SpreadsheetFormulaInjectionGuard::sanitizeScalar('-10'));
        $this->assertSame("'@cmd", SpreadsheetFormulaInjectionGuard::sanitizeScalar('@cmd'));
        $this->assertSame("'\t=HYPERLINK(\"http://example\")", SpreadsheetFormulaInjectionGuard::sanitizeScalar("\t=HYPERLINK(\"http://example\")"));
    }

    public function test_sanitize_scalar_keeps_safe_values(): void
    {
        $this->assertSame('Aluno Exemplo', SpreadsheetFormulaInjectionGuard::sanitizeScalar('Aluno Exemplo'));
        $this->assertSame('', SpreadsheetFormulaInjectionGuard::sanitizeScalar(''));
        $this->assertSame(10, SpreadsheetFormulaInjectionGuard::sanitizeScalar(10));
        $this->assertSame(10.5, SpreadsheetFormulaInjectionGuard::sanitizeScalar(10.5));
        $this->assertSame(true, SpreadsheetFormulaInjectionGuard::sanitizeScalar(true));
        $this->assertSame(null, SpreadsheetFormulaInjectionGuard::sanitizeScalar(null));
    }

    public function test_sanitize_row_sanitizes_each_cell(): void
    {
        $row = ['=1+1', 'ok', 123];
        $this->assertSame(["'=1+1", 'ok', 123], SpreadsheetFormulaInjectionGuard::sanitizeRow($row));
    }

    public function test_sanitize_scalar_prefixes_carriage_return_formula(): void
    {
        $this->assertSame("'\r=1+1", SpreadsheetFormulaInjectionGuard::sanitizeScalar("\r=1+1"));
    }

    public function test_sanitize_headings_prefixes_each_heading(): void
    {
        $h = ['Nome', '=SUM(1)', 'Nota'];
        $this->assertSame(['Nome', "'=SUM(1)", 'Nota'], SpreadsheetFormulaInjectionGuard::sanitizeHeadings($h));
    }

    public function test_sanitize_row_leaves_nested_arrays_untouched(): void
    {
        $nested = ['x' => 1];
        $row = ['ok', $nested];
        $out = SpreadsheetFormulaInjectionGuard::sanitizeRow($row);
        $this->assertSame('ok', $out[0]);
        $this->assertSame($nested, $out[1]);
    }

    public function test_sanitize_scalar_long_string_starting_with_equals(): void
    {
        $long = '=' . str_repeat('A', 5000);
        $got = SpreadsheetFormulaInjectionGuard::sanitizeScalar($long);
        $this->assertStringStartsWith("'", $got);
        $this->assertSame(strlen($long) + 1, strlen((string) $got));
    }
}
