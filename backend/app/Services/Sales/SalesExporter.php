<?php

namespace App\Services\Sales;

use App\Models\Sale;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class SalesExporter
{
    public const CSV_HEADERS = [
        'sale_id', 'branch', 'sale_date', 'product_name', 'category',
        'quantity', 'unit_price', 'discount_pct', 'payment_method', 'salesperson',
    ];

    private const CHUNK_SIZE = 1000;

    public function __construct(private readonly SalesSummary $summary) {}

    /**
     * Stream a filtered export as CSV (with UTF-8 BOM) into the given resource.
     *
     * @param  resource  $out
     * @param  array<string, mixed>  $filters
     */
    public function streamCsv($out, array $filters): int
    {
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::CSV_HEADERS);

        $rows = 0;
        Sale::query()
            ->applyFilters($filters)
            ->orderBy('id')
            ->lazyById(self::CHUNK_SIZE)
            ->each(function (Sale $sale) use ($out, &$rows) {
                fputcsv($out, $this->rowToArray($sale));
                $rows++;
            });

        return $rows;
    }

    /**
     * Write a two-sheet XLSX ("Sales Data" + "Summary") to the given path.
     *
     * @param  array<string, mixed>  $filters
     */
    public function writeXlsx(string $absolutePath, array $filters): int
    {
        $writer = new XlsxWriter();
        $writer->openToFile($absolutePath);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Sales Data');
        $writer->addRow(Row::fromValues(self::CSV_HEADERS));

        $rows = 0;
        Sale::query()
            ->applyFilters($filters)
            ->orderBy('id')
            ->lazyById(self::CHUNK_SIZE)
            ->each(function (Sale $sale) use ($writer, &$rows) {
                $writer->addRow(Row::fromValues($this->rowToArray($sale)));
                $rows++;
            });

        $summaryData = $this->summary->build($filters);

        $writer->addNewSheetAndMakeItCurrent()->setName('Summary');

        $writer->addRow(Row::fromValues(['Metric', 'Value']));
        $writer->addRow(Row::fromValues(['Total Revenue',       $summaryData['total_revenue']]));
        $writer->addRow(Row::fromValues(['Total Quantity',      $summaryData['total_quantity']]));
        $writer->addRow(Row::fromValues(['Total Rows',          $summaryData['total_rows']]));
        $writer->addRow(Row::fromValues(['Average Order Value', $summaryData['average_order_value']]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Top 5 Products by Revenue']));
        $writer->addRow(Row::fromValues(['Product', 'Revenue', 'Quantity']));
        foreach ($summaryData['top_products'] as $p) {
            $writer->addRow(Row::fromValues([$p['product_name'], $p['revenue'], $p['quantity']]));
        }
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Per-Branch Breakdown']));
        $writer->addRow(Row::fromValues(['Branch', 'Rows', 'Revenue', 'Quantity']));
        foreach ($summaryData['branch_breakdown'] as $b) {
            $writer->addRow(Row::fromValues([$b['branch'], $b['rows'], $b['revenue'], $b['quantity']]));
        }

        $writer->close();

        return $rows;
    }

    private function rowToArray(Sale $sale): array
    {
        return [
            $sale->sale_id,
            $sale->branch,
            $sale->sale_date?->format('Y-m-d'),
            $sale->product_name,
            $sale->category,
            $sale->quantity,
            $sale->unit_price,
            $sale->discount_pct,
            $sale->payment_method,
            $sale->salesperson,
        ];
    }
}
