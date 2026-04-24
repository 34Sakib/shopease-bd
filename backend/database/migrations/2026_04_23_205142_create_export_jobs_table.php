<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status', 20);
            $table->string('format', 10);
            $table->json('filters')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
