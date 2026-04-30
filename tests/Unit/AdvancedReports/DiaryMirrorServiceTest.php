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
}
