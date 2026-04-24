<?php

namespace App\Http\Requests;

use App\Services\Sales\SaleNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch'         => ['sometimes', 'nullable', 'string', Rule::in(SaleNormalizer::ALLOWED_BRANCHES)],
            'category'       => ['sometimes', 'nullable', 'string', 'max:64'],
            'payment_method' => ['sometimes', 'nullable', 'string', Rule::in(SaleNormalizer::ALLOWED_PAYMENT_METHODS)],
            'from'           => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to'             => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page'           => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->only(['branch', 'category', 'payment_method', 'from', 'to']);
    }
}
