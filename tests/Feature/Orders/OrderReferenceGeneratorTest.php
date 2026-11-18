<?php

namespace Tests\Feature\Orders;

use App\Services\Orders\OrderReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderReferenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_references_count_up_per_business_date(): void
    {
        $generator = new OrderReferenceGenerator;

        $this->assertSame('ORD-20261118-0001', $generator->next('2026-11-18'));
        $this->assertSame('ORD-20261118-0002', $generator->next('2026-11-18'));
        $this->assertSame('ORD-20261119-0001', $generator->next('2026-11-19'));
        $this->assertSame('ORD-20261118-0003', $generator->next('2026-11-18'));
        $row = DB::table('order_reference_counters')->where('business_date', '2026-11-18')->first();
        $this->assertNotNull($row->created_at);
        $this->assertNotNull($row->updated_at);
    }

    public function test_numbers_past_9999_print_five_digits(): void
    {
        DB::table('order_reference_counters')->insert(['business_date' => '2026-11-18', 'last_number' => 9999]);

        $this->assertSame('ORD-20261118-10000', (new OrderReferenceGenerator)->next('2026-11-18'));
    }
}
