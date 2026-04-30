<?php

namespace Tests\Unit\AdvancedReports;

use iEducar\Packages\AdvancedReports\Services\DiaryMirrorService;
use Tests\TestCase;

class DiaryMirrorServiceTest extends TestCase
{
    public function test_build_days_skips_weekends_when_no_calendar_context(): void
    {
        $s = new DiaryMirrorService();
        $days = $s->buildDays('2026-01-05', '2026-01-11', 80, true, null, null, null);

        $this->assertCount(5, $days);
        $this->assertSame('2026-01-05', $days[0]['date']);
        $this->assertSame('2026-01-09', $days[4]['date']);
    }

    public function test_build_days_normalizes_inverted_range(): void
    {
        $s = new DiaryMirrorService();
        $forward = $s->buildDays('2026-01-05', '2026-01-11', 80, true, null, null, null);
        $backward = $s->buildDays('2026-01-11', '2026-01-05', 80, true, null, null, null);

        $this->assertSame($forward, $backward);
    }

    public function test_build_days_respects_max_school_days_cap(): void
    {
        $s = new DiaryMirrorService();
        $days = $s->buildDays('2026-01-05', '2026-01-30', 3, true, null, null, null);

        $this->assertCount(3, $days);
        $this->assertSame('2026-01-05', $days[0]['date']);
        $this->assertSame('2026-01-07', $days[2]['date']);
    }
}
