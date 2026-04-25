<?php

namespace App\Services\Sales;

use DateTimeImmutable;

class SaleNormalizer
{
    public const ALLOWED_BRANCHES = [
        'Mirpur',
        'Gulshan',
        'Dhanmondi',
        'Uttara',
        'Motijheel',
        'Chattogram',
    ];

    public const ALLOWED_PAYMENT_METHODS = ['cash', 'bkash', 'nagad', 'card'];

    private const DIRTY_NULL_TOKENS = ['', 'n/a', 'na', '-', '--', 'null', 'none'];

    public function normalizeBranch(mixed $raw): string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            throw new NormalizationException('branch', $raw, 'branch is required');
        }

        $value = trim((string) $raw);
        $value = preg_replace('/\s+/u', ' ', $value);

        if ($value === '') {
            throw new NormalizationException('branch', $raw, 'branch is required');
        }

        $normalized = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        if (! in_array($normalized, self::ALLOWED_BRANCHES, true)) {
            throw new NormalizationException(
                'branch',
                $raw,
                "unknown branch '{$normalized}' (allowed: ".implode(', ', self::ALLOWED_BRANCHES).')',
            );
        }

        return $normalized;
    }

    public function normalizeDate(mixed $raw): string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            throw new NormalizationException('sale_date', $raw, 'sale_date is required');
        }

        $value = trim((string) $raw);

        if ($value === '') {
            throw new NormalizationException('sale_date', $raw, 'sale_date is required');
        }

        if (str_contains($value, '/')) {
            $parts = explode('/', $value);
            if (count($parts) !== 3) {
                throw new NormalizationException('sale_date', $raw, 'unrecognised date format');
            }
            [$d, $m, $y] = $parts;
        } elseif (str_contains($value, '-')) {
            $parts = explode('-', $value);
            if (count($parts) !== 3) {
                throw new NormalizationException('sale_date', $raw, 'unrecognised date format');
            }
            if (strlen($parts[0]) === 4) {
                [$y, $m, $d] = $parts;
            } else {
                [$m, $d, $y] = $parts;
            }
        } else {
            throw new NormalizationException('sale_date', $raw, 'unrecognised date format');
        }

        if (! ctype_digit($d) || ! ctype_digit($m) || ! ctype_digit($y)) {
            throw new NormalizationException('sale_date', $raw, 'non-numeric date component');
        }

        $day   = (int) $d;
        $month = (int) $m;
        $year  = (int) $y;

        if (! checkdate($month, $day, $year) || $year < 1970 || $year > 2100) {
            throw new NormalizationException('sale_date', $raw, 'invalid calendar date');
        }

        return (new DateTimeImmutable())
            ->setDate($year, $month, $day)
            ->setTime(0, 0, 0)
            ->format('Y-m-d');
    }

    public function normalizePrice(mixed $raw): float
    {
        if (is_int($raw) || is_float($raw)) {
            $number = (float) $raw;
        } else {
            if (! is_string($raw)) {
                throw new NormalizationException('unit_price', $raw, 'unit_price is required');
            }

            $cleaned = str_replace(["\xE0\xA7\xB3", '৳', ',', ' ', "\t"], '', $raw);
            $cleaned = trim($cleaned);

            if ($cleaned === '' || ! is_numeric($cleaned)) {
                throw new NormalizationException('unit_price', $raw, 'unit_price is not numeric');
            }

            $number = (float) $cleaned;
        }

        if ($number < 0) {
            throw new NormalizationException('unit_price', $raw, 'unit_price cannot be negative');
        }

        return round($number, 2);
    }

    /**
     * Normalize discount to a decimal in [0, 1].
     *
     *   - "10%"  → 0.10 (explicit percent sign ⇒ divide by 100)
     *   - 10     → 0.10 (raw number > 1 ⇒ divide by 100)
     *   - 0.10   → 0.10 (raw number ≤ 1 ⇒ use as-is)
     *   - ""/null → 0.0 (no discount)
     */
    public function normalizeDiscount(mixed $raw): float
    {
        if ($raw === null) {
            return 0.0;
        }

        $hasPercent = false;

        if (is_string($raw)) {
            $value = trim($raw);
            if ($value === '') {
                return 0.0;
            }
            if (str_ends_with($value, '%')) {
                $hasPercent = true;
                $value = trim(rtrim($value, '%'));
            }
            $value = str_replace([' ', ','], '', $value);
            if (! is_numeric($value)) {
                throw new NormalizationException('discount_pct', $raw, 'discount is not numeric');
            }
            $number = (float) $value;
        } elseif (is_int($raw) || is_float($raw)) {
            $number = (float) $raw;
        } else {
            throw new NormalizationException('discount_pct', $raw, 'discount must be string or number');
        }

        if ($hasPercent || $number > 1) {
            $number = $number / 100.0;
        }

        if ($number < 0 || $number > 1) {
            throw new NormalizationException(
                'discount_pct',
                $raw,
                'discount out of range after normalization (expected 0–1)',
            );
        }

        return round($number, 4);
    }

    public function normalizeCategory(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        if (in_array(strtolower($value), self::DIRTY_NULL_TOKENS, true)) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', $value);

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    public function normalizeSalesperson(mixed $raw): string
    {
        if ($raw === null) {
            return 'Unknown';
        }

        if (! is_string($raw) && ! is_numeric($raw)) {
            return 'Unknown';
        }

        $value = trim((string) $raw);

        if (in_array(strtolower($value), self::DIRTY_NULL_TOKENS, true)) {
            return 'Unknown';
        }

        return preg_replace('/\s+/u', ' ', $value);
    }

    public function normalizePaymentMethod(mixed $raw): string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            throw new NormalizationException('payment_method', $raw, 'payment_method is required');
        }

        $value = strtolower(trim((string) $raw));

        if ($value === '') {
            throw new NormalizationException('payment_method', $raw, 'payment_method is required');
        }

        if (! in_array($value, self::ALLOWED_PAYMENT_METHODS, true)) {
            throw new NormalizationException(
                'payment_method',
                $raw,
                "unknown payment method '{$value}' (allowed: ".implode(', ', self::ALLOWED_PAYMENT_METHODS).')',
            );
        }

        return $value;
    }

    public function normalizeQuantity(mixed $raw): int
    {
        if (! is_numeric($raw) && ! (is_string($raw) && ctype_digit(trim($raw)))) {
            throw new NormalizationException('quantity', $raw, 'quantity is not numeric');
        }

        $number = (int) round((float) $raw);

        if ($number <= 0) {
            throw new NormalizationException('quantity', $raw, 'quantity must be positive');
        }

        if ($number > 100000) {
            throw new NormalizationException('quantity', $raw, 'quantity unreasonably large');
        }

        return $number;
    }

    public function normalizeSaleId(mixed $raw): string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            throw new NormalizationException('sale_id', $raw, 'sale_id is required');
        }

        $value = trim((string) $raw);

        if ($value === '') {
            throw new NormalizationException('sale_id', $raw, 'sale_id is required');
        }

        if (mb_strlen($value) > 64) {
            throw new NormalizationException('sale_id', $raw, 'sale_id exceeds 64 characters');
        }

        return $value;
    }

    public function normalizeProductName(mixed $raw): string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            throw new NormalizationException('product_name', $raw, 'product_name is required');
        }

        $value = trim((string) $raw);
        $value = preg_replace('/\s+/u', ' ', $value);

        if ($value === '') {
            throw new NormalizationException('product_name', $raw, 'product_name is required');
        }

        if (mb_strlen($value) > 255) {
            throw new NormalizationException('product_name', $raw, 'product_name exceeds 255 characters');
        }

        return $value;
    }

    /**
     * Clean a raw CSV row into a DB-ready associative array.
     * Collects every field-level problem into a single NormalizationResult
     * so callers can report them all at once instead of fail-fast.
     *
     * @param  array<string, mixed>  $row
     */
    public function clean(array $row): NormalizationResult
    {
        $errors = [];
        $clean = [];

        $fields = [
            'sale_id'        => fn ($v) => $this->normalizeSaleId($v),
            'branch'         => fn ($v) => $this->normalizeBranch($v),
            'sale_date'      => fn ($v) => $this->normalizeDate($v),
            'product_name'   => fn ($v) => $this->normalizeProductName($v),
            'category'       => fn ($v) => $this->normalizeCategory($v),
            'quantity'       => fn ($v) => $this->normalizeQuantity($v),
            'unit_price'     => fn ($v) => $this->normalizePrice($v),
            'discount_pct'   => fn ($v) => $this->normalizeDiscount($v),
            'payment_method' => fn ($v) => $this->normalizePaymentMethod($v),
            'salesperson'    => fn ($v) => $this->normalizeSalesperson($v),
        ];

        foreach ($fields as $field => $fn) {
            $raw = $row[$field] ?? null;
            try {
                $clean[$field] = $fn($raw);
            } catch (NormalizationException $e) {
                $errors[] = [
                    'field'  => $e->field,
                    'raw'    => $e->rawValue,
                    'reason' => explode('] ', $e->getMessage(), 2)[1] ?? $e->getMessage(),
                ];
            }
        }

        return $errors === []
            ? NormalizationResult::success($clean)
            : NormalizationResult::failure($errors);
    }
}
