<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesIndexRequest;
use App\Models\Sale;
use App\Services\Sales\SalesSummary;
use Illuminate\Http\JsonResponse;

class SalesController extends Controller
{
    public function index(SalesIndexRequest $request): JsonResponse
    {
        $sales = Sale::query()
            ->applyFilters($request->filters())
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate(100);

        return response()->json($sales);
    }

    public function summary(SalesIndexRequest $request, SalesSummary $summary): JsonResponse
    {
        return response()->json($summary->build($request->filters()));
    }
}
