<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Services\Sales\SalesExporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public readonly string $exportJobId) {}

    public function handle(SalesExporter $exporter): void
    {
        $job = ExportJob::findOrFail($this->exportJobId);
        $job->update([
            'status'     => ExportJob::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        $dir = storage_path("app/exports/{$job->id}");
        @mkdir($dir, 0777, true);

        $extension = $job->format === 'xlsx' ? 'xlsx' : 'csv';
        $absolutePath = "{$dir}/sales-export.{$extension}";

        $rowCount = 0;

        try {
            if ($job->format === 'xlsx') {
                $rowCount = $exporter->writeXlsx($absolutePath, $job->filters ?? []);
            } else {
                $fp = fopen($absolutePath, 'w');
                $rowCount = $exporter->streamCsv($fp, $job->filters ?? []);
                fclose($fp);
            }
        } catch (Throwable $e) {
            $job->update([
                'status'       => ExportJob::STATUS_FAILED,
                'error'        => mb_substr($e->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
            throw $e;
        }

        $job->update([
            'status'          => ExportJob::STATUS_COMPLETED,
            'file_path'       => "exports/{$job->id}/sales-export.{$extension}",
            'row_count'       => $rowCount,
            'file_size_bytes' => @filesize($absolutePath) ?: null,
            'completed_at'    => now(),
        ]);
    }
}
