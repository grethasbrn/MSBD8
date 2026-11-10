<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seluruh skema database ditulis dalam SQL murni (folder database/sql) supaya
 * DBA bisa memakai fitur PostgreSQL penuh: ENUM, trigger, fungsi PL/pgSQL, dan view.
 * Migration ini hanya menjalankan file-file tersebut berurutan.
 */
return new class extends Migration
{
    private array $files = [
        '01_schema.sql',
        '02_functions_triggers.sql',
        '03_views.sql',
    ];

    public function up(): void
    {
        foreach ($this->files as $file) {
            DB::unprepared(file_get_contents(database_path("sql/{$file}")));
        }
    }

    public function down(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/99_drop_all.sql')));
    }
};
