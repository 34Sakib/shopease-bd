<?php

namespace Tests\Unit\Services;

use App\Services\Sales\NormalizationException;
use App\Services\Sales\SaleNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SaleNormalizerTest extends TestCase
{
    private SaleNormalizer $n;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = new SaleNormalizer();
    }

    // ---------------------------------------------------------------------
    // BRANCH
    // ---------------------------------------------------------------------

    public static function branchValidCases(): array
    {
        return [
            'lowercase'            => ['mirpur', 'Mirpur'],
            'uppercase + trailing' => ['MIRPUR ', 'Mirpur'],
            'title case'           => ['Mirpur', 'Mirpur'],
            'leading space'        => [' Mirpur', 'Mirpur'],
            'both spaces'          => [' Gulshan ', 'Gulshan'],
            'chaotic case'         => ['mOtIjhEeL', 'Motijheel'],
            'chaotic + spaces'     => ['  MoTIJheeL   ', 'Motijheel'],
            'multiple inner ws'    => ["dhanmondi\t\t", 'Dhanmondi'],
            'chattogram upper'    => ['CHATTOGRAM', 'Chattogram'],
            'uttara mixed'         => ['uTTAra', 'Uttara'],
        ];
    }

    #[Test]
    #[DataProvider('branchValidCases')]
    public function branch_normalizes_valid_inputs(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->n->normalizeBranch($raw));
    }

    public static function branchInvalidCases(): array
    {
        return [
            'empty'         => [''],
            'whitespace'    => ['   '],
            'unknown name'  => ['mohammadpur'],
            'typo'          => ['Motijhel'],
            'null'          => [null],
            'array'         => [['x']],
            'random'        => ['xxx'],
        ];
    }

    #[Test]
    #[DataProvider('branchInvalidCases')]
    public function branch_rejects_bad_inputs(mixed $raw): void
    {
        $this->expectException(NormalizationException::class);
        $this->n->normalizeBranch($raw);
    }

    // ---------------------------------------------------------------------
    // DATE
    // ---------------------------------------------------------------------

    public static function dateValidCases(): array
    {
        return [
            'd/m/Y'            => ['25/12/2023', '2023-12-25'],
            'd/m/Y single digit'=> ['5/1/2024',   '2024-01-05'],
            'Y-m-d'            => ['2023-12-25', '2023-12-25'],
            'Y-m-d padded'     => ['2024-01-05', '2024-01-05'],
            'm-d-Y'            => ['12-25-2023', '2023-12-25'],
            'm-d-Y single'     => ['1-5-2024',   '2024-01-05'],
            'leading ws'       => ['  25/12/2023', '2023-12-25'],
            'trailing ws'      => ['2024-02-29  ', '2024-02-29'],
            'ambiguous = m-d-Y'=> ['01-02-2026', '2026-01-02'],
        ];
    }

    #[Test]
    #[DataProvider('dateValidCases')]
    public function date_parses_all_three_formats(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->n->normalizeDate($raw));
    }

    public static function dateInvalidCases(): array
    {
        return [
            'empty'               => [''],
            'null'                => [null],
            'text'                => ['yesterday'],
            'bad month d/m/Y'     => ['25/13/2023'],
            'bad day m-d-Y'       => ['02-31-2024'],
            'non-leap year'       => ['2023-02-29'],
            'wrong separator'     => ['2023.12.25'],
            'two parts'           => ['12/2023'],
            'letters in date'     => ['2023-ab-25'],
            'year out of range'   => ['1850-01-01'],
        ];
    }

    #[Test]
    #[DataProvider('dateInvalidCases')]
    public function date_rejects_bad_inputs(mixed $raw): void
    {
        $this->expectException(NormalizationException::class);
        $this->n->normalizeDate($raw);
    }

    // ---------------------------------------------------------------------
    // PRICE
    // ---------------------------------------------------------------------

    public static function priceValidCases(): array
    {
        return [
            'plain float'            => ['1200.50', 1200.50],
            'plain int string'       => ['1200',    1200.0],
            'int raw'                => [1200,      1200.0],
            'float raw'              => [1200.5,    1200.5],
            'with taka sign'         => ['৳1200.50', 1200.50],
            'with taka + comma'      => ['৳1,200.50', 1200.50],
            'padded + taka + comma'  => ['  ৳1,200.50 ', 1200.50],
            'big number with comma'  => ['৳12,345,678.90', 12345678.90],
            'whole + trailing ws'    => [' 1423 ', 1423.0],
            'zero'                   => ['0', 0.0],
        ];
    }

    #[Test]
    #[DataProvider('priceValidCases')]
    public function price_normalizes_valid_inputs(mixed $raw, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, $this->n->normalizePrice($raw), 0.001);
    }

    public static function priceInvalidCases(): array
    {
        return [
            'empty string'    => [''],
            'only symbol'     => ['৳'],
            'letters'         => ['abc'],
            'negative'        => ['-5'],
            'negative number' => [-5],
            'null'            => [null],
            'array'           => [[1]],
        ];
    }

    #[Test]
    #[DataProvider('priceInvalidCases')]
    public function price_rejects_bad_inputs(mixed $raw): void
    {
        $this->expectException(NormalizationException::class);
        $this->n->normalizePrice($raw);
    }

    // ---------------------------------------------------------------------
    // DISCOUNT — the critical PDF trap
    // ---------------------------------------------------------------------

    public static function discountValidCases(): array
    {
        return [
            'int percent'             => [10,     0.10],
            'int percent string'      => ['10',   0.10],
            'percent sign string'     => ['10%',  0.10],
            'percent sign w/ space'   => [' 10 %', 0.10],
            'already decimal'         => [0.10,   0.10],
            'already decimal str'     => ['0.10', 0.10],
            'zero int'                => [0,      0.0],
            'zero decimal'            => [0.0,    0.0],
            'edge case 1.0 = 100%'    => [1.0,    1.0],
            'full 100%'               => ['100%', 1.0],
            'fractional percent'      => ['12.5%',0.125],
            'empty string = no disc'  => ['',     0.0],
            'null = no disc'          => [null,   0.0],
            'comma in int percent'    => ['1,0',  0.10],  // "1,0" -> "10" -> 0.10
        ];
    }

    #[Test]
    #[DataProvider('discountValidCases')]
    public function discount_handles_all_three_formats(mixed $raw, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, $this->n->normalizeDiscount($raw), 0.0001);
    }

    public static function discountInvalidCases(): array
    {
        return [
            'negative'       => [-5],
            'over 100'       => [150],
            'over 100 str'   => ['150%'],
            'letters'        => ['abc%'],
            'array'          => [[10]],
        ];
    }

    #[Test]
    #[DataProvider('discountInvalidCases')]
    public function discount_rejects_bad_inputs(mixed $raw): void
    {
        $this->expectException(NormalizationException::class);
        $this->n->normalizeDiscount($raw);
    }

    // ---------------------------------------------------------------------
    // CATEGORY
    // ---------------------------------------------------------------------

    public static function categoryCases(): array
    {
        return [
            'valid'          => ['Groceries',   'Groceries'],
            'lowercase'      => ['groceries',   'Groceries'],
            'uppercase'      => ['ELECTRONICS', 'Electronics'],
            'mixed case'     => ['CLOthing',    'Clothing'],
            'trimmed'        => ['  Groceries ', 'Groceries'],
            'empty -> null'  => ['',            null],
            'null -> null'   => [null,          null],
            'NA -> null'     => ['N/A',         null],
            'na lower'       => ['n/a',         null],
            'dash'           => ['-',           null],
            'double dash'    => ['--',          null],
            'NULL'           => ['NULL',        null],
            'null lower'     => ['null',        null],
            'NONE'           => ['None',        null],
            'whitespace only'=> ['   ',         null],
        ];
    }

    #[Test]
    #[DataProvider('categoryCases')]
    public function category_dirty_tokens_become_null(mixed $raw, ?string $expected): void
    {
        $this->assertSame($expected, $this->n->normalizeCategory($raw));
    }

    // ---------------------------------------------------------------------
    // SALESPERSON
    // ---------------------------------------------------------------------

    public static function salespersonCases(): array
    {
        return [
            'normal'              => ['Rahim Uddin',   'Rahim Uddin'],
            'trimmed'             => ['  Karim ',      'Karim'],
            'empty -> Unknown'    => ['',              'Unknown'],
            'whitespace -> Unknown'=> ['   ',          'Unknown'],
            'NULL -> Unknown'     => ['NULL',          'Unknown'],
            'null -> Unknown'     => ['null',          'Unknown'],
            'NA -> Unknown'       => ['N/A',           'Unknown'],
            'actual null -> Unknown'=> [null,          'Unknown'],
            'dash -> Unknown'     => ['-',             'Unknown'],
            'multi-word'          => ['Arif Chowdhury', 'Arif Chowdhury'],
            'collapse ws'         => ['Arif  Chowdhury', 'Arif Chowdhury'],
        ];
    }

    #[Test]
    #[DataProvider('salespersonCases')]
    public function salesperson_missing_defaults_to_unknown(mixed $raw, string $expected): void
    {
        $this->assertSame($expected, $this->n->normalizeSalesperson($raw));
    }

    // ---------------------------------------------------------------------
    // PAYMENT METHOD
    // ---------------------------------------------------------------------

    public static function paymentValidCases(): array
    {
        return [
            'cash lower'  => ['cash',  'cash'],
            'Cash'        => ['Cash',  'cash'],
            'CASH'        => ['CASH',  'cash'],
            'bkash lower' => ['bkash', 'bkash'],
            'bKash'       => ['bKash', 'bkash'],
            'Bkash'       => ['Bkash', 'bkash'],
            'BKASH'       => ['BKASH', 'bkash'],
            'nagad lower' => ['nagad', 'nagad'],
            'Nagad'       => ['Nagad', 'nagad'],
            'NAGAD'       => ['NAGAD', 'nagad'],
            'card lower'  => ['card',  'card'],
            'Card'        => ['Card',  'card'],
            'CARD'        => ['CARD',  'card'],
            'with spaces' => [' CASH ', 'cash'],
        ];
    }

    #[Test]
    #[DataProvider('paymentValidCases')]
    public function payment_method_canonicalizes(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->n->normalizePaymentMethod($raw));
    }

    #[Test]
    public function payment_method_rejects_unknown(): void
    {
        $this->expectException(NormalizationException::class);
        $this->n->normalizePaymentMethod('cheque');
    }

    #[Test]
    public function payment_method_rejects_empty(): void
    {
        $this->expectException(NormalizationException::class);
        $this->n->normalizePaymentMethod('');
    }

    // ---------------------------------------------------------------------
    // QUANTITY / SALE_ID / PRODUCT_NAME
    // ---------------------------------------------------------------------

    #[Test]
    public function quantity_parses_positive_integers(): void
    {
        $this->assertSame(5, $this->n->normalizeQuantity('5'));
        $this->assertSame(12, $this->n->normalizeQuantity(12));
        $this->assertSame(7, $this->n->normalizeQuantity(7.4));
    }

    #[Test]
    public function quantity_rejects_zero_negative_and_huge(): void
    {
        foreach ([0, -1, 'abc', 200000, null] as $bad) {
            try {
                $this->n->normalizeQuantity($bad);
                $this->fail('Expected exception for '.var_export($bad, true));
            } catch (NormalizationException $_) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function sale_id_trims_and_validates(): void
    {
        $this->assertSame('42', $this->n->normalizeSaleId('  42 '));
        $this->assertSame('ABC-001', $this->n->normalizeSaleId('ABC-001'));

        $this->expectException(NormalizationException::class);
        $this->n->normalizeSaleId('');
    }

    #[Test]
    public function product_name_collapses_whitespace(): void
    {
        $this->assertSame('Rice 5kg', $this->n->normalizeProductName('  Rice  5kg  '));
        $this->assertSame('চাল', $this->n->normalizeProductName('  চাল  '));
    }

    // ---------------------------------------------------------------------
    // clean() — end-to-end orchestration
    // ---------------------------------------------------------------------

    #[Test]
    public function clean_happy_path_returns_success_with_canonical_data(): void
    {
        $row = [
            'sale_id'        => '42',
            'branch'         => '  MoHaMmAdPur  ', // wrong branch to force error? No, we need valid
            'sale_date'      => '25/12/2023',
            'product_name'   => '  Rice 5kg ',
            'category'       => 'groceries',
            'quantity'       => '8',
            'unit_price'     => '৳1,200.50',
            'discount_pct'   => '10%',
            'payment_method' => 'bKash',
            'salesperson'    => '',
        ];

        // Swap in a real branch
        $row['branch'] = ' MirPUr ';

        $result = $this->n->clean($row);

        $this->assertTrue($result->ok, $result->errorSummary());
        $this->assertSame([
            'sale_id'        => '42',
            'branch'         => 'Mirpur',
            'sale_date'      => '2023-12-25',
            'product_name'   => 'Rice 5kg',
            'category'       => 'Groceries',
            'quantity'       => 8,
            'unit_price'     => 1200.50,
            'discount_pct'   => 0.10,
            'payment_method' => 'bkash',
            'salesperson'    => 'Unknown',
        ], $result->data);
    }

    #[Test]
    public function clean_accumulates_all_field_errors(): void
    {
        $row = [
            'sale_id'        => '',          // required
            'branch'         => 'mohammadpur', // unknown
            'sale_date'      => 'yesterday', // unparseable
            'product_name'   => '',
            'category'       => 'n/a',       // OK -> null
            'quantity'       => -5,
            'unit_price'     => 'xyz',
            'discount_pct'   => -3,
            'payment_method' => 'cheque',
            'salesperson'    => null,
        ];

        $result = $this->n->clean($row);

        $this->assertFalse($result->ok);
        $fields = array_column($result->errors, 'field');
        foreach (['sale_id','branch','sale_date','product_name','quantity','unit_price','discount_pct','payment_method'] as $f) {
            $this->assertContains($f, $fields, "expected an error for {$f}");
        }
    }

    #[Test]
    public function clean_handles_row_missing_salesperson_column_entirely(): void
    {
        $row = [
            'sale_id'        => '100',
            'branch'         => 'Uttara',
            'sale_date'      => '2024-03-16',
            'product_name'   => 'Saree',
            'category'       => 'Clothing',
            'quantity'       => 2,
            'unit_price'     => '1500',
            'discount_pct'   => '5%',
            'payment_method' => 'card',
            // 'salesperson' deliberately absent
        ];

        $result = $this->n->clean($row);

        $this->assertTrue($result->ok, $result->errorSummary());
        $this->assertSame('Unknown', $result->data['salesperson']);
    }

    #[Test]
    public function clean_treats_dirty_category_tokens_as_null(): void
    {
        foreach (['', 'N/A', 'n/a', '-', 'NULL', 'null', null] as $dirty) {
            $row = [
                'sale_id'        => 'X1',
                'branch'         => 'gulshan',
                'sale_date'      => '2024-01-01',
                'product_name'   => 'Tea 500g',
                'category'       => $dirty,
                'quantity'       => 1,
                'unit_price'     => '100',
                'discount_pct'   => 0,
                'payment_method' => 'cash',
                'salesperson'    => 'Karim',
            ];
            $result = $this->n->clean($row);
            $this->assertTrue($result->ok, $result->errorSummary());
            $this->assertNull($result->data['category'], 'dirty='.var_export($dirty, true));
        }
    }
}
