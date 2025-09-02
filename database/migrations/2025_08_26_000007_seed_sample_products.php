<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Esta migración estaba vacía y causaba problemas
        // No hacemos nada aquí para evitar errores
    }

    public function down(): void
    {
        // No hay nada que revertir
    }
};
