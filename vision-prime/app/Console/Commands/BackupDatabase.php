<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupDatabase extends Command
{
    protected $signature = 'platform:backup-db {--keep=7 : تعداد نسخه‌های نگهداری‌شده}';

    protected $description = 'بکاپ دورهای پایگاه داده به storage/backups (نگهداری N نسخهٔ آخر)';

    public function handle(): int
    {
        $dir = storage_path('backups');
        File::ensureDirectoryExists($dir);

        $filename = 'backup-'.now()->format('Y-m-d-H-i-s').'.sqlite';
        $target = $dir.'/'.$filename;

        $dbPath = config('database.connections.'.config('database.default').'.database');

        if (is_string($dbPath) && File::exists($dbPath) && $dbPath !== ':memory:') {
            File::copy($dbPath, $target);
        } else {
            // محیط تست (sqlite :memory:) — dump جداول به فایل
            $connection = DB::connection();
            $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $lines = [];
            foreach ($tables as $table) {
                $name = $table->name;
                $lines[] = "DROP TABLE IF EXISTS \"{$name}\";";
                $create = $connection->selectOne('SELECT sql FROM sqlite_master WHERE name = ?', [$name]);
                if ($create !== null && $create->sql !== null) {
                    $lines[] = $create->sql.';';
                }
                foreach ($connection->table($name)->get() as $row) {
                    $json = json_encode((array) $row, JSON_UNESCAPED_UNICODE);
                    $lines[] = "INSERT INTO \"{$name}\" VALUES (".implode(',', array_map(fn ($v) => is_numeric($v) ? $v : "'".addslashes((string) $v)."'", array_values((array) $row))).');';
                }
            }
            File::put($target, implode("\n", $lines));
        }

        $this->info("بکاپ ساخته شد: {$filename} (".number_format(File::size($dir.'/'.$filename) / 1024, 1).' KB)');

        // نگهداری فقط N نسخهٔ آخر
        $keep = (int) $this->option('keep');
        $backups = collect(File::files($dir))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.sqlite'))
            ->sortByDesc(fn ($file): int => $file->getMTime())
            ->values();

        if ($backups->count() > $keep) {
            $toDelete = $backups->slice($keep);
            foreach ($toDelete as $file) {
                File::delete($file->getPathname());
                $this->warn("حذف بکاپ قدیمی: {$file->getFilename()}");
            }
        }

        return self::SUCCESS;
    }
}
