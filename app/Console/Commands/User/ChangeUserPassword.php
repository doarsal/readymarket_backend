<?php

namespace App\Console\Commands\User;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ChangeUserPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:change-password
                            {user_id : ID del usuario}
                            {password? : Nueva contraseña (opcional, se pedirá si no se proporciona)}
                            {--force : Cambiar sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cambiar la contraseña de un usuario específico de forma segura';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $password = $this->argument('password');
        $force = $this->option('force');

        // Buscar usuario
        $user = User::find($userId);

        if (!$user) {
            $this->error("Usuario con ID {$userId} no encontrado.");
            return 1;
        }

        $this->info("Usuario encontrado: {$user->full_name} ({$user->email})");

        // Pedir contraseña si no se proporcionó
        if (!$password) {
            $password = $this->secret('Ingresa la nueva contraseña (mínimo 8 caracteres):');
            $confirmPassword = $this->secret('Confirma la nueva contraseña:');

            if ($password !== $confirmPassword) {
                $this->error('Las contraseñas no coinciden.');
                return 1;
            }
        }

        // Validar contraseña
        $validator = Validator::make(['password' => $password], [
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
        ], [
            'password.regex' => 'La contraseña debe contener al menos: 1 mayúscula, 1 minúscula, 1 número y 1 símbolo (@$!%*?&).',
        ]);

        if ($validator->fails()) {
            $this->error('Contraseña no válida:');
            foreach ($validator->errors()->all() as $error) {
                $this->error("- {$error}");
            }
            return 1;
        }

        // Confirmar cambio
        if (!$force && !$this->confirm("¿Estás seguro de cambiar la contraseña del usuario {$user->full_name}?")) {
            $this->info('Operación cancelada.');
            return 0;
        }

        // Cambiar contraseña
        try {
            $user->update([
                'password' => Hash::make($password),
                'password_changed_at' => now(),
                'force_password_change' => false, // Reset flag si existía
                'failed_login_attempts' => 0, // Reset intentos fallidos
                'locked_until' => null, // Desbloquear cuenta si estaba bloqueada
            ]);

            $this->info('✅ Contraseña cambiada exitosamente.');

            // Hacer usuario admin si es el ID 1
            if ($userId == 1 && $user->role !== 'admin') {
                $user->update([
                    'role' => 'admin',
                    'is_active' => true,
                    'is_verified' => true,
                ]);
                $this->info('✅ Usuario establecido como Super Administrator.');
            }

            // Log del cambio
            \Log::info('Contraseña cambiada por comando', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'changed_by' => 'console_command',
                'timestamp' => now()
            ]);

            $this->info("Usuario: {$user->full_name}");
            $this->info("Email: {$user->email}");
            $this->info("Rol: {$user->role}");
            $this->info("Estado: " . ($user->is_active ? 'Activo' : 'Inactivo'));

        } catch (\Exception $e) {
            $this->error("Error al cambiar la contraseña: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
