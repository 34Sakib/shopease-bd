<?php

namespace App\Services\Sales;

use RuntimeException;

class NormalizationException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        public readonly mixed $rawValue,
        string $reason,
    ) {
        parent::__construct(sprintf(
            '[%s] %s (raw=%s)',
            $field,
            $reason,
            is_scalar($rawValue) || $rawValue === null
                ? var_export($rawValue, true)
                : '<non-scalar>',
        ));
    }
}
