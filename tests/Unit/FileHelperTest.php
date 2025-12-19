<?php

namespace Phpfy\LaravelBackup\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Phpfy\LaravelBackup\Helpers\FileHelper;

class FileHelperTest extends TestCase
{
    /** @test */
    public function it_formats_bytes_correctly()
    {
        $this->assertEquals('1.00 KB', FileHelper::formatBytes(1024));
        $this->assertEquals('1.00 MB', FileHelper::formatBytes(1024 * 1024));
        $this->assertEquals('1.00 GB', FileHelper::formatBytes(1024 * 1024 * 1024));
        $this->assertEquals('500 B', FileHelper::formatBytes(500));
    }

    /** @test */
    public function it_parses_size_strings()
    {
        $this->assertEquals(1024, FileHelper::parseSize('1KB'));
        $this->assertEquals(1024 * 1024, FileHelper::parseSize('1MB'));
        $this->assertEquals(1024 * 1024 * 1024, FileHelper::parseSize('1GB'));
        $this->assertEquals(100, FileHelper::parseSize('100'));
    }

    /** @test */
    public function it_sanitizes_filenames()
    {
        $this->assertEquals('test_file.txt', FileHelper::sanitizeFilename('test/file.txt'));
        $this->assertEquals('test_file.txt', FileHelper::sanitizeFilename('test\\file.txt'));
        $this->assertEquals('test_file.txt', FileHelper::sanitizeFilename('test?file.txt'));
        $this->assertEquals('my-file_name.zip', FileHelper::sanitizeFilename('my-file name.zip'));
    }

    /** @test */
    public function it_gets_file_extension()
    {
        $this->assertEquals('txt', FileHelper::getExtension('file.txt'));
        $this->assertEquals('zip', FileHelper::getExtension('backup.ZIP'));
        $this->assertEquals('tar', FileHelper::getExtension('archive.tar.gz'));
    }

    /** @test */
    public function it_validates_files()
    {
        // Create temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'content');

        $this->assertTrue(FileHelper::isValidFile($tempFile));

        // Empty file
        $emptyFile = tempnam(sys_get_temp_dir(), 'empty');
        $this->assertFalse(FileHelper::isValidFile($emptyFile));

        // Non-existent file
        $this->assertFalse(FileHelper::isValidFile('/non/existent/file.txt'));

        // Cleanup
        unlink($tempFile);
        unlink($emptyFile);
    }

    /** @test */
    public function it_gets_temp_directory()
    {
        $tempDir = FileHelper::getTempDirectory();
        $this->assertNotEmpty($tempDir);
        $this->assertTrue(is_dir($tempDir));
    }

    /** @test */
    public function it_generates_temp_filename()
    {
        $filename = FileHelper::getTempFilename('backup', 'zip');

        $this->assertStringContainsString('backup', $filename);
        $this->assertStringEndsWith('.zip', $filename);
    }
}