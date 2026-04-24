<?php

namespace Tests\Feature\Api;

use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function seedSample(): void
    {
        $rows = [
            ['A1', 'Mirpur',    '2024-01-01', 'Rice 5kg',   'Groceries', 10, 100.00, 0.00, 'cash',  'Karim'],
            ['A2', 'Mirpur',    '2024-01-02', 'Saree',      'Clothing',   2, 1000.00, 0.10, 'bkash', 'Rahim'],
            ['A3', 'Gulshan',   '2024-02-15', 'Rice 5kg',   'Groceries', 20, 100.00, 0.00, 'card',  'Lima'],
            ['A4', 'Gulshan',   '2024-03-01', 'TV',         'Electronics', 1, 50000.00, 0.05, 'card', 'Lima'],
            ['A5', 'Dhanmondi', '2024-03-05', 'Rice 5kg',   'Groceries', 15, 100.00, 0.00, 'nagad', 'Karim'],
        ];
        foreach ($rows as $r) {
            Sale::create([
                'sale_id' => $r[0], 'branch' => $r[1], 'sale_date' => $r[2],
                'product_name' => $r[3], 'category' => $r[4], 'quantity' => $r[5],
                'unit_price' => $r[6], 'discount_pct' => $r[7], 'payment_method' => $r[8],
                'salesperson' => $r[9],
            ]);
        }
    }

    #[Test]
    public function sales_index_paginates_with_100_per_page(): void
    {
        $this->seedSample();
        $response = $this->getJson('/api/sales');
        $response->assertOk()
            ->assertJsonPath('per_page', 100)
            ->assertJsonPath('total', 5);
    }

    #[Test]
    public function sales_index_applies_all_filters(): void
    {
        $this->seedSample();

        $response = $this->getJson('/api/sales?'.http_build_query([
            'branch'   => 'Mirpur',
            'from'     => '2024-01-01',
            'to'       => '2024-01-31',
            'category' => 'Groceries',
        ]));

        $response->assertOk()->assertJsonPath('total', 1);
        $this->assertSame('A1', $response->json('data.0.sale_id'));
    }

    #[Test]
    public function sales_index_validates_branch_enum(): void
    {
        $response = $this->getJson('/api/sales?branch=NotARealBranch');
        $response->assertStatus(422);
    }

    #[Test]
    public function summary_returns_correct_aggregates(): void
    {
        $this->seedSample();

        $response = $this->getJson('/api/sales/summary');
        $response->assertOk();

        // Revenue: 10*100 + 2*1000*0.9 + 20*100 + 1*50000*0.95 + 15*100
        //        = 1000 + 1800 + 2000 + 47500 + 1500 = 53800
        $this->assertEqualsWithDelta(53800.00, $response->json('total_revenue'), 0.01);
        $this->assertSame(48, $response->json('total_quantity'));
        $this->assertSame(5, $response->json('total_rows'));
        $this->assertEqualsWithDelta(10760.00, $response->json('average_order_value'), 0.01);

        $topProducts = $response->json('top_products');
        $this->assertCount(3, $topProducts);
        $this->assertSame('TV', $topProducts[0]['product_name']);

        $branches = $response->json('branch_breakdown');
        $this->assertCount(3, $branches);
    }

    #[Test]
    public function summary_honors_filters(): void
    {
        $this->seedSample();

        $response = $this->getJson('/api/sales/summary?branch=Mirpur');
        $response->assertOk();
        // 10*100 + 2*1000*0.9 = 1000 + 1800 = 2800
        $this->assertEqualsWithDelta(2800.00, $response->json('total_revenue'), 0.01);
        $this->assertSame(2, $response->json('total_rows'));
    }
}
