<?php

namespace App\Services\Sales;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class SalesSummary
{
    /**
     * Revenue = SUM(quantity * unit_price * (1 - discount_pct)).
     * Uses already-normalized columns so discount_pct is guaranteed to be 0..1.
     */
    private const REVENUE_EXPR = 'quantity * unit_price * (1 - discount_pct)';

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   total_revenue: float,
     *   total_quantity: int,
     *   total_rows: int,
     *   average_order_value: float,
     *   top_products: list<array{product_name:string, revenue:float, quantity:int}>,
     *   branch_breakdown: list<array{branch:string, rows:int, revenue:float, quantity:int}>
     * }
     */
    public function build(array $filters): array
    {
        $base = fn () => Sale::query()->applyFilters($filters);

        $totals = $base()
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity')
            ->selectRaw('COALESCE(SUM('.self::REVENUE_EXPR.'), 0) as total_revenue')
            ->first();

        $totalRows = (int) ($totals->total_rows ?? 0);
        $totalRevenue = (float) ($totals->total_revenue ?? 0.0);
        $averageOrderValue = $totalRows > 0 ? round($totalRevenue / $totalRows, 2) : 0.0;

        $topProducts = $base()
            ->select('product_name')
            ->selectRaw('SUM('.self::REVENUE_EXPR.') as revenue')
            ->selectRaw('SUM(quantity) as quantity')
            ->groupBy('product_name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'product_name' => $r->product_name,
                'revenue'      => round((float) $r->revenue, 2),
                'quantity'     => (int) $r->quantity,
            ])
            ->all();

        $branchBreakdown = $base()
            ->select('branch')
            ->selectRaw('COUNT(*) as row_count')
            ->selectRaw('SUM('.self::REVENUE_EXPR.') as revenue')
            ->selectRaw('SUM(quantity) as quantity')
            ->groupBy('branch')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'branch'   => $r->branch,
                'rows'     => (int) $r->row_count,
                'revenue'  => round((float) $r->revenue, 2),
                'quantity' => (int) $r->quantity,
            ])
            ->all();

        return [
            'total_revenue'       => round($totalRevenue, 2),
            'total_quantity'      => (int) ($totals->total_quantity ?? 0),
            'total_rows'          => $totalRows,
            'average_order_value' => $averageOrderValue,
            'top_products'        => $topProducts,
            'branch_breakdown'    => $branchBreakdown,
        ];
    }
}
