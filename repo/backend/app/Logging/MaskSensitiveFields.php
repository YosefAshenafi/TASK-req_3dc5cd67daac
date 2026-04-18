<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class MaskSensitiveFields implements ProcessorInterface
{
    // Exact key names that must never appear in log context as plaintext.
    private const MASKED_KEYS = [
        'password',
        'password_confirmation',
        'email',
        'email_enc',
        'token',
        'api_token',
        'access_token',
        'plain_text_token',
        'authorization',
        'bearer',
        'plate',
        'session',
        'session_id',
        'cookie',
        'idempotency_key',
        'x-idempotency-key',
        'x_idempotency_key',
        'secret',
        'x-gateway-token',
        'x_gateway_token',
    ];

    // Substrings treated as sensitive when matched case-insensitively against a key.
    private const MASKED_SUBSTRINGS = ['password', 'token', 'secret'];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->maskArray($record->context);
        return $record->with(context: $context);
    }

    private function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskArray($value);
            }
        }
        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        if (in_array($normalized, self::MASKED_KEYS, true)) {
            return true;
        }
        foreach (self::MASKED_SUBSTRINGS as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }
        return false;
    }
}
