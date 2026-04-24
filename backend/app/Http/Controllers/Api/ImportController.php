<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportRequest;
use App\Services\Sales\SalesImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function store(ImportRequest $request, SalesImporter $importer): JsonResponse
    {
        $file = $request->file('file');

        $summary = $importer->import(
            absolutePath:      $file->getRealPath(),
            originalExtension: strtolower($file->getClientOriginalExtension()),
        );

        $response = [
            'status' => 'ok',
            'import_id'         => $summary['import_id'],
            'total'             => $summary['total'],
            'inserted'          => $summary['inserted'],
            'skipped_duplicate' => $summary['skipped_duplicate'],
            'skipped_invalid'   => $summary['skipped_invalid'],
            'error_log_url'     => $summary['error_log_path']
                ? route('imports.errors', ['import' => $summary['import_id']])
                : null,
        ];

        return response()->json($response);
    }

    public function errors(string $import): BinaryFileResponse
    {
        $path = "imports/{$import}/errors.csv";
        abort_unless(Storage::disk('local')->exists($path), 404, 'Error log not found.');

        return response()->download(
            storage_path("app/{$path}"),
            "import-{$import}-errors.csv",
            ['Content-Type' => 'text/csv; charset=utf-8'],
        );
    }
}
