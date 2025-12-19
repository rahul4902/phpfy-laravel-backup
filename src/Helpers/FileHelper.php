<?php

namespace Phpfy\LaravelBackup\Helpers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * File helper class with utility methods for file operations.
 * 
 * Provides static helper methods for common file operations used
 * throughout the backup package.
 * 
 * @package Phpfy\LaravelBackup\Helpers
 */
class FileHelper
{
    /**
     * Format bytes to human-readable format.
     *
     * @param int $bytes The number of bytes
     * @param int $precision Decimal precision
     * @return string Formatted size (e.g., "1.23 MB")
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes < 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        $unitIndex = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        // Don't add decimals for bytes
        if ($unitIndex === 0) {
            return round($size) . ' ' . $units[$unitIndex];
        }

        // Format KB and above with decimals
        return number_format($size, $precision) . ' ' . $units[$unitIndex];
    }

    /**
     * Parse human-readable size to bytes.
     *
     * @param string $size Size string (e.g., "10MB", "1.5GB")
     * @return int Number of bytes
     */
    public static function parseSize(string $size): int
    {
        $size = trim($size);

        // Check if it's just a number
        if (is_numeric($size)) {
            return (int) $size;
        }

        // Extract number and unit
        preg_match('/^([\d.]+)\s*([A-Za-z]*)$/', $size, $matches);

        if (empty($matches)) {
            return 0;
        }

        $value = (float) $matches[1];
        $unit = strtoupper($matches[2] ?? '');

        return match ($unit) {
            'K', 'KB' => (int) ($value * 1024),
            'M', 'MB' => (int) ($value * 1024 * 1024),
            'G', 'GB' => (int) ($value * 1024 * 1024 * 1024),
            'T', 'TB' => (int) ($value * 1024 * 1024 * 1024 * 1024),
            'P', 'PB' => (int) ($value * 1024 * 1024 * 1024 * 1024 * 1024),
            default => (int) $value,
        };
    }


    /**
     * Sanitize a filename by removing unsafe characters.
     *
     * @param string $filename The filename to sanitize
     * @param string $replacement Character to replace unsafe chars with
     * @return string Sanitized filename
     */
    public static function sanitizeFilename(string $filename, string $replacement = '_'): string
    {
        // Remove path separators
        $filename = str_replace(['/', '\\'], $replacement, $filename);

        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', $replacement, $filename);

        // Remove multiple consecutive replacements
        $filename = preg_replace('/' . preg_quote($replacement, '/') . '+/', $replacement, $filename);

        // Remove leading/trailing replacements
        $filename = trim($filename, $replacement);

        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'file';
        }

        return $filename;
    }

    /**
     * Get the directory size recursively.
     *
     * @param string $directory Path to directory
     * @return int Total size in bytes
     */
    public static function getDirectorySize(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Exception $e) {
            // Return current size if error occurs
        }

        return $size;
    }

    /**
     * Count files in a directory recursively.
     *
     * @param string $directory Path to directory
     * @param bool $includeDirectories Count directories as well
     * @return int Number of files
     */
    public static function countFiles(string $directory, bool $includeDirectories = false): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $count = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() || ($includeDirectories && $file->isDir())) {
                    $count++;
                }
            }
        } catch (\Exception $e) {
            // Return current count if error occurs
        }

        return $count;
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $directory Path to directory
     * @param int $permissions Directory permissions (default: 0755)
     * @return bool True if directory exists or was created
     */
    public static function ensureDirectoryExists(string $directory, int $permissions = 0755): bool
    {
        if (is_dir($directory)) {
            return true;
        }

        return mkdir($directory, $permissions, true);
    }

    /**
     * Delete a directory and all its contents recursively.
     *
     * @param string $directory Path to directory
     * @return bool True if successful
     */
    public static function deleteDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }

            return rmdir($directory);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file extension from filename.
     *
     * @param string $filename The filename
     * @return string File extension (without dot)
     */
    public static function getExtension(string $filename): string
    {
        // Get the last extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // For .tar.gz, .tar.bz2, etc., return the first extension
        if (in_array($ext, ['gz', 'bz2', 'xz', 'zip']) && str_contains($filename, '.tar.')) {
            $parts = explode('.', $filename);
            if (count($parts) >= 3) {
                return $parts[count($parts) - 2]; // Return 'tar'
            }
        }

        return $ext;
    }

    /**
     * Check if a file is readable and not empty.
     *
     * @param string $file Path to file
     * @return bool True if file is readable and not empty
     */
    public static function isValidFile(string $file): bool
    {
        return file_exists($file) && is_readable($file) && filesize($file) > 0;
    }

    /**
     * Get relative path from base path.
     *
     * @param string $absolutePath The absolute path
     * @param string $basePath The base path to make relative to
     * @return string Relative path
     */
    public static function getRelativePath(string $absolutePath, string $basePath): string
    {
        $absolutePath = str_replace('\\', '/', realpath($absolutePath));
        $basePath = str_replace('\\', '/', realpath($basePath));

        if (empty($absolutePath) || empty($basePath)) {
            return $absolutePath;
        }

        if (str_starts_with($absolutePath, $basePath)) {
            return substr($absolutePath, strlen($basePath) + 1);
        }

        return $absolutePath;
    }

    /**
     * Check if path is within allowed directory.
     *
     * @param string $path Path to check
     * @param string $allowedDirectory Allowed base directory
     * @return bool True if path is within allowed directory
     */
    public static function isPathSafe(string $path, string $allowedDirectory): bool
    {
        $realPath = realpath($path);
        $realAllowed = realpath($allowedDirectory);

        if ($realPath === false || $realAllowed === false) {
            return false;
        }

        return str_starts_with($realPath, $realAllowed);
    }

    /**
     * Get temporary directory path.
     *
     * @return string Temporary directory path
     */
    public static function getTempDirectory(): string
    {
        return sys_get_temp_dir();
    }

    /**
     * Generate a unique temporary filename.
     *
     * @param string $prefix Filename prefix
     * @param string $extension File extension
     * @return string Full path to temporary file
     */
    public static function getTempFilename(string $prefix = 'backup', string $extension = 'tmp'): string
    {
        $tempDir = self::getTempDirectory();
        $filename = $prefix . '_' . uniqid() . '_' . time() . '.' . $extension;

        return $tempDir . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Check if sufficient disk space is available.
     *
     * @param string $directory Directory to check
     * @param int $requiredBytes Required space in bytes
     * @return bool True if sufficient space available
     */
    public static function hasSufficientSpace(string $directory, int $requiredBytes): bool
    {
        $freeSpace = disk_free_space($directory);

        if ($freeSpace === false) {
            return false;
        }

        return $freeSpace >= $requiredBytes;
    }

    /**
     * Get available disk space in directory.
     *
     * @param string $directory Directory path
     * @return int Available space in bytes, or 0 on error
     */
    public static function getAvailableSpace(string $directory): int
    {
        $freeSpace = disk_free_space($directory);

        return $freeSpace !== false ? (int) $freeSpace : 0;
    }
}
