<?php

use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\SalesController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app'    => config('app.name'),
        'time'   => now()->toIso8601String(),
    ]);
});

Route::post('/import', [ImportController::class, 'store'])->name('imports.store');
Route::get('/imports/{import}/errors', [ImportController::class, 'errors'])->name('imports.errors');

Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
Route::get('/sales/summary', [SalesController::class, 'summary'])->name('sales.summary');

Route::get('/export/csv',   [ExportController::class, 'csv'])->name('exports.csv');
Route::get('/export/excel', [ExportController::class, 'excel'])->name('exports.excel');
Route::get('/export/status/{export}',   [ExportController::class, 'status'])->name('exports.status');
Route::get('/export/download/{export}', [ExportController::class, 'download'])->name('exports.download');
