<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ToggleOTPVerification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:toggle {status? : Enable (true) or disable (false) OTP verification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable or disable OTP verification for user registration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $status = $this->argument('status');

        if ($status === null) {
            // Show current status
            $currentStatus = config('otp.enabled') ? 'enabled' : 'disabled';
            $this->info("OTP verification is currently: {$currentStatus}");

            $choice = $this->choice(
                'Would you like to change it?',
                ['enable', 'disable', 'cancel'],
                'cancel'
            );

            if ($choice === 'cancel') {
                $this->info('No changes made.');
                return 0;
            }

            $status = $choice === 'enable' ? 'true' : 'false';
        }

        // Normalize status
        $enable = in_array(strtolower($status), ['true', '1', 'yes', 'enable', 'on']);
        $statusText = $enable ? 'true' : 'false';

        // Update .env file
        $this->updateEnvFile('OTP_VERIFICATION_ENABLED', $statusText);

        // Clear config cache
        $this->call('config:clear');

        $action = $enable ? 'enabled' : 'disabled';
        $this->info("OTP verification has been {$action}.");

        if (!$enable) {
            $this->warn('⚠️  OTP verification is now disabled. New users will be auto-verified during registration.');
            $this->warn('⚠️  Existing unverified users can login without OTP verification.');
        } else {
            $this->info('✅ OTP verification is now enabled. New users must verify via OTP before login.');
        }

        return 0;
    }

    /**
     * Update .env file with new value
     */
    private function updateEnvFile(string $key, string $value): void
    {
        $envFile = base_path('.env');

        if (!file_exists($envFile)) {
            $this->error('.env file not found!');
            return;
        }

        $envContent = file_get_contents($envFile);
        $keyPattern = "/^{$key}=.*/m";

        if (preg_match($keyPattern, $envContent)) {
            // Key exists, update it
            $envContent = preg_replace($keyPattern, "{$key}={$value}", $envContent);
        } else {
            // Key doesn't exist, add it
            $envContent .= "\n{$key}={$value}\n";
        }

        file_put_contents($envFile, $envContent);
    }
}
