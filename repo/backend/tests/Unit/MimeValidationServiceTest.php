<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MimeValidationService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MimeValidationServiceTest extends TestCase
{
    private MimeValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MimeValidationService();
    }

    public function test_valid_mp3_passes_validation(): void
    {
        // \xFF\xF3 is an MPEG sync word finfo detects as audio/mpeg
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_mp3_');
        file_put_contents($tmpFile, "\xFF\xF3" . str_repeat("\x00", 512));
        $file = new UploadedFile($tmpFile, 'test.mp3', 'audio/mpeg', null, true);

        $result = $this->service->validate($file);
        unlink($tmpFile);

        $this->assertTrue($result['valid']);
    }

    public function test_file_exceeding_mp3_size_limit_fails(): void
    {
        // Small file with valid MP3 magic; mock getSize() to simulate an oversized upload
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_big_mp3_');
        file_put_contents($tmpFile, "\xFF\xF3" . str_repeat("\x00", 100));

        $file = $this->createMock(UploadedFile::class);
        $file->method('getPathname')->willReturn($tmpFile);
        $file->method('getSize')->willReturn(30 * 1024 * 1024);

        $result = $this->service->validate($file);
        unlink($tmpFile);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('MB limit', $result['error']);
    }

    public function test_elf_binary_is_rejected(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, "\x7FELF\x02\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00");

        $file = new UploadedFile($tmpFile, 'malware.mp3', 'audio/mpeg', null, true);
        $result = $this->service->validate($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Executable', $result['error']);

        unlink($tmpFile);
    }
}
