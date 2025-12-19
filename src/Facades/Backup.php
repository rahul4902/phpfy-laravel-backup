<?php

namespace Phpfy\LaravelBackup\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Backup Facade
 * 
 * Provides a convenient static interface to the BackupService.
 * 
 * @method static array run(bool $onlyDb = false, bool $onlyFiles = false)
 * @method static array getConfig()
 * @method static string getTempDirectory()
 * 
 * @see \Phpfy\LaravelBackup\Services\BackupService
 * @package Phpfy\LaravelBackup\Facades
 */
class Backup extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * This tells Laravel which service to resolve from the container
     * when the facade is used.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'backup';
    }
}
