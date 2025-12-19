<?php

namespace Phpfy\LaravelBackup\Exceptions;

use Exception;

/**
 * Base exception for all backup-related errors.
 * 
 * This is the parent exception class for the backup package.
 * Catching this exception will catch all backup-related errors.
 * 
 * @package Phpfy\LaravelBackup\Exceptions
 */
class BackupException extends Exception
{
    /**
     * Create a new BackupException instance.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous throwable used for exception chaining
     */
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get a user-friendly error message.
     *
     * @return string
     */
    public function getUserMessage(): string
    {
        return "Backup operation failed: {$this->getMessage()}";
    }

    /**
     * Get the exception context for logging.
     *
     * @return array
     */
    public function getContext(): array
    {
        return [
            'exception' => get_class($this),
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    /**
     * Check if the exception is recoverable.
     *
     * @return bool
     */
    public function isRecoverable(): bool
    {
        // By default, backup exceptions are not recoverable
        // Subclasses can override this for specific cases
        return false;
    }

    /**
     * Get suggested actions to resolve the error.
     *
     * @return array
     */
    public function getSuggestedActions(): array
    {
        return [
            'Check application logs for detailed error information',
            'Verify backup configuration in config/backup.php',
            'Ensure sufficient disk space is available',
            'Check file and directory permissions',
            'Review database connection settings',
        ];
    }

    /**
     * Convert exception to array for API responses.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'type' => 'backup_error',
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'exception_class' => get_class($this),
            'suggested_actions' => $this->getSuggestedActions(),
        ];
    }

    /**
     * Render the exception as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            "%s: %s in %s on line %d",
            get_class($this),
            $this->getMessage(),
            $this->getFile(),
            $this->getLine()
        );
    }
}