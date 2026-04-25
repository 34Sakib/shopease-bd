<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $table = 'sales';

    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'branch',
        'sale_date',
        'product_name',
        'category',
        'quantity',
        'unit_price',
        'discount_pct',
        'payment_method',
        'salesperson',
    ];

    protected $casts = [
        'sale_date'    => 'date',
        'quantity'     => 'integer',
        'unit_price'   => 'decimal:2',
        'discount_pct' => 'decimal:4',
        'created_at'   => 'datetime',
    ];

    public function scopeBranch(Builder $query, ?string $branch): Builder
    {
        return $branch ? $query->where('branch', $branch) : $query;
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return $category ? $query->where('category', $category) : $query;
    }

    public function scopePaymentMethod(Builder $query, ?string $method): Builder
    {
        return $method ? $query->where('payment_method', $method) : $query;
    }

    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->where('sale_date', '>=', $from);
        }
        if ($to) {
            $query->where('sale_date', '<=', $to);
        }
        return $query;
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->branch($filters['branch'] ?? null)
            ->category($filters['category'] ?? null)
            ->paymentMethod($filters['payment_method'] ?? null)
            ->dateRange($filters['from'] ?? null, $filters['to'] ?? null);
    }
}
