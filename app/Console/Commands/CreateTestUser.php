<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test user and return token';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Buscar usuario existente
        $user = User::where('email', 'test@marketplace.com')->first();

        if (!$user) {
            // Crear usuario si no existe
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@marketplace.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now()
            ]);
            $this->info("User created successfully!");
        } else {
            $this->info("User already exists!");
        }

        // Crear token
        $token = $user->createToken('test-token')->plainTextToken;

        $this->info("Email: test@marketplace.com");
        $this->info("Token: " . $token);

        return 0;
    }
}
