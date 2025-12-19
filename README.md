# **Laravel Backup Package**

**A production-ready Laravel package for backing up your database and application files.**  
Works with **MySQL, PostgreSQL, SQLite, and SQL Server** using **pure PHP database dumps**, without requiring external CLI tools like `mysqldump` or `pg_dump`.  
Designed for **commercial and open-source projects**.

---

## **✨ Features**

- ✅ Database backups: **MySQL, PostgreSQL, SQLite, SQL Server**
- ✅ File backups with **include & exclude paths**
- ✅ **No external dump tools required** (pure PHP)
- ✅ **AES-256 encryption** support
- ✅ Multiple storage disks (**local, S3, FTP, etc.**)
- ✅ Automatic cleanup with **retention rules**
- ✅ Artisan commands for run, list & clean backups
- ✅ Scheduler-ready (Laravel Scheduler)
- ✅ Fully tested & **PSR-compliant structure**

---

## **📋 Requirements**

- PHP **8.1 or higher**
- Laravel **10.x or 11.x**
- Required PDO extensions:
  - `pdo_mysql`
  - `pdo_pgsql`
  - `pdo_sqlite`
  - `pdo_sqlsrv`

---

## **📦 Installation**

```bash
composer require phpfy/laravel-backup
```

```bash
php artisan vendor:publish --provider="Phpfy\LaravelBackup\LaravelBackupServiceProvider" --tag=backup-config
```

---

## **⚙️ Configuration**

Config file location:

```text
config/backup.php
```

---

## **🚀 Usage**

```bash
php artisan backup:run
php artisan backup:list
php artisan backup:clean
```

---

## **🧪 Testing**

```bash
composer test
```

---

## **📄 License**

MIT License.
