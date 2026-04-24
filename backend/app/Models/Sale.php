<?php

namespace App\Models;

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
}
