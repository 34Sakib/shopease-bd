<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sale_id', 64);
            $table->string('branch', 64);
            $table->date('sale_date');
            $table->string('product_name', 255);
            $table->string('category', 64)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_pct', 5, 4);
            $table->string('payment_method', 32);
            $table->string('salesperson', 128)->default('Unknown');
            $table->timestamp('created_at')->useCurrent();

            $table->unique('sale_id');
            $table->index(['branch', 'sale_date'], 'sales_branch_date_idx');
            $table->index('category', 'sales_category_idx');
            $table->index('sale_date', 'sales_sale_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
