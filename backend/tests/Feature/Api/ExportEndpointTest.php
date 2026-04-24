<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateExport;
use App\Models\ExportJob;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExportEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function seedSales(int $count): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'sale_id'        => (string) $i,
                'branch'         => 'Mirpur',
                'sale_date'      => '2024-01-01',
                'product_name'   => 'Rice',
                'category'       => 'Groceries',
                'quantity'       => 1,
                'unit_price'     => 100.00,
                'discount_pct'   => 0.0,
                'payment_method' => 'cash',
                'salesperson'    => 'Karim',
                'created_at'     => now(),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Sale::insert($chunk);
        }
    }

    #[Test]
    public function csv_export_streams_synchronously_with_utf8_bom_when_under_limit(): void
    {
        $this->seedSales(10);

        $response = $this->get('/api/export/csv');
        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('content-type'));

        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'CSV must start with UTF-8 BOM');

        $lines = preg_split('/\r\n|\n/', trim($body));
        $this->assertCount(11, $lines);
        $this->assertStringContainsString('sale_id', $lines[0]);
    }

    #[Test]
    public function excel_export_returns_xlsx_file_when_under_limit(): void
    {
        $this->seedSales(5);

        $response = $this->get('/api/export/excel');
        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );
    }

    #[Test]
    public function csv_export_honors_filters(): void
    {
        Sale::create([
            'sale_id'=>'A','branch'=>'Mirpur','sale_date'=>'2024-01-01','product_name'=>'Rice',
            'category'=>'Groceries','quantity'=>1,'unit_price'=>100,'discount_pct'=>0,
            'payment_method'=>'cash','salesperson'=>'Karim',
        ]);
        Sale::create([
            'sale_id'=>'B','branch'=>'Gulshan','sale_date'=>'2024-01-01','product_name'=>'Rice',
            'category'=>'Groceries','quantity'=>1,'unit_price'=>100,'discount_pct'=>0,
            'payment_method'=>'cash','salesperson'=>'Lima',
        ]);

        $response = $this->get('/api/export/csv?branch=Mirpur');
        $body = $response->streamedContent();
        $this->assertStringContainsString('Mirpur', $body);
        $this->assertStringNotContainsString('Gulshan', $body);
    }

    #[Test]
    public function large_export_dispatches_queued_job_and_returns_job_id(): void
    {
        Bus::fake([GenerateExport::class]);

        $this->seedSales(10001);

        $response = $this->getJson('/api/export/csv');
        $response->assertStatus(202)
            ->assertJsonStructure(['status', 'job_id', 'row_count', 'status_url'])
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('row_count', 10001);

        Bus::assertDispatched(GenerateExport::class);

        $this->assertDatabaseHas('export_jobs', [
            'id'     => $response->json('job_id'),
            'status' => ExportJob::STATUS_PENDING,
            'format' => 'csv',
        ]);
    }

    #[Test]
    public function export_status_endpoint_returns_job_state(): void
    {
        $job = ExportJob::create([
            'id'     => (string) \Illuminate\Support\Str::uuid(),
            'status' => ExportJob::STATUS_PENDING,
            'format' => 'csv',
        ]);

        $response = $this->getJson("/api/export/status/{$job->id}");
        $response->assertOk()
            ->assertJsonPath('id', $job->id)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('download_url', null);
    }

    #[Test]
    public function download_is_404_for_incomplete_job(): void
    {
        $job = ExportJob::create([
            'id'     => (string) \Illuminate\Support\Str::uuid(),
            'status' => ExportJob::STATUS_PROCESSING,
            'format' => 'csv',
        ]);

        $this->get("/api/export/download/{$job->id}")->assertStatus(404);
    }
}
