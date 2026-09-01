<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;
use App\Services\TelegramService;

class BackupController extends Controller
{

    public function __construct()
    {
    }


    /**
     * گرفتن بکاپ از کل دیتابیس
     */
    public function createBackup()
    {
        try {
            // Create symbolic link if not exists
            if (!file_exists(public_path('storage'))) {
                \Artisan::call('storage:link');
            }
            // Create symbolic link if not exists
            if (!file_exists(public_path('storage'))) {
                \Artisan::call('storage:link');
            }
            // قبل از ایجاد فایل backup
            $backupPath = storage_path('app/public/backups');

            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0775, true);

                // تنظیم دسترسی‌ها در سیستم‌عامل‌های یونیکس
                if (PHP_OS !== 'WINNT') {
                    chmod($backupPath, 0775);

                    // اگر در محیط لینوکس هستید و می‌خواهید مالکیت را هم تغییر دهید
                    // $user = get_current_user();
                    // chown($backupPath, $user);
                }
            }

            // نام فایل بکاپ با تاریخ و زمان
            $filename = 'backup_' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';
            $filePath = storage_path('app/public/backups/' . $filename);

            // استفاده از کلاس Process برای اجرای دستور mysqldump
            $host = config('database.connections.mysql.host');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $database = config('database.connections.mysql.database');

            // ساخت دستور mysqldump
            $command = [
                'mysqldump',
                '--opt',
                '--databases',
                $database,
                '-h', $host,
                '-u', $username,
                '-p' . $password,
                '--result-file=' . $filePath
            ];

            // اجرای دستور با استفاده از Process
            $process = new \Symfony\Component\Process\Process($command);
            $process->setTimeout(300); // 5 دقیقه تایم‌اوت
            $process->run();

            // بررسی نتیجه اجرای دستور
            if (!$process->isSuccessful()) {
                \Log::error('MySQL Dump Error: ' . $process->getErrorOutput());
                throw new Exception('خطا در ایجاد فایل بکاپ: ' . $process->getErrorOutput());
            }

