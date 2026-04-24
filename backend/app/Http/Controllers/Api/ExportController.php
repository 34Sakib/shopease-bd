<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequest;
use App\Jobs\GenerateExport;
use App\Models\ExportJob;
use App\Models\Sale;
use App\Services\Sales\SalesExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public const SYNC_ROW_LIMIT = 10000;

    public function csv(ExportRequest $request, SalesExporter $exporter): StreamedResponse|JsonResponse
    {
        return $this->exportOrDispatch($request, $exporter, 'csv');
    }

    public function excel(ExportRequest $request, SalesExporter $exporter): BinaryFileResponse|JsonResponse
    {
        return $this->exportOrDispatch($request, $exporter, 'xlsx');
    }

    public function status(string $export): JsonResponse
    {
        $job = ExportJob::findOrFail($export);
        return response()->json([
            'id'              => $job->id,
            'status'          => $job->status,
            'format'          => $job->format,
            'row_count'       => $job->row_count,
            'file_size_bytes' => $job->file_size_bytes,
            'error'           => $job->error,
            'download_url'    => $job->status === ExportJob::STATUS_COMPLETED
                ? route('exports.download', ['export' => $job->id])
                : null,
            'created_at'      => $job->created_at?->toIso8601String(),
            'started_at'      => $job->started_at?->toIso8601String(),
            'completed_at'    => $job->completed_at?->toIso8601String(),
        ]);
    }

    public function download(string $export): BinaryFileResponse
    {
        $job = ExportJob::findOrFail($export);

        abort_unless(
            $job->status === ExportJob::STATUS_COMPLETED && $job->file_path,
            404,
            'Export is not ready.',
        );

        $absolute = storage_path("app/{$job->file_path}");
        abort_unless(is_file($absolute), 404, 'Export file missing.');

        $name = $job->format === 'xlsx' ? "sales-{$export}.xlsx" : "sales-{$export}.csv";
        $mime = $job->format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=utf-8';

        return response()->download($absolute, $name, ['Content-Type' => $mime]);
    }

    /**
     * Decide: small export streams synchronously; large export goes async.
     */
    private function exportOrDispatch(ExportRequest $request, SalesExporter $exporter, string $format): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        $filters = $request->filters();

        $rowCount = Sale::query()->applyFilters($filters)->count();

        if ($rowCount <= self::SYNC_ROW_LIMIT) {
            return $format === 'xlsx'
                ? $this->streamXlsxSync($exporter, $filters)
                : $this->streamCsvSync($exporter, $filters);
        }

        $job = ExportJob::create([
            'id'      => (string) \Illuminate\Support\Str::uuid(),
            'status'  => ExportJob::STATUS_PENDING,
            'format'  => $format,
            'filters' => $filters,
        ]);

        Bus::dispatch(new GenerateExport($job->id));

        return response()->json([
            'status'     => 'queued',
            'job_id'     => $job->id,
            'row_count'  => $rowCount,
            'status_url' => route('exports.status', ['export' => $job->id]),
        ], 202);
    }

    private function streamCsvSync(SalesExporter $exporter, array $filters): StreamedResponse
    {
        $filename = 'sales-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($exporter, $filters) {
            $out = fopen('php://output', 'w');
            $exporter->streamCsv($out, $filters);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    private function streamXlsxSync(SalesExporter $exporter, array $filters): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sales_').'.xlsx';
        $exporter->writeXlsx($tmp, $filters);

        $filename = 'sales-'.now()->format('Ymd-His').'.xlsx';

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
