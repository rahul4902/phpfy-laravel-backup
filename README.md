Laravel Backup Package

Description
A production-ready Laravel package for backing up your database and application files. Works with MySQL, PostgreSQL, SQLite, and SQL Server using pure PHP database dumps, without requiring external CLI tools like mysqldump or pg_dump. Designed for commercial and open-source projects.

Features

Database backups: MySQL, PostgreSQL, SQLite, SQL Server

File backups with include and exclude paths

No external dump tools required (pure PHP implementation)

AES-256 backup encryption support

Multiple storage disks (local, S3, FTP, etc.)

Automatic cleanup with retention rules

Artisan commands for running, listing, and cleaning backups

Can be scheduled via Laravel scheduler

Fully tested and PSR-compliant structure

Requirements

PHP 8.1 or higher

Laravel 10.x or 11.x

PDO extensions for the databases you use:

pdo_mysql for MySQL

pdo_pgsql for PostgreSQL

pdo_sqlite for SQLite

pdo_sqlsrv for SQL Server

Installation

Require the package via Composer:
composer require phpfy/laravel-backup

Publish the configuration file:
php artisan vendor:publish --provider="Phpfy\LaravelBackup\LaravelBackupServiceProvider" --tag=backup-config

Configuration
The main configuration file is config/backup.php.

Basic example:

backup.name: Name used in backup filenames (defaults to APP_NAME)

backup.source.databases: List of database connections to back up

backup.source.files.include: Paths to include in file backups

backup.source.files.exclude: Paths to exclude from backups

destination.disks: Storage disks where backups will be stored

encryption.enabled: Whether to encrypt backups

encryption.password: Encryption password (use env)

cleanup: Retention and cleanup strategy

Example configuration:

backup:
name: env(APP_NAME, "laravel-backup")
source:
databases:
- mysql
# or: pgsql, sqlite, sqlsrv
files:
include:
- base_path()
exclude:
- base_path("vendor")
- base_path("node_modules")
- storage_path("logs")

destination:
disks:
- local

encryption:
enabled: false
password: env("BACKUP_ENCRYPTION_PASSWORD")

cleanup:
enabled: true
keep_all_backups_for_days: 7
keep_daily_backups_for_days: 16
keep_weekly_backups_for_weeks: 8
keep_monthly_backups_for_months: 4
keep_yearly_backups_for_years: 2

Usage

Artisan commands:

Run full backup (database + files):
php artisan backup:run

Database only:
php artisan backup:run --only-db

Files only:
php artisan backup:run --only-files

List all backups:
php artisan backup:list

Clean up old backups:
php artisan backup:clean

Programmatic usage (Facade):
Use the Backup facade for manual control inside your app:

Full backup:
Backup::run();

Database only:
Backup::run(onlyDb: true);

Files only:
Backup::run(onlyFiles: true);

Scheduling
Use Laravel’s scheduler to automate backups:

In app/Console/Kernel.php:

protected function schedule(Schedule $schedule)
{
$schedule->command("backup:run")->dailyAt("02:00");
$schedule->command("backup:clean")->weekly();
}

Encryption
To enable AES-256 encryption of backups:

In .env:
BACKUP_ENCRYPTION_ENABLED=true
BACKUP_ENCRYPTION_PASSWORD=your-secure-password

In config/backup.php, set encryption.enabled to true and password from env.

To decrypt a backup programmatically, use the provided helper in your own tooling layer (for commercial use you can expose a command or internal API around it).

Backup Structure
Created backup ZIP files follow this structure:

databases/

mysql-YYYY-MM-DD-HH-MM-SS.sql

pgsql-YYYY-MM-DD-HH-MM-SS.sql

sqlite-YYYY-MM-DD-HH-MM-SS.sqlite

sqlsrv-YYYY-MM-DD-HH-MM-SS.sql

files/

YourAppName/

app/

config/

database/

public/

resources/

routes/

etc.

manifest.json
Contains metadata like backup name, timestamps, database connections, file counts, etc.

Testing
Run the test suite:

composer test

(Optional) With coverage:
composer test-coverage

Commercial Use Notes

Licensed under MIT, which allows commercial use, modification, distribution, and private use without copyleft obligations.

For SaaS or closed-source apps, you can bundle this package without open-sourcing your own application code.

For white-label or client work, include license and copyright notices as required.

Security

Do not commit any real database credentials or encryption passwords.

Use environment variables for all sensitive data:

Database credentials

Encryption password

Limit access to backup files (disk permissions, private S3 buckets, etc.).

For audits, log backup events via Laravel logging.

Folder and Git Ignore Best Practices
For the package repository:

Do not commit:

vendor/

node_modules/

.env files

IDE/settings files (.idea, .vscode, etc.)

coverage and build artifacts

Recommended to keep:

src/

tests/

config/

composer.json

phpunit.xml

README

LICENSE

CHANGELOG

CONTRIBUTING (optional but good for commercial/open packages)

License
MIT License. Suitable for both open-source and commercial use.

Support and Issues

Use the repository issue tracker for bug reports and feature requests.

For commercial support, provide email or private channel as needed.