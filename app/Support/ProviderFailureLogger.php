<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class ProviderFailureLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function report(string $provider, string $operation, string|Throwable $failure, array $context = []): void
    {
        Log::warning('Provider operation failed.', [
            'provider' => $provider,
            'operation' => $operation,
            'failure' => $failure instanceof Throwable ? $failure->getMessage() : $failure,
            'exception' => $failure instanceof Throwable ? $failure::class : null,
            ...$context,
        ]);
    }
}
