<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Campos personales adicionales
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');

            // Campos de seguridad y control
            $table->boolean('is_active')->default(true)->after('avatar');
            $table->boolean('is_verified')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('is_verified');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->string('timezone', 50)->default('UTC')->after('last_login_ip');
            $table->string('locale', 5)->default('en')->after('timezone');

            // Campos de seguridad avanzada
            $table->integer('failed_login_attempts')->default(0)->after('locale');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->boolean('two_factor_enabled')->default(false)->after('locked_until');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');

            // Campos de marketplace específicos
            $table->enum('role', ['admin', 'manager', 'user'])->default('user')->after('two_factor_secret');
            $table->json('permissions')->nullable()->after('role');
            $table->json('preferences')->nullable()->after('permissions');

            // Campos de auditoría
            $table->timestamp('password_changed_at')->nullable()->after('preferences');
            $table->boolean('force_password_change')->default(false)->after('password_changed_at');
            $table->timestamp('terms_accepted_at')->nullable()->after('force_password_change');
            $table->string('created_by_ip', 45)->nullable()->after('terms_accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'avatar',
                'is_active',
                'is_verified',
                'last_login_at',
                'last_login_ip',
                'timezone',
                'locale',
                'failed_login_attempts',
                'locked_until',
                'two_factor_enabled',
                'two_factor_secret',
                'role',
                'permissions',
                'preferences',
                'password_changed_at',
                'force_password_change',
                'terms_accepted_at',
                'created_by_ip'
            ]);
        });
    }
};
