<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service for handling invoicing with FacturaloPlus API
 */
class InvoiceService
{
    /**
     * API Key provided by FacturaloPlus
     */
    protected $apiKey;

    /**
     * Indicates if we are in test mode or production
     */
    protected $testMode;

    /**
     * Base URL for the current environment (test or production)
     */
    protected $baseUrl;

    /**
     * Service constructor
     */
    public function __construct()
    {
        $this->apiKey = config('services.facturalo.api_key');
        $this->testMode = config('services.facturalo.test_mode', true);

        // Get URLs from configuration
        $sandboxUrl = config('services.facturalo.url_sandbox');
        $productionUrl = config('services.facturalo.url_production');

        // Set base URL according to the mode
        $this->baseUrl = $this->testMode ? $sandboxUrl : $productionUrl;
    }

    /**
     * Gets the base URL according to the environment
     */
    protected function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Stamps a CFDI using XML
     *
     * @param string $xmlContent XML content to stamp
     * @return array Response from the invoicing service
     */
    public function stampXml(string $xmlContent): array
    {
        try {
            $response = Http::asForm()->post($this->getBaseUrl() . '/timbrar', [
                'apikey' => $this->apiKey,
                'xml' => $xmlContent
            ]);

            Log::info('XML stamping response: ' . $response->body());

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error stamping XML',
                    'errors' => $response->json(),
                ];
            }
        } catch (Exception $e) {
            Log::error('Error in stampXml: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error processing stamping request',
                'errors' => $e->getMessage(),
            ];
        }
    }

    /**
     * Stamps a CFDI using JSON
     *
     * @param array $invoiceData Invoice data in JSON format
     * @return array Response from the invoicing service
     */
    public function stampJson(array $invoiceData): array
    {
        try {
            $jsonData = json_encode($invoiceData);

            $response = Http::asForm()->post($this->getBaseUrl() . '/timbrarJSON2', [
                'apikey' => $this->apiKey,
                'json' => $jsonData
            ]);

            Log::info('JSON stamping response: ' . $response->body());

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error stamping JSON',
                    'errors' => $response->json(),
                ];
            }
        } catch (Exception $e) {
            Log::error('Error in stampJson: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error processing JSON stamping request',
                'errors' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available stamping credits
     *
     * @return array Response with available credits
     */
    public function getAvailableCredits(): array
    {
        try {
            $response = Http::asForm()->post($this->getBaseUrl() . '/consultarCreditosDisponibles', [
                'apikey' => $this->apiKey,
            ]);

            Log::info('Credits query response: ' . $response->body());

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error querying available credits',
                    'errors' => $response->json(),
                ];
            }
        } catch (Exception $e) {
            Log::error('Error in getAvailableCredits: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Error processing credit query request',
                'errors' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generates an XML invoice for CFDI 4.0
     *
     * @param array $data Data to generate the invoice
     * @return string Generated XML
     */
    public function generateXmlCfdi40(array $data): string
    {
        // Implement logic to generate XML according to received data
        // This is a basic implementation, you should adapt it to your specific needs

        $issueDate = date('Y-m-d\TH:i:s');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="4.0" Serie="{$data['series']}" Folio="{$data['folio']}" Fecha="{$issueDate}" FormaPago="{$data['payment_form']}" SubTotal="{$data['subtotal']}" Moneda="{$data['currency']}" Total="{$data['total']}" TipoDeComprobante="I" Exportacion="01" MetodoPago="{$data['payment_method']}" LugarExpedicion="{$data['expedition_place']}">
    <cfdi:Emisor Rfc="{$data['issuer']['tax_id']}" Nombre="{$data['issuer']['name']}" RegimenFiscal="{$data['issuer']['tax_regime']}"/>
    <cfdi:Receptor Rfc="{$data['receiver']['tax_id']}" Nombre="{$data['receiver']['name']}" DomicilioFiscalReceptor="{$data['receiver']['zip_code']}" RegimenFiscalReceptor="{$data['receiver']['tax_regime']}" UsoCFDI="{$data['receiver']['cfdi_usage']}"/>
    <cfdi:Conceptos>
XML;

        foreach ($data['items'] as $item) {
            $xml .= <<<XML
        <cfdi:Concepto ClaveProdServ="{$item['product_key']}" Cantidad="{$item['quantity']}" ClaveUnidad="{$item['unit_key']}" Unidad="{$item['unit']}" Descripcion="{$item['description']}" ValorUnitario="{$item['unit_price']}" Importe="{$item['amount']}" ObjetoImp="02">
            <cfdi:Impuestos>
                <cfdi:Traslados>
                    <cfdi:Traslado Base="{$item['amount']}" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="{$item['tax_amount']}"/>
                </cfdi:Traslados>
            </cfdi:Impuestos>
        </cfdi:Concepto>
XML;
        }

        $xml .= <<<XML
    </cfdi:Conceptos>
    <cfdi:Impuestos TotalImpuestosTrasladados="{$data['total_transferred_taxes']}">
        <cfdi:Traslados>
            <cfdi:Traslado Base="{$data['tax_base']}" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="{$data['total_transferred_taxes']}"/>
        </cfdi:Traslados>
    </cfdi:Impuestos>
</cfdi:Comprobante>
XML;

        return $xml;
    }

    /**
     * Prepares JSON data for CFDI 4.0
     *
     * @param array $data Invoice data
     * @return array Prepared JSON data for FacturaloPlus API
     */
    public function prepareJsonCfdi40Data(array $data): array
    {
        // Format for FacturaloPlus JSON API
        $jsonData = [
            'serie' => $data['series'],
            'folio' => $data['folio'],
            'fecha_emision' => date('Y-m-d\TH:i:s'),
            'forma_pago' => $data['payment_form'],
            'subtotal' => $data['subtotal'],
            'moneda' => $data['currency'],
            'total' => $data['total'],
            'tipo_comprobante' => 'I',
            'exportacion' => '01',
            'metodo_pago' => $data['payment_method'],
            'lugar_expedicion' => $data['expedition_place'],
            'emisor' => [
                'rfc' => $data['issuer']['tax_id'],
                'nombre' => $data['issuer']['name'],
                'regimen_fiscal' => $data['issuer']['tax_regime'],
            ],
            'receptor' => [
                'rfc' => $data['receiver']['tax_id'],
                'nombre' => $data['receiver']['name'],
                'domicilio_fiscal_receptor' => $data['receiver']['zip_code'],
                'regimen_fiscal_receptor' => $data['receiver']['tax_regime'],
                'uso_cfdi' => $data['receiver']['cfdi_usage'],
            ],
            'conceptos' => [],
            'impuestos' => [
                'total_impuestos_trasladados' => $data['total_transferred_taxes'],
                'traslados' => [
                    [
                        'base' => $data['tax_base'],
                        'impuesto' => '002',
                        'tipo_factor' => 'Tasa',
                        'tasa_o_cuota' => '0.160000',
                        'importe' => $data['total_transferred_taxes'],
                    ]
                ]
            ],
        ];

        // Add items (concepts)
        foreach ($data['items'] as $item) {
            $jsonData['conceptos'][] = [
                'clave_producto_servicio' => $item['product_key'],
                'cantidad' => $item['quantity'],
                'clave_unidad' => $item['unit_key'],
                'unidad' => $item['unit'],
                'descripcion' => $item['description'],
                'valor_unitario' => $item['unit_price'],
                'importe' => $item['amount'],
                'objeto_imp' => '02',
                'impuestos' => [
                    'traslados' => [
                        [
                            'base' => $item['amount'],
                            'impuesto' => '002',
                            'tipo_factor' => 'Tasa',
                            'tasa_o_cuota' => '0.160000',
                            'importe' => $item['tax_amount'],
                        ]
                    ]
                ]
            ];
        }

        return $jsonData;
    }
}
