<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class MaskSensitiveFields implements ProcessorInterface
{
    private const MASKED_KEYS = ['password', 'email', 'token', 'plate'];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->maskArray($record->context);
        return $record->with(context: $context);
    }

    private function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::MASKED_KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskArray($value);
            }
        }
        return $data;
    }
}
