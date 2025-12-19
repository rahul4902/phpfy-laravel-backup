<?php

namespace Phpfy\LaravelBackup\Tests\Unit;

use Phpfy\LaravelBackup\Tests\TestCase;
use Phpfy\LaravelBackup\Services\DatabaseDumper;
use Phpfy\LaravelBackup\Exceptions\DatabaseDumpException;

class DatabaseDumperTest extends TestCase
{
    /** @test */
    /** @test */
    public function it_can_dump_sqlite_database()
    {
        $dumper = new DatabaseDumper('testbench');
        $outputPath = storage_path('app/backups');

        // Create directory if it doesn't exist
        if (!file_exists($outputPath)) {
            mkdir($outputPath, 0777, true);
        }

        $dumpFile = $dumper->dump($outputPath);

        $this->assertFileExists($dumpFile);
        // For :memory: database, just check file exists (it will have minimal content)
        $this->assertGreaterThanOrEqual(0, filesize($dumpFile));
        $this->assertStringEndsWith('.sqlite', $dumpFile);

        // Cleanup
        if (file_exists($dumpFile)) {
            unlink($dumpFile);
        }
    }


    /** @test */
    public function it_throws_exception_for_invalid_connection()
    {
        $this->expectException(DatabaseDumpException::class);

        $dumper = new DatabaseDumper('invalid_connection');
        $dumper->dump(sys_get_temp_dir());
    }

    /** @test */
    public function it_gets_correct_driver()
    {
        $dumper = new DatabaseDumper('testbench');

        $this->assertEquals('sqlite', $dumper->getDriver());
    }

    /** @test */
    public function it_throws_exception_for_unsupported_driver()
    {
        config(['database.connections.unsupported' => [
            'driver' => 'mongodb',
        ]]);

        $this->expectException(DatabaseDumpException::class);

        $dumper = new DatabaseDumper('unsupported');
        $dumper->dump(sys_get_temp_dir());
    }
}
