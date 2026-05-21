<?php

declare(strict_types=1);

namespace App\Services;

interface ScanAdapterInterface
{
    public function scan(string $filePath): bool;
}

class ScanHookService
{
    /** @var ScanAdapterInterface[] */
    private array $adapters = [];

    public function registerAdapter(ScanAdapterInterface $adapter): void
    {
        $this->adapters[] = $adapter;
    }

    public function scan(string $filePath): array
    {
        if (empty($this->adapters)) {
            return ['clean' => true, 'reason' => 'no_adapter'];
        }

        foreach ($this->adapters as $adapter) {
            if (!$adapter->scan($filePath)) {
                return ['clean' => false, 'reason' => 'adapter_rejected'];
            }
        }

        return ['clean' => true, 'reason' => 'passed'];
    }
}
