<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrar datos del campo 'name' a 'first_name' si está vacío
        DB::statement("
            UPDATE users
            SET first_name = CASE
                WHEN first_name IS NULL OR first_name = '' THEN
                    SUBSTRING_INDEX(name, ' ', 1)
                ELSE first_name
            END,
            last_name = CASE
                WHEN last_name IS NULL OR last_name = '' THEN
                    CASE
                        WHEN LOCATE(' ', name) > 0 THEN
                            SUBSTRING(name, LOCATE(' ', name) + 1)
                        ELSE ''
                    END
                ELSE last_name
            END
            WHERE name IS NOT NULL AND name != ''
        ");

        // Eliminar el campo 'name' ya que es redundante
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Agregar de vuelta el campo 'name'
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id');
        });

        // Reconstruir el campo 'name' a partir de first_name y last_name
        DB::statement("
            UPDATE users
            SET name = TRIM(CONCAT(IFNULL(first_name, ''), ' ', IFNULL(last_name, '')))
            WHERE first_name IS NOT NULL OR last_name IS NOT NULL
        ");
    }
};