            // بررسی اندازه فایل
            if (file_exists($filePath) && filesize($filePath) > 0) {
                $this->ensureBackupCorsHtaccess();

                return response()->json([
                    'status' => 'success',
                    'message' => 'فایل بکاپ با موفقیت ایجاد شد',
                    'filename' => $filename,
                    'url' => $this->backupDownloadUrl($filename),
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'خطا در ایجاد فایل بکاپ: فایل خالی است'
            ], 500);

       } catch (\Throwable $th) {
            \Log::error("خطا در ایجاد بکاپ: " . $th->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'خطای سرور: ' . $th->getMessage()
            ], 500);
        }
    }

    public function downloadBackup(Request $request, ?string $filename = null)
    {
        $filename = basename(rawurldecode((string) ($filename ?: $request->query('filename', ''))));

        // Accept both backup_YYYY-mm-dd_HH-ii-ss.sql and backup_php_....sql
        if (!preg_match('/^backup_(?:php_)?[\d\-_]+\.sql$/', $filename)) {
            return response()->json(['message' => 'نام فایل نامعتبر است'], 400);
        }

        $filePath = storage_path('app/public/backups/' . $filename);
        if (!file_exists($filePath)) {
            return response()->json(['message' => 'فایل پشتیبان یافت نشد'], 404);
        }

        return response()->download($filePath, $filename, $this->backupDownloadHeaders($filename));
    }

    public function createBackupAndReturnZipFile()
    {
        try {
            // Create symbolic link if not exists
            if (!file_exists(public_path('storage'))) {
                \Artisan::call('storage:link');
            }

            // قبل از ایجاد فایل backup
            $backupPath = storage_path('app/public/backups');

            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0775, true);

                // تنظیم دسترسی‌ها در سیستم‌عامل‌های یونیکس
                if (PHP_OS !== 'WINNT') {
                    chmod($backupPath, 0775);
                }
            }

            // نام فایل بکاپ با تاریخ و زمان
            $filename = 'backup_' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';
            $filePath = storage_path('app/public/backups/' . $filename);

            // روش اول: استفاده از PHP برای ایجاد بکاپ (بدون نیاز به mysqldump)
            try {
                // باز کردن فایل برای نوشتن
                $file = fopen($filePath, 'w');
                if (!$file) {
                    throw new Exception('خطا در باز کردن فایل برای نوشتن');
                }

                // اطلاعات دیتابیس
                $database = config('database.connections.mysql.database');

                // نوشتن هدر فایل SQL
                fwrite($file, "-- SQL Dump generated by Laravel PHP\n");
                fwrite($file, "-- Date: " . Carbon::now()->format('Y-m-d H:i:s') . "\n");
                fwrite($file, "-- Database: `" . $database . "`\n\n");
                fwrite($file, "SET FOREIGN_KEY_CHECKS=0;\n\n");

                // گرفتن لیست تمام جداول
                $tables = DB::select('SHOW TABLES');
                $dbName = 'Tables_in_' . $database;

                foreach ($tables as $table) {
                    $tableName = $table->$dbName;

                    // ساختار جدول
                    fwrite($file, "\n-- --------------------------------------------------------\n");
                    fwrite($file, "\n-- Table structure for table `" . $tableName . "`\n\n");

                    // دستور DROP TABLE
                    fwrite($file, "DROP TABLE IF EXISTS `" . $tableName . "`;\n");

                    // گرفتن ساختار جدول
                    $createTable = DB::select('SHOW CREATE TABLE ' . $tableName);
                    $createTableSql = $createTable[0]->{'Create Table'};
                    fwrite($file, $createTableSql . ";\n\n");

                    // داده‌های جدول
                    $rows = DB::table($tableName)->get();

                    if (count($rows) > 0) {
                        fwrite($file, "-- Dumping data for table `" . $tableName . "`\n");

                        // برای هر 100 ردیف یک دستور INSERT جداگانه ایجاد می‌کنیم
                        $chunks = array_chunk($rows->toArray(), 100);

                        foreach ($chunks as $chunk) {
                            fwrite($file, "INSERT INTO `" . $tableName . "` VALUES\n");

                            $rowCount = count($chunk);
                            $i = 0;

                            foreach ($chunk as $row) {
                                $values = [];

                                foreach ((array)$row as $value) {
                                    if (is_null($value)) {
                                        $values[] = "NULL";
                                    } elseif (is_numeric($value)) {
                                        $values[] = $value;
                                    } else {
                                        $values[] = "'" . addslashes($value) . "'";
                                    }
                                }

                                $i++;
                                if ($i == $rowCount) {
                                    fwrite($file, "(" . implode(", ", $values) . ");\n\n");
                                } else {
                                    fwrite($file, "(" . implode(", ", $values) . "),\n");
                                }
                            }
                        }
                    }
                }

                // پایان فایل SQL
                fwrite($file, "\nSET FOREIGN_KEY_CHECKS=1;\n");

                // بستن فایل
                fclose($file);

                \Log::info('بکاپ با استفاده از PHP ایجاد شد: ' . $filePath);
            } catch (\Exception $e) {
                \Log::error('خطا در ایجاد بکاپ با PHP: ' . $e->getMessage());

                // اگر روش PHP با خطا مواجه شد، از روش mysqldump استفاده می‌کنیم
                // استفاده از کلاس Process برای اجرای دستور mysqldump
                $host = config('database.connections.mysql.host');
                $username = config('database.connections.mysql.username');
                $password = config('database.connections.mysql.password');
                $database = config('database.connections.mysql.database');

                // ساخت دستور mysqldump
                $command = sprintf(
                    'mysqldump -h %s -u %s -p%s --opt --databases %s > %s',
                    escapeshellarg($host),
                    escapeshellarg($username),
                    escapeshellarg($password),
                    escapeshellarg($database),
                    escapeshellarg($filePath)
                );

                // اجرای دستور
                $output = [];
                $returnVar = 0;
                exec($command . ' 2>&1', $output, $returnVar);

                if ($returnVar !== 0) {
                    \Log::error('MySQL Dump Error: ' . implode("\n", $output));
                    throw new Exception('خطا در ایجاد فایل بکاپ: ' . implode("\n", $output));
                }

                \Log::info('بکاپ با استفاده از mysqldump ایجاد شد: ' . $filePath);
            }

            // بررسی اندازه فایل
            if (file_exists($filePath) && filesize($filePath) > 0) {
                // ایجاد فایل زیپ
                $zipPath = storage_path('app/public/backups/' . $filename . '.zip');
                $zip = new \ZipArchive();
                if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                    $zip->addFile($filePath, $filename);
                    $zip->close();

                    // پاک کردن فایل SQL اصلی بعد از ایجاد فایل زیپ
                    File::delete($filePath);

                    \Log::info('فایل زیپ ایجاد شد: ' . $zipPath);
                    return $zipPath; // برگرداندن مسیر فایل به جای محتوای آن
                } else {
                    \Log::error('خطا در ایجاد فایل زیپ');
                    return $filePath; // اگر ایجاد زیپ با خطا مواجه شد، فایل SQL را برمی‌گردانیم
                }
            }

            \Log::error('فایل بکاپ ایجاد شد اما خالی است');
            return null;

        } catch (\Throwable $th) {
            \Log::error("خطا در ایجاد بکاپ: " . $th->getMessage());
            return null;
        }
    }


    /**
     * بازیابی اطلاعات از فایل بکاپ (.sql یا .sql.zip)
     */
    public function restoreBackup(Request $request)
    {
        try {
            $backupUrl = $request->input('backup_url');
            if (!$backupUrl && !$request->hasFile('backup_file')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'فایل بکاپ یافت نشد'
                ], 400);
            }

            if (!file_exists(public_path('storage'))) {
                \Artisan::call('storage:link');
            }

            $tempPath = storage_path('app/public/backups/temp');
            if (!File::exists($tempPath)) {
                File::makeDirectory($tempPath, 0775, true);
            }

            $cleanupFiles = [];

            // Prepare the SQL file FIRST — never drop tables until the file is ready.
            if ($backupUrl) {
                $path = parse_url($backupUrl, PHP_URL_PATH);
                $filename = basename($path);
                if (!$this->isAllowedBackupFilename($filename)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'نام فایل بکاپ نامعتبر است'
                    ], 400);
                }

                $sourcePath = storage_path('app/public/backups/' . $filename);
                if (!file_exists($sourcePath) || filesize($sourcePath) <= 0) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'فایل بکاپ در مسیر مشخص شده یافت نشد'
                    ], 404);
                }

                $workFile = $tempPath . '/' . $filename;
                copy($sourcePath, $workFile);
                $cleanupFiles[] = $workFile;
            } else {
                $file = $request->file('backup_file');
                if (!$file || !$file->isValid()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'فایل آپلود شده معتبر نیست'
                    ], 400);
                }

                $originalName = strtolower((string) $file->getClientOriginalName());
                if (!$this->isAllowedBackupExtension($originalName)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'فقط فایل SQL یا ZIP بکاپ مجاز است'
                    ], 400);
                }

                if ($file->getSize() <= 0) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'فایل بکاپ خالی است'
                    ], 400);
                }

                $ext = str_ends_with($originalName, '.sql.zip') || str_ends_with($originalName, '.zip')
                    ? '.sql.zip'
                    : '.sql';
                $filename = 'restore_' . time() . $ext;
                $file->move($tempPath, $filename);
                $workFile = $tempPath . '/' . $filename;
                $cleanupFiles[] = $workFile;
            }

            $sqlFile = $this->resolveSqlFileFromBackup($workFile, $tempPath, $cleanupFiles);
            if ($sqlFile === null || !file_exists($sqlFile) || filesize($sqlFile) <= 0) {
                $this->cleanupFiles($cleanupFiles);
                return response()->json([
                    'status' => 'error',
                    'message' => 'فایل SQL داخل بکاپ یافت نشد یا خالی است'
                ], 400);
            }

            // Drop existing tables only after the restore file is validated.
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            $tables = DB::select('SHOW TABLES');
            $dbName = 'Tables_in_' . config('database.connections.mysql.database');
            foreach ($tables as $table) {
                DB::statement('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table->$dbName) . '`');
            }
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            $command = sprintf(
                'mysql -h %s -u %s -p%s %s < %s',
                escapeshellarg(config('database.connections.mysql.host')),
                escapeshellarg(config('database.connections.mysql.username')),
                escapeshellarg(config('database.connections.mysql.password')),
                escapeshellarg(config('database.connections.mysql.database')),
                escapeshellarg($sqlFile)
            );

            $output = [];
            $returnVar = 0;
            exec($command . ' 2>&1', $output, $returnVar);

            $this->cleanupFiles($cleanupFiles);

            if ($returnVar !== 0) {
                \Log::error('MySQL Error: ' . implode("\n", $output));
                throw new Exception('خطا در اجرای دستور MySQL: ' . implode("\n", $output));
            }

            return response()->json([
                'status' => 'success',
                'message' => 'بازیابی اطلاعات با موفقیت انجام شد',
            ]);
        } catch (Exception $e) {
            \Log::error('خطا در بازیابی اطلاعات: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function isAllowedBackupFilename(string $filename): bool
    {
        return (bool) preg_match(
            '/^(backup_(?:php_)?[\d\-_]+|restore_\d+)\.(sql|sql\.zip|zip)$/',
            $filename
        );
    }

    private function isAllowedBackupExtension(string $originalName): bool
    {
        return str_ends_with($originalName, '.sql')
            || str_ends_with($originalName, '.sql.zip')
            || str_ends_with($originalName, '.zip');
    }

    /**
     * @param  list<string>  $cleanupFiles
     */
    private function resolveSqlFileFromBackup(string $workFile, string $tempPath, array &$cleanupFiles): ?string
    {
        $lower = strtolower($workFile);
        if (str_ends_with($lower, '.sql') && !str_ends_with($lower, '.sql.zip')) {
            return $workFile;
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new Exception('افزونه ZipArchive روی سرور نصب نیست');
        }

        $zip = new \ZipArchive();
        if ($zip->open($workFile) !== true) {
            return null;
        }

        $sqlEntry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            if (str_ends_with(strtolower($name), '.sql') && !str_ends_with(strtolower($name), '/')) {
                $sqlEntry = $name;
                break;
            }
        }

        if ($sqlEntry === null) {
            $zip->close();
            return null;
        }

        $extractName = 'restore_extracted_' . time() . '.sql';
        $extractPath = $tempPath . '/' . $extractName;
        $stream = $zip->getStream($sqlEntry);
        if ($stream === false) {
            $zip->close();
            return null;
        }

        $out = fopen($extractPath, 'wb');
        if ($out === false) {
            fclose($stream);
            $zip->close();
            return null;
        }

        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
        $zip->close();

        $cleanupFiles[] = $extractPath;

        return $extractPath;
    }

    /**
     * @param  list<string>  $files
     */
    private function cleanupFiles(array $files): void
    {
        foreach (array_unique($files) as $file) {
            if (is_string($file) && $file !== '' && file_exists($file)) {
                File::delete($file);
            }
        }
    }


    /**
     * تست اتصال به دیتابیس
     */
    public function testDatabaseConnection()
    {
        try {
            // تست اتصال به دیتابیس
            DB::connection()->getPdo();

            // بررسی دسترسی‌های کاربر دیتابیس
            $hasPrivileges = DB::select("SHOW GRANTS FOR CURRENT_USER()");

            // اطلاعات دیتابیس
            $dbInfo = [
                'connection' => config('database.default'),
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'username' => config('database.connections.mysql.username'),
                'version' => DB::select('SELECT VERSION() as version')[0]->version,
                'tables_count' => count(DB::select('SHOW TABLES')),
                'privileges' => $hasPrivileges
            ];

            // بررسی وجود دستور mysqldump
            $output = [];
            $returnVar = 0;
            exec('which mysqldump 2>&1', $output, $returnVar);
            $mysqldumpPath = ($returnVar === 0) ? $output[0] : 'نصب نشده';

            return response()->json([
                'status' => 'success',
                'message' => 'اتصال به دیتابیس برقرار است',
                'database_info' => $dbInfo,
                'mysqldump_path' => $mysqldumpPath
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در اتصال به دیتابیس: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تست دستور mysqldump
     */
    public function testMysqldump()
    {
        try {
            // ایجاد دایرکتوری temp اگر وجود نداشته باشد
            $tempPath = storage_path('app/public/backups/temp');
            if (!File::exists($tempPath)) {
                File::makeDirectory($tempPath, 0775, true);
            }

            // نام فایل تست
            $filename = 'test_dump_' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';
            $filePath = $tempPath . '/' . $filename;

            // دستور mysqldump برای تست - فقط ساختار یک جدول کوچک
            $command = sprintf(
                'mysqldump -h %s -u %s -p%s --no-data %s users --result-file=%s 2>&1',
                escapeshellarg(config('database.connections.mysql.host')),
                escapeshellarg(config('database.connections.mysql.username')),
                escapeshellarg(config('database.connections.mysql.password')),
                escapeshellarg(config('database.connections.mysql.database')),
                escapeshellarg($filePath)
            );

            // اجرای دستور و بررسی خطا
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            // بررسی نتیجه اجرای دستور
            if ($returnVar !== 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'خطا در اجرای دستور mysqldump',
                    'command' => preg_replace('/(-p)([^\\s]+)/', '$1******', $command),
                    'output' => $output,
                    'return_code' => $returnVar
                ], 500);
            }

            // بررسی اندازه فایل
            if (file_exists($filePath)) {
                $fileSize = filesize($filePath);
                $fileContent = '';

                // اگر فایل کوچک است، محتوای آن را نمایش می‌دهیم
                if ($fileSize < 10240) { // کمتر از 10 کیلوبایت
                    $fileContent = file_get_contents($filePath);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'تست دستور mysqldump با موفقیت انجام شد',
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'file_content' => $fileContent,
                    'command' => preg_replace('/(-p)([^\\s]+)/', '$1******', $command)
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'فایل ایجاد نشد',
                'command' => preg_replace('/(-p)([^\\s]+)/', '$1******', $command)
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در اجرای تست: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * گرفتن بکاپ از کل دیتابیس با استفاده از PHP
     */
    public function createBackupWithPHP()
    {
        try {
            // Create symbolic link if not exists
            if (!file_exists(public_path('storage'))) {
                \Artisan::call('storage:link');
            }

            // قبل از ایجاد فایل backup
            $backupPath = storage_path('app/public/backups');

            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0775, true);
            }

            // نام فایل بکاپ با تاریخ و زمان
            $filename = 'backup_php_' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';
            $filePath = storage_path('app/public/backups/' . $filename);

            // باز کردن فایل برای نوشتن
            $file = fopen($filePath, 'w');

            // اطلاعات دیتابیس
            $database = config('database.connections.mysql.database');

            // نوشتن هدر فایل SQL
            fwrite($file, "-- SQL Dump generated by Laravel PHP\n");
            fwrite($file, "-- Date: " . Carbon::now()->format('Y-m-d H:i:s') . "\n");
            fwrite($file, "-- Database: `" . $database . "`\n\n");

            // گرفتن لیست تمام جداول
            $tables = DB::select('SHOW TABLES');
            $dbName = 'Tables_in_' . $database;

            foreach ($tables as $table) {
                $tableName = $table->$dbName;

                // ساختار جدول
                fwrite($file, "\n-- --------------------------------------------------------\n");
                fwrite($file, "\n-- Table structure for table `" . $tableName . "`\n\n");

                // دستور DROP TABLE
                fwrite($file, "DROP TABLE IF EXISTS `" . $tableName . "`;\n");

                // گرفتن ساختار جدول
                $createTable = DB::select('SHOW CREATE TABLE ' . $tableName);
                $createTableSql = $createTable[0]->{'Create Table'};
                fwrite($file, $createTableSql . ";\n\n");

                // داده‌های جدول
                $rows = DB::table($tableName)->get();

                if (count($rows) > 0) {
                    fwrite($file, "-- Dumping data for table `" . $tableName . "`\n");
                    fwrite($file, "INSERT INTO `" . $tableName . "` VALUES\n");

                    $rowCount = count($rows);
                    $i = 0;

                    foreach ($rows as $row) {
                        $values = [];

                        foreach ((array)$row as $value) {
                            if (is_null($value)) {
                                $values[] = "NULL";
                            } elseif (is_numeric($value)) {
                                $values[] = $value;
                            } else {
                                $values[] = "'" . addslashes($value) . "'";
                            }
                        }

                        $i++;
                        if ($i == $rowCount) {
                            fwrite($file, "(" . implode(", ", $values) . ");\n\n");
                        } else {
                            fwrite($file, "(" . implode(", ", $values) . "),\n");
                        }
                    }
                }
            }

            // پایان فایل SQL
            fwrite($file, "\nSET FOREIGN_KEY_CHECKS=1;\n");

            // بستن فایل
            fclose($file);

            // بررسی اندازه فایل
            if (file_exists($filePath) && filesize($filePath) > 0) {
                $this->ensureBackupCorsHtaccess();

                return response()->json([
                    'status' => 'success',
                    'message' => 'فایل بکاپ با موفقیت ایجاد شد',
                    'filename' => $filename,
                    'url' => $this->backupDownloadUrl($filename),
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'خطا در ایجاد فایل بکاپ: فایل خالی است'
            ], 500);

        } catch (\Throwable $th) {
            \Log::error("خطا در ایجاد بکاپ با PHP: " . $th->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'خطای سرور: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * HTTP entry for cron/manual backup-to-telegram.
     * Requires ?token= matching BACKUP_CRON_SECRET. Scheduler calls
     * createBackupAndSendToTelegram() directly and does not need this.
     */
    public function createBackupAndSendToTelegramHttp(Request $request)
    {
        $expected = (string) env('BACKUP_CRON_SECRET', '');
        $token = (string) $request->query('token', '');

        if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        $result = $this->createBackupAndSendToTelegram();

        return response()->json([
            'status' => $result ? 'success' : 'error',
        ], $result ? 200 : 500);
    }

    /**
     * ایجاد بکاپ و ارسال به تلگرام
     */
    public function createBackupAndSendToTelegram($adminId = null)
    {
        try {
            $backupFile = $this->createBackupAndReturnZipFile();

            if (!$backupFile || !file_exists($backupFile)) {
                \Log::error('فایل بکاپ ایجاد نشد یا وجود ندارد');
                return false;
            }

            // اگر adminId ارسال نشده باشد، اولین ادمین را پیدا می‌کنیم
            if (!$adminId) {
                $admin = \App\Models\User::where('role', 'admin')->first();
                if ($admin) {
                    $adminId = $admin->account_id;
                } else {
                    \Log::error('هیچ کاربر ادمینی یافت نشد');
                    return false;
                }
            }

            $currentDate = now()->toJalali()->format('Y/m/d');
            $text = "نسخه پشتیبان $currentDate";

            // ارسال فایل به تلگرام
            $telegramService = new TelegramService();
            $result = $telegramService->sendDocumentFile($adminId, $backupFile, $text);

            // پاک کردن فایل بکاپ بعد از ارسال
            File::delete($backupFile);

            return $result;

        } catch (\Throwable $th) {
            \Log::error("خطا در ایجاد و ارسال بکاپ: " . $th->getMessage());
            return false;
        }
    }

    private function backupDownloadUrl(string $filename): string
    {
        return url('/api/downloadBackup?filename=' . rawurlencode($filename));
    }

    /**
     * @return array<string, string>
     */
    private function backupDownloadHeaders(string $filename): array
    {
        return [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
            'Access-Control-Expose-Headers' => 'Content-Disposition, Content-Type, Content-Length',
        ];
    }

    private function ensureBackupCorsHtaccess(): void
    {
        $backupPath = storage_path('app/public/backups');
        if (! File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0775, true);
        }

        $htaccess = $backupPath . '/.htaccess';
        if (File::exists($htaccess)) {
            return;
        }

        File::put($htaccess, <<<'HTACCESS'
<IfModule mod_headers.c>
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, OPTIONS"
    Header always set Access-Control-Allow-Headers "*"
</IfModule>
HTACCESS
        );
    }

}
