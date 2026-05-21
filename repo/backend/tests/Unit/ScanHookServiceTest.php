<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ScanAdapterInterface;
use App\Services\ScanHookService;
use Tests\TestCase;

class ScanHookServiceTest extends TestCase
{
    private ScanHookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScanHookService();
    }

    // ---------------------------------------------------------------
    // No adapters registered
    // ---------------------------------------------------------------

    public function test_no_adapters_returns_clean_with_no_adapter_reason(): void
    {
        $result = $this->service->scan('/any/path.mp3');

        $this->assertTrue($result['clean']);
        $this->assertSame('no_adapter', $result['reason']);
    }

    // ---------------------------------------------------------------
    // Single adapter — pass path
    // ---------------------------------------------------------------

    public function test_passing_adapter_returns_clean_true(): void
    {
        $this->service->registerAdapter($this->adapterReturning(true));

        $result = $this->service->scan('/safe/file.mp3');

        $this->assertTrue($result['clean']);
        $this->assertSame('passed', $result['reason']);
    }

    // ---------------------------------------------------------------
    // Single adapter — reject path
    // ---------------------------------------------------------------

    public function test_rejecting_adapter_returns_clean_false(): void
    {
        $this->service->registerAdapter($this->adapterReturning(false));

        $result = $this->service->scan('/malware/file.exe');

        $this->assertFalse($result['clean']);
        $this->assertSame('adapter_rejected', $result['reason']);
    }

    // ---------------------------------------------------------------
    // Multiple adapters — all pass
    // ---------------------------------------------------------------

    public function test_multiple_passing_adapters_returns_clean_true(): void
    {
        $this->service->registerAdapter($this->adapterReturning(true));
        $this->service->registerAdapter($this->adapterReturning(true));
        $this->service->registerAdapter($this->adapterReturning(true));

        $result = $this->service->scan('/safe.mp4');

        $this->assertTrue($result['clean']);
        $this->assertSame('passed', $result['reason']);
    }

    // ---------------------------------------------------------------
    // Multiple adapters — first rejects (short-circuit)
    // ---------------------------------------------------------------

    public function test_first_rejecting_adapter_short_circuits_remaining(): void
    {
        $callCount = 0;

        $counting = new class ($callCount) implements ScanAdapterInterface {
            public function __construct(private int &$count) {}
            public function scan(string $filePath): bool
            {
                $this->count++;
                return true;
            }
        };

        $this->service->registerAdapter($this->adapterReturning(false));
        $this->service->registerAdapter($counting);

        $result = $this->service->scan('/bad.exe');

        $this->assertFalse($result['clean']);
        $this->assertSame(0, $callCount, 'Second adapter must not be invoked after first rejects');
    }

    // ---------------------------------------------------------------
    // Mixed adapters — later one rejects
    // ---------------------------------------------------------------

    public function test_later_rejecting_adapter_causes_failure(): void
    {
        $this->service->registerAdapter($this->adapterReturning(true));
        $this->service->registerAdapter($this->adapterReturning(true));
        $this->service->registerAdapter($this->adapterReturning(false));

        $result = $this->service->scan('/suspicious.pdf');

        $this->assertFalse($result['clean']);
        $this->assertSame('adapter_rejected', $result['reason']);
    }

    // ---------------------------------------------------------------
    // Adapter registered after scan still works on next call
    // ---------------------------------------------------------------

    public function test_adapter_registered_after_first_scan_is_used_on_second_scan(): void
    {
        $firstResult = $this->service->scan('/first.mp3');
        $this->assertSame('no_adapter', $firstResult['reason']);

        $this->service->registerAdapter($this->adapterReturning(true));

        $secondResult = $this->service->scan('/second.mp3');
        $this->assertTrue($secondResult['clean']);
        $this->assertSame('passed', $secondResult['reason']);
    }

    // ---------------------------------------------------------------
    // File path is forwarded to the adapter
    // ---------------------------------------------------------------

    public function test_file_path_is_passed_to_adapter(): void
    {
        $capturedPath = null;

        $spy = new class ($capturedPath) implements ScanAdapterInterface {
            public function __construct(private mixed &$captured) {}
            public function scan(string $filePath): bool
            {
                $this->captured = $filePath;
                return true;
            }
        };

        $this->service->registerAdapter($spy);
        $this->service->scan('/media/unique-path.mp3');

        $this->assertSame('/media/unique-path.mp3', $capturedPath);
    }

    // ---------------------------------------------------------------
    // Result structure invariants
    // ---------------------------------------------------------------

    public function test_scan_result_always_contains_clean_and_reason_keys(): void
    {
        $result = $this->service->scan('/any/file.mp3');

        $this->assertArrayHasKey('clean', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertIsBool($result['clean']);
        $this->assertIsString($result['reason']);
    }

    public function test_scan_result_clean_is_bool_when_adapter_rejects(): void
    {
        $this->service->registerAdapter($this->adapterReturning(false));

        $result = $this->service->scan('/bad.exe');

        $this->assertIsBool($result['clean']);
        $this->assertFalse($result['clean']);
    }

    // ---------------------------------------------------------------
    // All adapters receive same path in multi-adapter chain
    // ---------------------------------------------------------------

    public function test_all_adapters_in_chain_receive_same_file_path(): void
    {
        $paths = [];

        $spyFactory = function () use (&$paths): ScanAdapterInterface {
            return new class ($paths) implements ScanAdapterInterface {
                public function __construct(private array &$log) {}
                public function scan(string $filePath): bool
                {
                    $this->log[] = $filePath;
                    return true;
                }
            };
        };

        $this->service->registerAdapter($spyFactory());
        $this->service->registerAdapter($spyFactory());

        $this->service->scan('/shared/path.mp3');

        $this->assertCount(2, $paths);
        $this->assertSame('/shared/path.mp3', $paths[0]);
        $this->assertSame('/shared/path.mp3', $paths[1]);
    }

    // ---------------------------------------------------------------
    // Adapters called in registration order
    // ---------------------------------------------------------------

    public function test_adapters_are_called_in_registration_order(): void
    {
        $callOrder = [];

        $makeOrdered = function (int $id) use (&$callOrder): ScanAdapterInterface {
            return new class ($id, $callOrder) implements ScanAdapterInterface {
                public function __construct(private int $id, private array &$order) {}
                public function scan(string $filePath): bool
                {
                    $this->order[] = $this->id;
                    return true;
                }
            };
        };

        $this->service->registerAdapter($makeOrdered(1));
        $this->service->registerAdapter($makeOrdered(2));
        $this->service->registerAdapter($makeOrdered(3));

        $this->service->scan('/ordered.mp3');

        $this->assertSame([1, 2, 3], $callOrder);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function adapterReturning(bool $value): ScanAdapterInterface
    {
        return new class ($value) implements ScanAdapterInterface {
            public function __construct(private readonly bool $result) {}
            public function scan(string $filePath): bool
            {
                return $this->result;
            }
        };
    }
}
