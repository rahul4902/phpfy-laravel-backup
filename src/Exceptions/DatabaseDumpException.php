<?php

namespace Phpfy\LaravelBackup\Exceptions;

/**
 * Exception thrown when database dump operations fail.
 * 
 * This exception is used for all database-related backup errors,
 * including connection failures, dump command errors, and file I/O issues.
 * 
 * @package Phpfy\LaravelBackup\Exceptions
 */
class DatabaseDumpException extends BackupException
{
    protected ?string $connection = null;
    protected ?string $driver = null;
    protected ?string $commandOutput = null;

    /**
     * Set the database connection name.
     *
     * @param string $connection
     * @return self
     */
    public function setConnection(string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Get the database connection name.
     *
     * @return string|null
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the database driver.
     *
     * @param string $driver
     * @return self
     */
    public function setDriver(string $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Get the database driver.
     *
     * @return string|null
     */
    public function getDriver(): ?string
    {
        return $this->driver;
    }

    /**
     * Set the command output/error.
     *
     * @param string $output
     * @return self
     */
    public function setCommandOutput(string $output): self
    {
        $this->commandOutput = $output;
        return $this;
    }

    /**
     * Get the command output/error.
     *
     * @return string|null
     */
    public function getCommandOutput(): ?string
    {
        return $this->commandOutput;
    }

    /**
     * Get a user-friendly error message.
     *
     * @return string
     */
    public function getUserMessage(): string
    {
        $message = "Database dump failed";

        if ($this->connection) {
            $message .= " for connection '{$this->connection}'";
        }

        if ($this->driver) {
            $message .= " ({$this->driver})";
        }

        $message .= ": {$this->getMessage()}";

        return $message;
    }

    /**
     * Get the exception context for logging.
     *
     * @return array
     */
    public function getContext(): array
    {
        return array_merge(parent::getContext(), [
            'connection' => $this->connection,
            'driver' => $this->driver,
            'command_output' => $this->commandOutput,
        ]);
    }

    /**
     * Get suggested actions specific to database dump errors.
     *
     * @return array
     */
    public function getSuggestedActions(): array
    {
        $actions = [
            'Verify database connection credentials',
            'Ensure database server is running and accessible',
        ];

        if ($this->driver === 'mysql') {
            $actions[] = 'Check if mysqldump is installed and in PATH';
            $actions[] = 'Verify MySQL user has sufficient privileges';
        } elseif ($this->driver === 'pgsql') {
            $actions[] = 'Check if pg_dump is installed and in PATH';
            $actions[] = 'Verify PostgreSQL user has sufficient privileges';
            $actions[] = 'Ensure PGPASSWORD environment variable is set';
        } elseif ($this->driver === 'sqlite') {
            $actions[] = 'Check if SQLite database file exists';
            $actions[] = 'Verify read permissions on SQLite database file';
        }

        $actions = array_merge($actions, [
            'Check database timeout settings',
            'Review command output for specific errors',
            'Ensure sufficient disk space for dump file',
        ]);

        return $actions;
    }

    /**
     * Check if the exception is recoverable.
     *
     * @return bool
     */
    public function isRecoverable(): bool
    {
        // Database dump errors are generally not recoverable automatically
        // They require manual intervention to fix configuration or permissions
        return false;
    }

    /**
     * Convert exception to array for API responses.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'type' => 'database_dump_error',
            'connection' => $this->connection,
            'driver' => $this->driver,
            'command_output' => $this->commandOutput,
        ]);
    }
}