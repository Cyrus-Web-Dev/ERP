<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    private const DIR = 'backups';

    public function index()
    {
        $files = collect(Storage::disk('local')->files(self::DIR))
            ->filter(fn ($f) => str_ends_with($f, '.sql'))
            ->map(fn ($f) => [
                'nama' => basename($f),
                'ukuran' => $this->formatUkuran(Storage::disk('local')->size($f)),
                'dibuat' => Storage::disk('local')->lastModified($f),
            ])
            ->sortByDesc('dibuat')
            ->values();

        return view('backup.index', ['files' => $files]);
    }

    /**
     * Setara backupDatabase() di backup.php lama: dump manual per tabel,
     * bukan shell_exec/mysqldump/pg_dump, supaya tetap jalan di hosting
     * yang tidak mengizinkan exec(). Mendukung MySQL & PostgreSQL.
     */
    public function store(Request $request)
    {
        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();

        $sql = "-- Database: {$database} ({$driver})\n";
        $sql .= '-- Backup Date: '.now()->format('Y-m-d H:i:s')."\n\n";

        $sql .= $driver === 'pgsql'
            ? $this->dumpPgsql()
            : $this->dumpMysql();

        $filename = 'backup_'.now()->format('Y-m-d_H-i-s').'.sql';
        Storage::disk('local')->put(self::DIR.'/'.$filename, $sql);

        ActivityLog::catat('backup', 'BACKUP', 'Backup Database', "Membuat backup database: {$filename}");

        return redirect()->route('backup.index')->with('success', "Backup database berhasil! File: {$filename}");
    }

    /**
     * MySQL: dump penuh (struktur tabel + data), sama seperti versi lama.
     */
    private function dumpMysql(): string
    {
        $sql = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tables = collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0]);

        foreach ($tables as $table) {
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

            $create = DB::select("SHOW CREATE TABLE `{$table}`")[0];
            $sql .= ($create->{'Create Table'} ?? array_values((array) $create)[1]).";\n\n";

            $sql .= $this->dumpDataInsert($table, fn ($col) => "`{$col}`");
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        return $sql;
    }

    /**
     * PostgreSQL: dump DATA SAJA (bukan struktur). Merekonstruksi ulang
     * DDL Postgres secara manual (tipe kolom, sequence, index, constraint)
     * berisiko meleset tanpa pg_dump - jadi pendekatannya: jalankan
     * `php artisan migrate` dulu di database tujuan untuk membuat struktur
     * tabelnya, baru import file ini untuk mengisi datanya.
     */
    private function dumpPgsql(): string
    {
        $sql = "-- CATATAN: file ini hanya berisi DATA, bukan struktur tabel.\n";
        $sql .= "-- Jalankan 'php artisan migrate' di database tujuan lebih dulu,\n";
        $sql .= "-- baru import file ini untuk mengisi datanya.\n\n";
        $sql .= "SET session_replication_role = 'replica';\n\n";

        $tables = collect(DB::select("
            SELECT tablename FROM pg_tables
            WHERE schemaname = 'public' AND tablename != 'migrations'
        "))->map(fn ($row) => $row->tablename);

        foreach ($tables as $table) {
            $sql .= $this->dumpDataInsert($table, fn ($col) => "\"{$col}\"");
        }

        $sql .= "\nSET session_replication_role = 'origin';\n";

        return $sql;
    }

    private function dumpDataInsert(string $table, \Closure $quoteColumn): string
    {
        $rows = DB::table($table)->get();

        if ($rows->isEmpty()) {
            return '';
        }

        $kolom = collect((array) $rows->first())->keys()->map($quoteColumn)->implode(',');
        $namaTabelQuoted = $quoteColumn($table);

        $sql = "INSERT INTO {$namaTabelQuoted} ({$kolom}) VALUES\n";

        $baris = $rows->map(function ($row) {
            $values = collect((array) $row)->map(function ($value) {
                if ($value === null) {
                    return 'NULL';
                }

                return "'".str_replace(["\\", "'"], ["\\\\", "''"], (string) $value)."'";
            });

            return '('.$values->implode(',').')';
        });

        return $sql.$baris->implode(",\n").";\n\n";
    }

    public function download(string $filename)
    {
        $filename = basename($filename);
        $path = self::DIR.'/'.$filename;

        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql' || ! Storage::disk('local')->exists($path)) {
            abort(404, 'File backup tidak ditemukan.');
        }

        return Storage::disk('local')->download($path);
    }

    public function destroy(string $filename)
    {
        $filename = basename($filename);
        $path = self::DIR.'/'.$filename;

        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql' || ! Storage::disk('local')->exists($path)) {
            return back()->with('error', 'File backup tidak ditemukan!');
        }

        Storage::disk('local')->delete($path);

        ActivityLog::catat('backup', 'DELETE', 'Hapus Backup', "Menghapus file backup: {$filename}");

        return back()->with('success', "File backup '{$filename}' berhasil dihapus!");
    }

    private function formatUkuran(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
