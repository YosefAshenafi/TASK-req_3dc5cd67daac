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
        $file = UploadedFile::fake()->create('test.mp3', 512, 'audio/mpeg');
        $result = $this->service->validate($file);

        $this->assertTrue($result['valid']);
    }

    public function test_file_exceeding_mp3_size_limit_fails(): void
    {
        $file = UploadedFile::fake()->create('big.mp3', 30 * 1024, 'audio/mpeg');
        $result = $this->service->validate($file);

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
