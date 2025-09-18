<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PasswordResetEmailService;
use App\Models\User;

class TestPasswordResetEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:password-reset-email {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test password reset email sending';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        // Find user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        $this->info("Testing password reset email for user: {$user->first_name} {$user->last_name} ({$user->email})");

        // Generate test token
        $token = \Str::random(64);

        // Test email service
        $emailService = new PasswordResetEmailService();
        $result = $emailService->sendPasswordResetEmail($user, $token);

        if ($result) {
            $this->info('✅ Email sent successfully!');
            $this->info("Check the email: {$user->email}");
            $this->info("Reset URL: " . config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email));
        } else {
            $this->error('❌ Failed to send email. Check the logs for more details.');
        }

        return $result ? 0 : 1;
    }
}
