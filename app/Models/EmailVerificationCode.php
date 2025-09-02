<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmailVerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'used',
        'ip_address'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean'
    ];

    /**
     * Generate a new verification code
     */
    public static function generateCode(string $email, string $ipAddress = null): string
    {
        // Delete any existing unused codes for this email
        self::where('email', $email)
            ->where('used', false)
            ->delete();

        // Generate 6-digit code
        $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Create new code
        self::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(15), // 15 minutes expiry
            'ip_address' => $ipAddress
        ]);

        return $code;
    }

    /**
     * Verify a code
     */
    public static function verifyCode(string $email, string $code): bool
    {
        $verification = self::where('email', $email)
            ->where('code', $code)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($verification) {
            $verification->update(['used' => true]);
            return true;
        }

        return false;
    }

    /**
     * Check if code is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
