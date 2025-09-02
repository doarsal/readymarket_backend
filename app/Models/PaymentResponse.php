<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentResponse extends Model
{
    protected $fillable = [
        'transaction_reference',
        'payment_session_id',
        'order_id',
        'cart_id',
        'user_id',
        'payment_status',
        'gateway',
        'mitec_response',
        'auth_code',
        'folio_cpagos',
        'cd_response',
        'cd_error',
        'nb_error',
        'amount',
        'ds_trans_id',
        'eci',
        'cavv',
        'trans_status',
        'response_code',
        'response_description',
        'card_type',
        'card_last_four',
        'card_name',
        'voucher',
        'voucher_comercio',
        'voucher_cliente',
        'raw_xml_response',
        'mitec_date',
        'mitec_time',
        'ip_address',
        'user_agent',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'mitec_date' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Relaciones
     */
    public function paymentSession(): BelongsTo
    {
        return $this->belongsTo(PaymentSession::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeApproved($query)
    {
        return $query->where('payment_status', 'approved');
    }

    public function scopeError($query)
    {
        return $query->where('payment_status', 'error');
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Helpers
     */
    public function isApproved(): bool
    {
        return $this->payment_status === 'approved';
    }

    public function isError(): bool
    {
        return $this->payment_status === 'error';
    }

    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Crea una respuesta de pago desde datos parseados de MITEC
     */
    public static function createFromMitecResponse(
        array $parsedData,
        ?PaymentSession $paymentSession = null,
        ?string $rawXml = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        // Determinar estado del pago
        $paymentStatus = 'pending';
        if (isset($parsedData['payment_response'])) {
            $paymentStatus = strtolower($parsedData['payment_response']) === 'approved' ? 'approved' : 'error';
        }

        // Extraer fecha y hora de MITEC
        $mitecDate = null;
        if (!empty($parsedData['date']) && !empty($parsedData['time'])) {
            try {
                $mitecDate = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s',
                    $parsedData['date'] . ' ' . $parsedData['time']);
            } catch (\Exception $e) {
                // Si falla el parsing de fecha, usar null
            }
        }

        // Log para debug
        \Log::info('Creando PaymentResponse con datos:', [
            'parsed_data_keys' => array_keys($parsedData),
            'transaction_ref' => $parsedData['r3ds_reference'] ?? $parsedData['payment_folio'] ?? 'NOT_FOUND',
            'payment_response' => $parsedData['payment_response'] ?? 'NOT_FOUND',
            'payment_session_id' => $paymentSession?->id,
            'payment_session_passed' => $paymentSession ? 'YES' : 'NO'
        ]);

        // Si no hay payment session, buscar cart y user por referencia o más reciente
        $cartId = $paymentSession?->cart_id;
        $userId = $paymentSession?->user_id;
        $amount = $parsedData['amount'] ?? null;

        if (!$paymentSession) {
            // Extraer posible cart token de la referencia
            $transactionRef = $parsedData['r3ds_reference'] ?? $parsedData['payment_folio'] ?? '';

            // Buscar cart por token en la referencia (si existe)
            $cart = null;
            if (preg_match('/(\w+)_(\w+)$/', $transactionRef, $matches)) {
                // Si la referencia tiene formato TOKEN_SUFFIX, usar TOKEN
                $possibleToken = $matches[1];
                $cart = \App\Models\Cart::where('cart_token', $possibleToken)
                    ->orWhere('cart_token', 'LIKE', $possibleToken . '%')
                    ->first();
            }

            // Si no encontró por token, buscar el carrito más reciente
            if (!$cart) {
                $cart = \App\Models\Cart::where('status', 'active')
                    ->where('created_at', '>=', now()->subHours(6))
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            if ($cart) {
                $cartId = $cart->id;
                $userId = $cart->user_id;
                // Obtener amount del carrito si no viene de MITEC
                if (!$amount) {
                    $amount = $cart->total_amount;
                }
                \Log::info('Datos del carrito encontrados como fallback', [
                    'cart_id' => $cartId,
                    'user_id' => $userId,
                    'cart_token' => $cart->cart_token ?? 'N/A',
                    'amount_from_cart' => $amount,
                    'metodo_busqueda' => $transactionRef ? 'por_referencia' : 'mas_reciente'
                ]);
            }
        } else {
            // Si tenemos PaymentSession pero no amount, intentar obtenerlo del carrito
            if (!$amount && $paymentSession->cart_id) {
                $cart = \App\Models\Cart::find($paymentSession->cart_id);
                if ($cart) {
                    $amount = $cart->total_amount;
                    \Log::info('Amount obtenido del carrito via PaymentSession', [
                        'cart_id' => $cart->id,
                        'amount' => $amount
                    ]);
                }
            }
        }

        return self::create([
            'transaction_reference' => $parsedData['r3ds_reference'] ?? $parsedData['payment_folio'] ?? null,
            'payment_session_id' => $paymentSession?->id,
            'cart_id' => $cartId,
            'user_id' => $userId,
            'payment_status' => $paymentStatus,
            'gateway' => 'mitec',

            // Datos principales MITEC
            'mitec_response' => $parsedData['payment_response'] ?? null,
            'auth_code' => $parsedData['payment_auth'] ?? null,
            'folio_cpagos' => $parsedData['payment_folio'] ?? null,
            'cd_response' => $parsedData['cd_response'] ?? null,
            'cd_error' => $parsedData['cd_error'] ?? null,
            'nb_error' => $parsedData['nb_error'] ?? null,
            'amount' => $amount, // Usar amount calculado (del carrito o MITEC)

            // Datos 3DS
            'ds_trans_id' => $parsedData['r3ds_dsTransId'] ?? null,
            'eci' => $parsedData['r3ds_eci'] ?? null,
            'cavv' => $parsedData['r3ds_cavv'] ?? null,
            'trans_status' => $parsedData['r3ds_transStatus'] ?? null,
            'response_code' => $parsedData['r3ds_responseCode'] ?? null,
            'response_description' => $parsedData['r3ds_responseDescription'] ?? null,

            // Datos de tarjeta
            'card_type' => $parsedData['cc_type'] ?? null,
            'card_last_four' => $parsedData['cc_number'] ?? null,
            'card_name' => $parsedData['cc_name'] ?? null,

            // Vouchers
            'voucher' => $parsedData['voucher'] ?? null,
            'voucher_comercio' => $parsedData['voucher_comercio'] ?? null,
            'voucher_cliente' => $parsedData['voucher_cliente'] ?? null,

            // Datos de auditoría
            'raw_xml_response' => $rawXml,
            'mitec_date' => $mitecDate,
            'mitec_time' => $parsedData['time'] ?? null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => [
                'branch' => $parsedData['branch'] ?? null,
                'auth_bancaria' => $parsedData['auth_bancaria'] ?? null,
                'protocolo' => $parsedData['protocolo'] ?? null,
                'version' => $parsedData['version'] ?? null,
                'friendly_response' => $parsedData['friendly_response'] ?? null,
                'all_parsed_data' => $parsedData // Guardar todo para debug
            ]
        ]);
    }
}
