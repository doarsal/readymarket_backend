<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PaymentSession extends Model
{
    protected $fillable = [
        'transaction_reference',
        'form_html',
        'mitec_url',
        'user_id',
        'cart_id',
        'billing_information_id',
        'microsoft_account_id',
        'payment_method',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el carrito
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Verifica si la sesión ha expirado
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Limpia sesiones expiradas
     */
    public static function cleanExpired(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }

    /**
     * Crea una nueva sesión de pago con expiración de 10 minutos
     */
    public static function createForPayment(
        string $transactionReference,
        string $formHtml,
        string $mitecUrl,
        ?int $userId = null,
        ?int $cartId = null,
        ?int $billingInformationId = null,
        ?int $microsoftAccountId = null,
        ?string $paymentMethod = null
    ): self {
        // Validar que los datos requeridos no estén vacíos
        if (empty($transactionReference)) {
            throw new \InvalidArgumentException('transaction_reference no puede estar vacío');
        }

        if (empty($formHtml)) {
            throw new \InvalidArgumentException('form_html no puede estar vacío');
        }

        if (empty($mitecUrl)) {
            throw new \InvalidArgumentException('mitec_url no puede estar vacío');
        }

        return self::create([
            'transaction_reference' => $transactionReference,
            'form_html' => $formHtml,
            'mitec_url' => $mitecUrl,
            'user_id' => $userId,
            'cart_id' => $cartId,
            'billing_information_id' => $billingInformationId,
            'microsoft_account_id' => $microsoftAccountId,
            'payment_method' => $paymentMethod,
            'expires_at' => Carbon::now()->addMinutes(10)
        ]);
    }
}
