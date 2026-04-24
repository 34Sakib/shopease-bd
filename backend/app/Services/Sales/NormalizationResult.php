<?php

namespace App\Services\Sales;

class NormalizationResult
{
    /**
     * @param  array<string, mixed>  $data   Cleaned row ready for DB insert.
     * @param  list<array{field:string, raw:mixed, reason:string}>  $errors
     */
    private function __construct(
        public readonly bool $ok,
        public readonly array $data,
        public readonly array $errors,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function success(array $data): self
    {
        return new self(true, $data, []);
    }

    /** @param  list<array{field:string, raw:mixed, reason:string}>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, [], $errors);
    }

    public function firstError(): ?string
    {
        return $this->errors[0]['reason'] ?? null;
    }

    public function errorSummary(): string
    {
        return implode('; ', array_map(
            fn ($e) => sprintf('%s: %s', $e['field'], $e['reason']),
            $this->errors,
        ));
    }
}
