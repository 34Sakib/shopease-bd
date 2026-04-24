<?php

namespace Tests\Feature\Api;

use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function csvUpload(string $contents, string $name = 'sales.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csvtest_');
        file_put_contents($path, $contents);
        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    #[Test]
    public function it_imports_a_clean_csv_and_reports_counts(): void
    {
        $csv = "\xEF\xBB\xBFsale_id,branch,sale_date,product_name,category,quantity,unit_price,discount_pct,payment_method,salesperson\n"
             . "1, MIRPUR ,25/12/2023,Rice 5kg,Groceries,5,\"৳1,200.50\",10%,bKash,Karim\n"
             . "2,gulshan,2024-03-16,Saree,Clothing,2,1500,0.05,CASH,Rahim\n"
             . "3,motijheel,03-17-2024,Onion 1kg,Groceries,10,50,0,nagad,Lima\n";

        $response = $this->postJson('/api/import', [
            'file' => $this->csvUpload($csv),
        ]);

        $response->assertOk()->assertJsonStructure([
            'status', 'import_id', 'total', 'inserted',
            'skipped_duplicate', 'skipped_invalid', 'error_log_url',
        ]);

        $this->assertSame(3, $response->json('total'));
        $this->assertSame(3, $response->json('inserted'));
        $this->assertSame(0, $response->json('skipped_duplicate'));
        $this->assertSame(0, $response->json('skipped_invalid'));
        $this->assertNull($response->json('error_log_url'));

        $this->assertDatabaseCount('sales', 3);
        $this->assertDatabaseHas('sales', [
            'sale_id' => '1',
            'branch'  => 'Mirpur',
        ]);
    }

    #[Test]
    public function it_skips_invalid_rows_and_produces_error_log(): void
    {
        $csv = "sale_id,branch,sale_date,product_name,category,quantity,unit_price,discount_pct,payment_method,salesperson\n"
             . "1,mirpur,25/12/2023,Rice,Groceries,5,1200,10,cash,Karim\n"
             . "2,unknown_branch,25/12/2023,Rice,Groceries,5,1200,10,cash,Karim\n"
             . "3,gulshan,not-a-date,Rice,Groceries,5,1200,10,cash,Karim\n"
             . "4,uttara,2024-01-01,Saree,Clothing,2,1000,150%,cash,Lima\n";

        $response = $this->postJson('/api/import', [
            'file' => $this->csvUpload($csv),
        ]);

        $response->assertOk();
        $this->assertSame(4, $response->json('total'));
        $this->assertSame(1, $response->json('inserted'));
        $this->assertSame(3, $response->json('skipped_invalid'));
        $this->assertNotNull($response->json('error_log_url'));
    }

    #[Test]
    public function it_detects_duplicate_sale_ids_within_file_and_against_db(): void
    {
        // Pre-seed one row
        Sale::create([
            'sale_id'        => '1',
            'branch'         => 'Mirpur',
            'sale_date'      => '2024-01-01',
            'product_name'   => 'Rice',
            'category'       => 'Groceries',
            'quantity'       => 1,
            'unit_price'     => 100.00,
            'discount_pct'   => 0.0,
            'payment_method' => 'cash',
            'salesperson'    => 'Karim',
        ]);

        $csv = "sale_id,branch,sale_date,product_name,category,quantity,unit_price,discount_pct,payment_method,salesperson\n"
             . "1,mirpur,2024-01-01,Rice,Groceries,1,100,0,cash,Karim\n"    // duplicate vs DB
             . "2,gulshan,2024-01-01,Saree,Clothing,1,100,0,cash,Lima\n"
             . "2,gulshan,2024-01-01,Saree,Clothing,1,100,0,cash,Lima\n"    // duplicate within file
             . "3,uttara,2024-01-01,Tea,Groceries,1,100,0,cash,Rahim\n";

        $response = $this->postJson('/api/import', [
            'file' => $this->csvUpload($csv),
        ]);

        $response->assertOk();
        $this->assertSame(4, $response->json('total'));
        $this->assertSame(2, $response->json('inserted'));
        $this->assertSame(2, $response->json('skipped_duplicate'));
        $this->assertDatabaseCount('sales', 3);
    }

    #[Test]
    public function it_handles_rows_with_missing_salesperson_column_entirely(): void
    {
        // Only 9 fields on some rows
        $csv = "sale_id,branch,sale_date,product_name,category,quantity,unit_price,discount_pct,payment_method,salesperson\n"
             . "1,mirpur,2024-01-01,Rice,Groceries,1,100,0,cash\n"
             . "2,gulshan,2024-01-01,Saree,Clothing,1,100,0,cash,Lima\n";

        $response = $this->postJson('/api/import', [
            'file' => $this->csvUpload($csv),
        ]);

        $response->assertOk();
        $this->assertSame(2, $response->json('inserted'));
        $this->assertDatabaseHas('sales', [
            'sale_id'     => '1',
            'salesperson' => 'Unknown',
        ]);
    }

    #[Test]
    public function it_rejects_non_csv_file(): void
    {
        $png = tempnam(sys_get_temp_dir(), 'png_');
        file_put_contents($png, "fake");
        $upload = new UploadedFile($png, 'sales.png', 'image/png', null, true);

        $response = $this->postJson('/api/import', ['file' => $upload]);
        $response->assertStatus(422);
    }
}
