<?php

namespace Phpfy\LaravelBackup\Exceptions;

/**
 * Exception thrown when file backup operations fail.
 * 
 * This exception is used for file-related backup errors,
 * including permission issues, I/O errors, and path problems.
 * 
 * @package Phpfy\LaravelBackup\Exceptions
 */
class FileBackupException extends BackupException
{
    protected ?string $filePath = null;
    protected ?string $operation = null;

    /**
     * Set the file path that caused the error.
     *
     * @param string $filePath
     * @return self
     */
    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    /**
     * Get the file path that caused the error.
     *
     * @return string|null
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * Set the operation that was being performed.
     *
     * @param string $operation (e.g., 'read', 'write', 'scan', 'compress')
     * @return self
     */
    public function setOperation(string $operation): self
    {
        $this->operation = $operation;
        return $this;
    }

    /**
     * Get the operation that was being performed.
     *
     * @return string|null
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * Get a user-friendly error message.
     *
     * @return string
     */
    public function getUserMessage(): string
    {
        $message = "File backup failed";

        if ($this->operation) {
            $message .= " during {$this->operation} operation";
        }

        if ($this->filePath) {
            $message .= " on file '{$this->filePath}'";
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
            'file_path' => $this->filePath,
            'operation' => $this->operation,
            'file_exists' => $this->filePath ? file_exists($this->filePath) : null,
            'is_readable' => $this->filePath && file_exists($this->filePath) ? is_readable($this->filePath) : null,
            'is_writable' => $this->filePath && file_exists($this->filePath) ? is_writable($this->filePath) : null,
        ]);
    }

    /**
     * Get suggested actions specific to file backup errors.
     *
     * @return array
     */
    public function getSuggestedActions(): array
    {
        $actions = [
            'Check file and directory permissions',
            'Ensure sufficient disk space is available',
        ];

        if ($this->operation === 'read' || $this->operation === 'scan') {
            $actions[] = 'Verify read permissions on source files and directories';
            $actions[] = 'Check if files/directories exist';
            $actions[] = 'Ensure no files are locked by other processes';
        } elseif ($this->operation === 'write' || $this->operation === 'compress') {
            $actions[] = 'Verify write permissions on backup directory';
            $actions[] = 'Check temporary directory is writable';
            $actions[] = 'Ensure ZIP extension is enabled in PHP';
        }

        if ($this->filePath) {
            $actions[] = "Check the specific file: {$this->filePath}";
        }

        $actions = array_merge($actions, [
            'Review include/exclude patterns in config',
            'Check for symbolic links if not following links',
            'Verify no files are too large to process',
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
        // File backup errors might be recoverable in some cases
        // For example, if a single file is unreadable, we could skip it
        // This depends on configuration settings
        return $this->operation === 'read' || $this->operation === 'scan';
    }

    /**
     * Convert exception to array for API responses.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'type' => 'file_backup_error',
            'file_path' => $this->filePath,
            'operation' => $this->operation,
        ]);
    }

    /**
     * Create exception for permission denied errors.
     *
     * @param string $filePath
     * @param string $operation
     * @return static
     */
    public static function permissionDenied(string $filePath, string $operation = 'read'): self
    {
        $exception = new static("Permission denied: Cannot {$operation} file");
        $exception->setFilePath($filePath);
        $exception->setOperation($operation);
        return $exception;
    }

    /**
     * Create exception for file not found errors.
     *
     * @param string $filePath
     * @return static
     */
    public static function fileNotFound(string $filePath): self
    {
        $exception = new static("File or directory not found");
        $exception->setFilePath($filePath);
        return $exception;
    }

    /**
     * Create exception for unreadable files.
     *
     * @param string $filePath
     * @return static
     */
    public static function unreadable(string $filePath): self
    {
        $exception = new static("File is not readable");
        $exception->setFilePath($filePath);
        $exception->setOperation('read');
        return $exception;
    }
}