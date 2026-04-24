<?php

namespace App\Services\Sales;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class SalesImporter
{
    private const CHUNK_SIZE = 500;

    private const CANONICAL_HEADERS = [
        'sale_id', 'branch', 'sale_date', 'product_name', 'category',
        'quantity', 'unit_price', 'discount_pct', 'payment_method', 'salesperson',
    ];

    public function __construct(private readonly SaleNormalizer $normalizer) {}

    /**
     * @return array{
     *   total: int, inserted: int, skipped_duplicate: int,
     *   skipped_invalid: int, error_log_path: ?string, import_id: string
     * }
     */
    public function import(string $absolutePath, string $originalExtension): array
    {
        $importId = (string) Str::uuid();
        $errorRelativePath = "imports/{$importId}/errors.csv";
        $errorAbsolutePath = storage_path("app/{$errorRelativePath}");
        @mkdir(dirname($errorAbsolutePath), 0777, true);

        $errorFp = fopen($errorAbsolutePath, 'w');
        fwrite($errorFp, "\xEF\xBB\xBF");
        fputcsv($errorFp, ['row_number', 'reason', 'raw_sale_id', 'raw_row_json']);

        $rows = $this->streamRows($absolutePath, $originalExtension);

        $counters = [
            'total'             => 0,
            'inserted'          => 0,
            'skipped_duplicate' => 0,
            'skipped_invalid'   => 0,
        ];

        $seenSaleIds = [];
        $rowNumber = 1;

        $rows->chunk(self::CHUNK_SIZE)->each(function (LazyCollection $chunk) use (
            &$counters, &$seenSaleIds, &$rowNumber, $errorFp
        ) {
            $pending = [];

            foreach ($chunk as $row) {
                $rowNumber++;
                $counters['total']++;

                $result = $this->normalizer->clean($row);
                if (! $result->ok) {
                    $counters['skipped_invalid']++;
                    fputcsv($errorFp, [
                        $rowNumber,
                        $result->errorSummary(),
                        $row['sale_id'] ?? '',
                        json_encode($row, JSON_UNESCAPED_UNICODE),
                    ]);
                    continue;
                }

                $saleId = $result->data['sale_id'];
                if (isset($seenSaleIds[$saleId])) {
                    $counters['skipped_duplicate']++;
                    fputcsv($errorFp, [
                        $rowNumber,
                        "duplicate sale_id in file: {$saleId}",
                        $saleId,
                        json_encode($row, JSON_UNESCAPED_UNICODE),
                    ]);
                    continue;
                }

                $seenSaleIds[$saleId] = true;
                $pending[] = $result->data + ['created_at' => now()];
            }

            if ($pending !== []) {
                $attempted = count($pending);

                $existing = DB::table('sales')
                    ->whereIn('sale_id', array_column($pending, 'sale_id'))
                    ->pluck('sale_id')
                    ->flip()
                    ->all();

                $freshRows = [];
                foreach ($pending as $row) {
                    if (isset($existing[$row['sale_id']])) {
                        $counters['skipped_duplicate']++;
                        fputcsv($errorFp, [
                            '-',
                            "duplicate sale_id already in DB: {$row['sale_id']}",
                            $row['sale_id'],
                            json_encode($row, JSON_UNESCAPED_UNICODE),
                        ]);
                    } else {
                        $freshRows[] = $row;
                    }
                }

                if ($freshRows !== []) {
                    $inserted = DB::table('sales')->insertOrIgnore($freshRows);
                    $counters['inserted']          += $inserted;
                    $counters['skipped_duplicate'] += (count($freshRows) - $inserted);
                }
            }
        });

        fclose($errorFp);

        $errorCount = $counters['skipped_invalid'] + $counters['skipped_duplicate'];
        if ($errorCount === 0) {
            @unlink($errorAbsolutePath);
            $errorRelativePath = null;
        }

        return [
            'total'             => $counters['total'],
            'inserted'          => $counters['inserted'],
            'skipped_duplicate' => $counters['skipped_duplicate'],
            'skipped_invalid'   => $counters['skipped_invalid'],
            'error_log_path'    => $errorRelativePath,
            'import_id'         => $importId,
        ];
    }

    /**
     * Yield associative rows keyed by canonical column names.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    private function streamRows(string $absolutePath, string $extension): LazyCollection
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->streamCsv($absolutePath);
        }

        if (in_array($extension, ['xlsx', 'xls', 'ods'], true)) {
            return $this->streamSpreadsheet($absolutePath);
        }

        return LazyCollection::make(fn () => yield from []);
    }

    private function streamCsv(string $path): LazyCollection
    {
        return LazyCollection::make(function () use ($path) {
            $fp = fopen($path, 'r');
            if ($fp === false) {
                return;
            }

            $headers = fgetcsv($fp);
            if ($headers === false) {
                fclose($fp);
                return;
            }

            if (isset($headers[0]) && is_string($headers[0]) && str_starts_with($headers[0], "\xEF\xBB\xBF")) {
                $headers[0] = substr($headers[0], 3);
            }
            $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);

            $map = $this->buildHeaderMap($headers);

            while (($line = fgetcsv($fp)) !== false) {
                if ($line === [null] || $line === []) {
                    continue;
                }
                yield $this->toCanonicalRow($line, $map);
            }

            fclose($fp);
        });
    }

    private function streamSpreadsheet(string $path): LazyCollection
    {
        return LazyCollection::make(function () use ($path) {
            $reader = ReaderFactory::createFromFile($path);
            $reader->open($path);

            $headers = null;
            $map = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($c) => $c->getValue(), $row->getCells());

                    if ($headers === null) {
                        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $cells);
                        $map = $this->buildHeaderMap($headers);
                        continue;
                    }

                    yield $this->toCanonicalRow($cells, $map);
                }
                break; // only first sheet
            }

            $reader->close();
        });
    }

    /** @return array<string, int> */
    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        foreach (self::CANONICAL_HEADERS as $canonical) {
            $idx = array_search($canonical, $headers, true);
            if ($idx !== false) {
                $map[$canonical] = $idx;
            }
        }
        return $map;
    }

    /** @return array<string, mixed> */
    private function toCanonicalRow(array $cells, array $map): array
    {
        $out = [];
        foreach ($map as $field => $idx) {
            $out[$field] = $cells[$idx] ?? null;
        }
        return $out;
    }

}
