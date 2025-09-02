<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;

/**
 * Servicio para construcción de XML de transacciones MITEC
 * Genera el XML exacto requerido por MITEC 3DS v2
 */
class MitecXmlBuilderService
{
    /**
     * Construye el XML completo para una transacción MITEC
     *
     * @param array $transactionData Datos de la transacción
     * @param array $cardData Datos de la tarjeta
     * @param array $billingData Datos de facturación
     * @return string XML completo sin formato
     */
    public function buildTransactionXml(array $transactionData, array $cardData, array $billingData): string
    {
        // Determinar merchant según tipo de tarjeta
        $merchant = $this->getMerchantByCardType($cardData['card_number']);

        // Generar referencia única si no se proporciona
        $reference = $transactionData['reference'] ?? $this->generateTransactionReference();

        // Configuraciones desde .env (sin config file intermedio)
        $responseUrl = env('MITEC_RESPONSE_URL') . '?token=' . $reference;
        $browserIP = env('MITEC_TEST_IP');
        $cobro = env('MITEC_DEFAULT_COBRO');
        $currency = $transactionData['currency'] ?? env('MITEC_DEFAULT_CURRENCY');

        // Datos de configuración del negocio desde .env
        $businessData = [
            'id_company' => env('MITEC_ID_COMPANY'),
            'id_branch' => env('MITEC_ID_BRANCH'),
            'country' => env('MITEC_COUNTRY'),
            'user' => env('MITEC_BS_USER'),
            'password' => env('MITEC_BS_PWD'),
        ];

        // Construir XML EXACTO como en el frontend que funciona
        $xmlBody = <<<XML
<business>
<bs_idCompany>{$businessData['id_company']}</bs_idCompany>
<bs_idBranch>{$businessData['id_branch']}</bs_idBranch>
<bs_country>{$businessData['country']}</bs_country>
<bs_user>{$businessData['user']}</bs_user>
<bs_pwd>{$businessData['password']}</bs_pwd>
</business>
<transaction>
<tx_merchant>{$merchant}</tx_merchant>
<tx_reference>{$reference}</tx_reference>
<tx_amount>{$transactionData['amount']}</tx_amount>
<tx_currency>{$currency}</tx_currency>
<creditcard>
<cc_name>{$cardData['name']}</cc_name>
<cc_number>{$cardData['card_number']}</cc_number>
<cc_expMonth>{$cardData['exp_month']}</cc_expMonth>
<cc_expYear>{$cardData['exp_year']}</cc_expYear>
<cc_cvv>{$cardData['cvv']}</cc_cvv>
</creditcard>
<billing>
<bl_billingPhone>{$billingData['phone']}</bl_billingPhone>
<bl_billingEmail>{$billingData['email']}</bl_billingEmail>
</billing>
<tx_urlResponse>{$responseUrl}</tx_urlResponse>
<tx_cobro>{$cobro}</tx_cobro>
<tx_browserIP>{$browserIP}</tx_browserIP>
</transaction>
XML;

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<TRANSACTION3DS>\n{$xmlBody}\n</TRANSACTION3DS>";

        // Log solo para debugging en desarrollo (sin datos sensibles)
        if (config('app.debug')) {
            Log::info('MITEC XML generado para transacción', [
                'reference' => $reference,
                'merchant' => $merchant,
                'amount' => $transactionData['amount']
            ]);
        }        return $xml;
    }

    /**
     * Obtiene el merchant ID según el tipo de tarjeta
     *
     * @param string $cardNumber Número de tarjeta
     * @return string Merchant ID
     */
    private function getMerchantByCardType(string $cardNumber): string
    {
        // Por ahora usar merchant AMEX que está funcionando en QA
        // TODO: Implementar lógica específica por tipo de tarjeta si es necesario
        return env('MITEC_MERCHANT_AMEX');
    }

    /**
     * Genera una referencia única de transacción
     *
     * @return string Referencia única
     */
    private function generateTransactionReference(): string
    {
        return 'TXN' . time() . '_' . strtoupper(substr(uniqid(), -6));
    }

    /**
     * Construye la URL del webhook con el token de la transacción
     *
     * @param string $reference Referencia de la transacción
     * @return string URL completa del webhook
     */
    private function buildWebhookUrl(string $reference): string
    {
        // Usar la URL de respuesta desde .env
        return env('MITEC_RESPONSE_URL') . '?token=' . $reference;
    }

    /**
     * Construye el XML final para el formulario con datos encriptados
     *
     * @param string $encryptedData Datos encriptados
     * @return string XML para el formulario
     */
    public function buildFormXml(string $encryptedData): string
    {
        $data0 = env('MITEC_DATA0');
        return "<pgs><data0>{$data0}</data0><data>{$encryptedData}</data></pgs>";
    }
}
