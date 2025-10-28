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
        // MySQL no soporta índices parciales directamente como PostgreSQL
        // Pero podemos usar un índice único compuesto que incluya deleted_at
        // Los registros eliminados (deleted_at != NULL) tendrán valores únicos de timestamp
        // Los registros activos (deleted_at = NULL) seguirán la restricción única

        Schema::table('microsoft_accounts', function (Blueprint $table) {
            // Eliminar el índice único anterior si existe
            $table->dropUnique('unique_domain_per_user');
        });

        // Crear nuevo índice único que incluye deleted_at
        // Esto permite que dominios eliminados (soft deleted) puedan reutilizarse
        DB::statement('
            CREATE UNIQUE INDEX unique_domain_per_user
            ON microsoft_accounts (user_id, domain_concatenated, deleted_at)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al índice único anterior
        DB::statement('DROP INDEX unique_domain_per_user ON microsoft_accounts');

        Schema::table('microsoft_accounts', function (Blueprint $table) {
            $table->unique(['user_id', 'domain_concatenated'], 'unique_domain_per_user');
        });
    }
};
