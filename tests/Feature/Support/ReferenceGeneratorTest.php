<?php

namespace Tests\Feature\Support;

use App\Services\Support\ReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_references_count_up_per_prefix_and_business_date(): void
    {
        $generator = new ReferenceGenerator;

        $this->assertSame('ORD-20261118-0001', $generator->next('ORD', '2026-11-18'));
        $this->assertSame('ORD-20261118-0002', $generator->next('ORD', '2026-11-18'));
        $this->assertSame('RCP-20261118-0001', $generator->next('RCP', '2026-11-18'));
        $this->assertSame('ORD-20261119-0001', $generator->next('ORD', '2026-11-19'));
        $this->assertSame('ORD-20261118-0003', $generator->next('ORD', '2026-11-18'));
        $this->assertSame('RCP-20261118-0002', $generator->next('RCP', '2026-11-18'));

        $row = DB::table('reference_counters')->where(['prefix' => 'ORD', 'business_date' => '2026-11-18'])->first();
        $this->assertSame(3, $row->last_number);
        $this->assertNotNull($row->created_at);
        $this->assertNotNull($row->updated_at);
    }

    public function test_numbers_past_9999_print_five_digits(): void
    {
        DB::table('reference_counters')->insert(['prefix' => 'ORD', 'business_date' => '2026-11-18', 'last_number' => 9999]);

        $this->assertSame('ORD-20261118-10000', (new ReferenceGenerator)->next('ORD', '2026-11-18'));
    }
}
