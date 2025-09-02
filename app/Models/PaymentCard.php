<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'card_token',
        'card_fingerprint',
        'last_four_digits',
        'brand',
        'card_type',
        'expiry_month_encrypted',
        'expiry_year_encrypted',
        'cardholder_name_encrypted',
        'mitec_card_id',
        'mitec_merchant_used',
        'is_default',
        'is_active',
        'created_ip',
        'last_used_ip',
        'last_used_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'expiry_month_encrypted',
        'expiry_year_encrypted',
        'cardholder_name_encrypted',
        'card_token',
        'mitec_card_id',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Generar token único al crear
        static::creating(function ($card) {
            if (empty($card->card_token)) {
                $card->card_token = 'card_' . Str::random(40) . '_' . time();
            }
        });

        // Asegurar que solo una tarjeta sea default por usuario
        static::saving(function ($card) {
            if ($card->is_default && $card->isDirty('is_default')) {
                static::where('user_id', $card->user_id)
                    ->where('id', '!=', $card->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Relación con User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Encriptar datos sensibles
     */
    public function setExpiryMonthEncryptedAttribute($value)
    {
        $this->attributes['expiry_month_encrypted'] = Crypt::encryptString($value);
    }

    public function setExpiryYearEncryptedAttribute($value)
    {
        $this->attributes['expiry_year_encrypted'] = Crypt::encryptString($value);
    }

    public function setCardholderNameEncryptedAttribute($value)
    {
        $this->attributes['cardholder_name_encrypted'] = Crypt::encryptString($value);
    }

    /**
     * Desencriptar datos sensibles
     */
    public function getExpiryMonthAttribute()
    {
        return Crypt::decryptString($this->expiry_month_encrypted);
    }

    public function getExpiryYearAttribute()
    {
        return Crypt::decryptString($this->expiry_year_encrypted);
    }

    public function getCardholderNameAttribute()
    {
        return Crypt::decryptString($this->cardholder_name_encrypted);
    }

    /**
     * Generar huella digital de la tarjeta
     */
    public static function generateFingerprint($cardNumber, $expiryMonth, $expiryYear)
    {
        return hash('sha256', $cardNumber . $expiryMonth . $expiryYear);
    }

    /**
     * Obtener representación segura de la tarjeta
     */
    public function getMaskedCardNumberAttribute()
    {
        return '**** **** **** ' . $this->last_four_digits;
    }

    /**
     * Verificar si la tarjeta está expirada
     */
    public function getIsExpiredAttribute()
    {
        $currentMonth = (int) date('m');
        $currentYear = (int) date('Y');
        $expiryMonth = (int) $this->expiry_month;
        $expiryYear = (int) $this->expiry_year;

        return ($expiryYear < $currentYear) ||
               ($expiryYear == $currentYear && $expiryMonth < $currentMonth);
    }

    /**
     * Verificar si la tarjeta está próxima a vencer (3 meses)
     */
    public function getIsExpiringSoonAttribute()
    {
        if ($this->is_expired) {
            return false;
        }

        $expiryDate = $this->getCardExpiryDate();
        $threeMonthsFromNow = now()->addMonths(3);

        return $expiryDate->lte($threeMonthsFromNow);
    }

    /**
     * Obtener días hasta la expiración
     */
    public function getDaysUntilExpiryAttribute()
    {
        $expiryDate = $this->getCardExpiryDate();
        $today = now()->startOfDay();

        if ($this->is_expired) {
            return $today->diffInDays($expiryDate, false); // Número negativo para expiradas
        }

        return $today->diffInDays($expiryDate);
    }

    /**
     * Obtener fecha completa de expiración
     */
    public function getCardExpiryDate()
    {
        $month = (int) $this->expiry_month;
        $year = (int) $this->expiry_year;

        // Último día del mes de expiración
        return \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
