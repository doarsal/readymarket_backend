<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\EmailVerificationCode;
use App\Services\WhatsAppNotificationService;

class OTPVerificationService
{
    private WhatsAppNotificationService $whatsappService;

    public function __construct(WhatsAppNotificationService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Check if OTP verification is enabled
     */
    public function isOTPEnabled(): bool
    {
        return config('otp.enabled', true);
    }

    /**
     * Check if email channel is enabled
     */
    public function isEmailChannelEnabled(): bool
    {
        return config('otp.channels.email', true);
    }

    /**
     * Check if WhatsApp channel is enabled
     */
    public function isWhatsAppChannelEnabled(): bool
    {
        return config('otp.channels.whatsapp', true);
    }

    /**
     * Get OTP expiration time in minutes
     */
    public function getExpirationMinutes(): int
    {
        return config('otp.expiration_minutes', 10);
    }

    /**
     * Get resend rate limit in seconds
     */
    public function getResendRateLimitSeconds(): int
    {
        return config('otp.resend_rate_limit_seconds', 60);
    }

    /**
     * Generate and send OTP code via email and WhatsApp
     */
    public function sendOTPCode(User $user, string $ip = null): string
    {
        // Check if OTP is enabled
        if (!$this->isOTPEnabled()) {
            Log::info("OTP verification is disabled, skipping OTP send", [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            // If OTP is disabled, mark user as verified immediately
            $user->update([
                'is_verified' => true,
                'email_verified_at' => now()
            ]);

            return 'DISABLED';
        }

        try {
            // Generate new verification code
            $verificationCode = EmailVerificationCode::generateCode($user->email, $ip ?? request()->ip());

            $channelsSent = [];

            // Send welcome email with OTP if email channel is enabled
            if ($this->isEmailChannelEnabled()) {
                $this->sendWelcomeEmailWithOTP($user, $verificationCode->code);
                $channelsSent[] = 'email';
            }

            // Send WhatsApp with OTP if WhatsApp channel is enabled
            if ($this->isWhatsAppChannelEnabled()) {
                $this->sendWhatsAppWithOTP($user, $verificationCode->code);
                $channelsSent[] = 'whatsapp';
            }

            Log::info("OTP sent successfully", [
                'user_id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'channels' => $channelsSent
            ]);

            return $verificationCode->code;

        } catch (\Exception $e) {
            Log::error("Failed to send OTP", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send OTP for returning unverified user (without admin notification)
     */
    public function sendOTPForReturningUser(User $user, ?string $ip = null): string
    {
        // Check if OTP is enabled
        if (!$this->isOTPEnabled()) {
            Log::info("OTP is disabled, marking returning user as verified", [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            // If OTP is disabled, mark user as verified immediately
            $user->update([
                'is_verified' => true,
                'email_verified_at' => \Carbon\Carbon::now(config('app.timezone'))
            ]);

            return 'DISABLED';
        }

        try {
            // Generate new verification code
            $verificationCode = EmailVerificationCode::generateCode($user->email, $ip ?? request()->ip());

            $channelsSent = [];

            // Send email with OTP if email channel is enabled
            if ($this->isEmailChannelEnabled()) {
                $this->sendReturnUserEmailWithOTP($user, $verificationCode->code);
                $channelsSent[] = 'email';
            }

            // Send WhatsApp with OTP if WhatsApp channel is enabled
            if ($this->isWhatsAppChannelEnabled()) {
                $this->sendWhatsAppWithOTP($user, $verificationCode->code);
                $channelsSent[] = 'whatsapp';
            }

            Log::info("OTP sent to returning user (no admin notification)", [
                'user_id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'channels' => $channelsSent
            ]);

            return $verificationCode->code;

        } catch (\Exception $e) {
            Log::error("Failed to send OTP to returning user", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send welcome email with OTP code
     */
    private function sendWelcomeEmailWithOTP(User $user, string $otpCode): void
    {
        try {
            Mail::send('emails.welcome-otp', [
                'user' => $user,
                'otpCode' => $otpCode,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ], function ($message) use ($user) {
                $message->to($user->email)
                       ->subject('🎉 ¡Bienvenido a Readymarket! Verifica tu cuenta');
            });

            Log::info("Welcome email with OTP sent", [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send welcome email with OTP", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send email with OTP code for returning users
     */
    private function sendReturnUserEmailWithOTP(User $user, string $otpCode): void
    {
        try {
            Mail::send('emails.welcome-otp', [
                'user' => $user,
                'otpCode' => $otpCode,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'isReturningUser' => true
            ], function ($message) use ($user) {
                $message->to($user->email)
                       ->subject('🔑 Código de verificación - Readymarket');
            });

            Log::info("Return user email with OTP sent", [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send return user email with OTP", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send WhatsApp message with OTP code
     */
    private function sendWhatsAppWithOTP(User $user, string $otpCode): void
    {
        try {
            $this->whatsappService->sendOTPVerification($user->phone, $otpCode, $user->first_name);

            Log::info("WhatsApp OTP sent", [
                'user_id' => $user->id,
                'phone' => $user->phone
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp OTP", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw here, email is more important
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyOTPCode(User $user, string $code): bool
    {
        // Check if OTP is enabled
        if (!$this->isOTPEnabled()) {
            Log::info("OTP verification is disabled, auto-verifying user", [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            // If OTP is disabled, mark user as verified immediately
            $user->update([
                'is_verified' => true,
                'email_verified_at' => now()
            ]);

            return true;
        }

        try {
            // Get current time in Mexico City timezone
            $now = \Carbon\Carbon::now(config('app.timezone'));

            $verification = EmailVerificationCode::where('email', $user->email)
                ->where('code', $code)
                ->where('expires_at', '>', $now)
                ->where('used', false)
                ->first();

            if (!$verification) {
                Log::warning("Invalid or expired OTP", [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'code' => $code,
                    'current_time' => $now->toDateTimeString(),
                    'timezone' => config('app.timezone')
                ]);
                return false;
            }

            // Mark as used
            $verification->update(['used' => true]);

            // Mark user as verified
            $user->update([
                'is_verified' => true,
                'email_verified_at' => $now
            ]);

            Log::info("OTP verified successfully", [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Error verifying OTP", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Resend OTP code (uses returning user method - no admin notification)
     */
    public function resendOTPCode(User $user): bool
    {
        // Check if OTP is enabled
        if (!$this->isOTPEnabled()) {
            Log::info("OTP verification is disabled, skipping resend", [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            // If OTP is disabled, mark user as verified immediately
            $user->update([
                'is_verified' => true,
                'email_verified_at' => \Carbon\Carbon::now(config('app.timezone'))
            ]);

            return true;
        }

        try {
            if ($user->email_verified_at) {
                Log::warning("User already verified", [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return false;
            }

            // Check rate limiting - configurable wait time
            $rateLimitSeconds = $this->getResendRateLimitSeconds();
            $now = \Carbon\Carbon::now(config('app.timezone'));
            $lastCode = EmailVerificationCode::where('email', $user->email)
                ->where('created_at', '>', $now->subSeconds($rateLimitSeconds))
                ->first();

            if ($lastCode) {
                Log::warning("OTP resend rate limited", [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'rate_limit_seconds' => $rateLimitSeconds
                ]);
                return false;
            }

            // Send new OTP using returning user method (no admin notification)
            $this->sendOTPForReturningUser($user, request()->ip());

            return true;

        } catch (\Exception $e) {
            Log::error("Error resending OTP", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
