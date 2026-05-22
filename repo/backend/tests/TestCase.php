<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    protected function fakeAudioFile(string $name = 'test.mp3'): UploadedFile
    {
        // \xFF\xF3 is an MPEG sync word that finfo_file detects as audio/mpeg
        $tmp = tempnam(sys_get_temp_dir(), 'fake_audio_');
        file_put_contents($tmp, "\xFF\xF3" . str_repeat("\x00", 512));
        return new UploadedFile($tmp, $name, 'audio/mpeg', null, true);
    }

    protected function fakePdfFile(string $name = 'test.pdf'): UploadedFile
    {
        // %PDF-1.4 is the PDF magic header finfo_file detects as application/pdf
        $tmp = tempnam(sys_get_temp_dir(), 'fake_pdf_');
        file_put_contents($tmp, "%PDF-1.4\n" . str_repeat("\x00", 512));
        return new UploadedFile($tmp, $name, 'application/pdf', null, true);
    }
}
