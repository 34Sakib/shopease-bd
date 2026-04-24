<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExportJob extends Model
{
    use HasUuids;

    protected $table = 'export_jobs';

    public $timestamps = false;

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'id', 'status', 'format', 'filters',
        'file_path', 'row_count', 'file_size_bytes', 'error',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'filters'         => 'array',
        'row_count'       => 'integer',
        'file_size_bytes' => 'integer',
        'created_at'      => 'datetime',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];
}
