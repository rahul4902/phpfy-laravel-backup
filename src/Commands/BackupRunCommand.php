<?php

namespace Phpfy\LaravelBackup\Commands;

use Illuminate\Console\Command;
use Phpfy\LaravelBackup\Services\BackupService;
use Phpfy\LaravelBackup\Exceptions\BackupException;

class BackupRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run 
                            {--only-db : Backup only the database}
                            {--only-files : Backup only files}
                            {--disable-notifications : Disable backup notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a backup';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $this->info('Starting backup...');
        $this->newLine();

        $onlyDb = $this->option('only-db');
        $onlyFiles = $this->option('only-files');

        if ($onlyDb && $onlyFiles) {
            $this->error('Cannot use --only-db and --only-files together!');
            return self::FAILURE;
        }

        // Display backup type
        if ($onlyDb) {
            $this->comment('📊 Backup Type: Database Only');
        } elseif ($onlyFiles) {
            $this->comment('📁 Backup Type: Files Only');
        } else {
            $this->comment('📦 Backup Type: Full (Database + Files)');
        }

        $this->newLine();

        try {
            // Show progress
            $progressBar = $this->output->createProgressBar(3);
            $progressBar->start();

            $result = $backupService->run(
                onlyDb: $onlyDb,
                onlyFiles: $onlyFiles
            );

            $progressBar->finish();
            $this->newLine(2);

            // Display success information
            $this->info('✓ Backup completed successfully!');
            $this->newLine();

            $this->table(
                ['Property', 'Value'],
                [
                    ['Filename', $result['filename']],
                    ['Size', $this->formatBytes($result['size'])],
                    ['Destinations', implode(', ', $result['destinations'])],
                ]
            );

            $this->newLine();
            $this->info('Backup saved to: ' . implode(', ', $result['destinations']));

            return self::SUCCESS;

        } catch (BackupException $e) {
            $this->newLine(2);
            $this->error('✗ Backup failed!');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();

            if ($this->output->isVerbose()) {
                $this->error('Stack trace:');
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->newLine(2);
            $this->error('✗ Unexpected error occurred!');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();

            return self::FAILURE;
        }
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}