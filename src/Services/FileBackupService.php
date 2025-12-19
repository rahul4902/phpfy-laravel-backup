<?php

namespace Phpfy\LaravelBackup\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Phpfy\LaravelBackup\Exceptions\FileBackupException;
use SplFileInfo;

class FileBackupService
{
    protected array $includes = [];
    protected array $excludes = [];
    protected bool $followLinks = false;
    protected bool $ignoreUnreadableDirectories = true;
    protected string $basePath;

    /**
     * Create a new FileBackupService instance.
     */
    public function __construct(array $config)
    {
        $this->includes = $config['backup']['source']['files']['include'] ?? [];
        $this->excludes = $config['backup']['source']['files']['exclude'] ?? [];
        $this->followLinks = $config['backup']['source']['files']['follow_links'] ?? false;
        $this->ignoreUnreadableDirectories = $config['backup']['source']['files']['ignore_unreadable_directories'] ?? true;

        // Set base path for relative paths (usually the project root)
        $this->basePath = base_path();

        // Normalize paths
        $this->includes = array_map([$this, 'normalizePath'], $this->includes);
        $this->excludes = array_map([$this, 'normalizePath'], $this->excludes);
    }

    /**
     * Get all files to backup.
     * Returns array with relative path as key and absolute path as value.
     */
    public function getFiles(): array
    {
        $files = [];

        foreach ($this->includes as $includePath) {
            if (!file_exists($includePath)) {
                continue;
            }

            if (is_file($includePath)) {
                if (!$this->shouldExclude($includePath)) {
                    $relativePath = $this->getRelativePath($includePath);
                    $files[$relativePath] = $includePath;
                }
                continue;
            }

            if (is_dir($includePath)) {
                $dirFiles = $this->scanDirectory($includePath);
                $files = array_merge($files, $dirFiles);
            }
        }

        return $files;
    }

    /**
     * Scan a directory recursively.
     * Returns array with relative path as key and absolute path as value.
     */
    protected function scanDirectory(string $directory): array
    {
        $files = [];

        try {
            $flags = RecursiveDirectoryIterator::SKIP_DOTS;

            if ($this->followLinks) {
                $flags |= RecursiveDirectoryIterator::FOLLOW_SYMLINKS;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, $flags),
                RecursiveIteratorIterator::SELF_FIRST
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                try {
                    // Skip if not a file
                    if (!$file->isFile()) {
                        continue;
                    }

                    $filePath = $file->getPathname();

                    // Normalize path for comparison
                    $normalizedPath = $this->normalizePath($filePath);

                    // Check if file should be excluded
                    if ($this->shouldExclude($normalizedPath)) {
                        continue;
                    }

                    // Check if file is readable
                    if (!$file->isReadable()) {
                        if (!$this->ignoreUnreadableDirectories) {
                            throw new FileBackupException("File is not readable: {$filePath}");
                        }
                        continue;
                    }

                    // Store with relative path as key, absolute path as value
                    $relativePath = $this->getRelativePath($normalizedPath);
                    $files[$relativePath] = $normalizedPath;

                } catch (\UnexpectedValueException $e) {
                    // Handle permission denied or other file access errors
                    if (!$this->ignoreUnreadableDirectories) {
                        throw new FileBackupException("Error accessing file: " . $e->getMessage(), 0, $e);
                    }
                    continue;
                }
            }

        } catch (\UnexpectedValueException $e) {
            if (!$this->ignoreUnreadableDirectories) {
                throw new FileBackupException("Error scanning directory '{$directory}': " . $e->getMessage(), 0, $e);
            }
        } catch (\Exception $e) {
            throw new FileBackupException("Unexpected error scanning directory '{$directory}': " . $e->getMessage(), 0, $e);
        }

        return $files;
    }

    /**
     * Get relative path from base path.
     */
    protected function getRelativePath(string $absolutePath): string
    {
        $normalizedAbsolute = $this->normalizePath($absolutePath);
        $normalizedBase = $this->normalizePath($this->basePath);

        // If path starts with base path, make it relative
        if (str_starts_with($normalizedAbsolute, $normalizedBase)) {
            $relativePath = substr($normalizedAbsolute, strlen($normalizedBase));
            return ltrim($relativePath, '/');
        }

        // If outside base path, just use the filename
        return basename($normalizedAbsolute);
    }

    /**
     * Check if a path should be excluded.
     */
    protected function shouldExclude(string $path): bool
    {
        $normalizedPath = $this->normalizePath($path);

        foreach ($this->excludes as $excludePath) {
            // Exact match
            if ($normalizedPath === $excludePath) {
                return true;
            }

            // Path starts with exclude path (directory exclusion)
            if (str_starts_with($normalizedPath, $excludePath . '/')) {
                return true;
            }

            // Pattern matching (for wildcards)
            if ($this->matchesPattern($normalizedPath, $excludePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if path matches a pattern (supports * wildcards).
     */
    protected function matchesPattern(string $path, string $pattern): bool
    {
        // Convert pattern to regex
        $regex = preg_quote($pattern, '/');
        $regex = str_replace('\*', '.*', $regex);
        $regex = '/^' . $regex . '$/';

        return preg_match($regex, $path) === 1;
    }

    /**
     * Normalize a file path.
     */
    protected function normalizePath(string $path): string
    {
        // Convert to absolute path
        $path = realpath($path) ?: $path;

        // Convert backslashes to forward slashes
        $path = str_replace('\\', '/', $path);

        // Remove trailing slashes
        $path = rtrim($path, '/');

        return $path;
    }

    /**
     * Get total size of all files to backup.
     */
    public function getTotalSize(): int
    {
        $files = $this->getFiles();
        $totalSize = 0;

        foreach ($files as $relativePath => $absolutePath) {
            if (file_exists($absolutePath) && is_readable($absolutePath)) {
                $totalSize += filesize($absolutePath);
            }
        }

        return $totalSize;
    }

    /**
     * Get file count.
     */
    public function getFileCount(): int
    {
        return count($this->getFiles());
    }

    /**
     * Get included paths.
     */
    public function getIncludes(): array
    {
        return $this->includes;
    }

    /**
     * Get excluded paths.
     */
    public function getExcludes(): array
    {
        return $this->excludes;
    }

    /**
     * Set base path for relative path calculation.
     */
    public function setBasePath(string $basePath): self
    {
        $this->basePath = $this->normalizePath($basePath);
        return $this;
    }

    /**
     * Get current base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
