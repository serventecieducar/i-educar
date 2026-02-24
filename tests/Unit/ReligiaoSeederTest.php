<?php

namespace Tests\Unit;

use App\Models\Religion;
use Database\Seeders\ReligiaoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReligiaoSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creates_religions(): void
    {
        $seeder = new ReligiaoSeeder;
        $seeder->run();

        $this->assertGreaterThanOrEqual(9, Religion::count());
        $this->assertDatabaseHas('pmieducar.religions', ['name' => 'Católica Apostólica Romana']);
        $this->assertDatabaseHas('pmieducar.religions', ['name' => 'Evangélica']);
    }

    public function test_is_idempotent(): void
    {
        $seeder = new ReligiaoSeeder;
        $seeder->run();
        $countAfterFirst = Religion::count();

        $seeder->run();
        $countAfterSecond = Religion::count();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }
}
