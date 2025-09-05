<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Invoice Model
 *
 * Represents a Mexican electronic invoice (CFDI 4.0)
 */
class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'user_id',
        'invoice_number',
        'serie',
        'folio',
        'uuid',
        'status',
        'issuer_rfc',
        'issuer_name',
        'issuer_tax_regime',
        'issuer_postal_code',
        'receiver_rfc',
        'receiver_name',
        'receiver_tax_regime',
        'receiver_postal_code',
        'receiver_cfdi_use',
        'subtotal',
        'tax_amount',
        'total',
        'currency',
        'exchange_rate',
        'payment_method',
        'payment_form',
        'expedition_place',
        'issue_date',
        'stamped_at',
        'cancelled_at',
        'xml_content',
        'pdf_content',
        'sat_response',
        'facturalo_response',
        'cancellation_reason',
        'replacement_uuid',
        'concepts',
        'notes'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'issue_date' => 'datetime',
        'stamped_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'sat_response' => 'array',
        'facturalo_response' => 'array',
        'concepts' => 'array'
    ];

    /**
     * Invoice status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_STAMPED = 'stamped';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_ERROR = 'error';

    /**
     * Get the order that owns the invoice
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user that owns the invoice
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for stamped invoices
     */
    public function scopeStamped($query)
    {
        return $query->where('status', self::STATUS_STAMPED);
    }

    /**
     * Scope for pending invoices
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for cancelled invoices
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Check if invoice is stamped
     */
    public function isStamped(): bool
    {
        return $this->status === self::STATUS_STAMPED;
    }

    /**
     * Check if invoice is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if invoice is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if XML data is available
     */
    public function hasXmlData(): bool
    {
        return !empty($this->xml_content);
    }

    /**
     * Check if PDF data is available
     */
    public function hasPdfData(): bool
    {
        return !empty($this->pdf_content);
    }

    /**
     * Get the full invoice number (serie + folio)
     */
    public function getFullInvoiceNumberAttribute(): string
    {
        return $this->serie . '-' . $this->folio;
    }

    /**
     * Generate next invoice number for a given serie
     */
    public static function generateNextInvoiceNumber(string $serie = 'FAC'): string
    {
        $lastInvoice = self::where('serie', $serie)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastInvoice ? (int)$lastInvoice->folio + 1 : 1;

        return str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Mark invoice as stamped
     */
    public function markAsStamped(string $uuid, array $satResponse, string $xmlContent, string $pdfContent = null): void
    {
        $updateData = [
            'uuid' => $uuid,
            'status' => self::STATUS_STAMPED,
            'stamped_at' => Carbon::now(),
            'sat_response' => $satResponse,
            'facturalo_response' => $satResponse,
            'xml_content' => $xmlContent
        ];

        if ($pdfContent) {
            $updateData['pdf_content'] = $pdfContent;
        }

        $this->update($updateData);

        // Guardar archivos físicos en storage de forma organizada
        $this->savePhysicalFiles($xmlContent, $pdfContent);
    }

    /**
     * Mark invoice as cancelled
     */
    public function markAsCancelled(string $reason, string $replacementUuid = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => Carbon::now(),
            'cancellation_reason' => $reason,
            'replacement_uuid' => $replacementUuid
        ]);
    }

    /**
     * Save physical files to storage in organized structure
     */
    public function savePhysicalFiles(string $xmlContent, string $pdfContent = null): void
    {
        $directory = "invoices/{$this->id}";
        $xmlFilename = "factura_{$this->invoice_number}.xml";
        $pdfFilename = "factura_{$this->invoice_number}.pdf";

        // Guardar XML
        \Storage::disk('local')->put("{$directory}/{$xmlFilename}", $xmlContent);

        // Guardar PDF si existe
        if ($pdfContent) {
            $pdfBinary = base64_decode($pdfContent);
            \Storage::disk('local')->put("{$directory}/{$pdfFilename}", $pdfBinary);
        }

        \Log::info('Physical files saved', [
            'invoice_id' => $this->id,
            'directory' => $directory,
            'xml_file' => $xmlFilename,
            'pdf_file' => $pdfContent ? $pdfFilename : 'not available'
        ]);
    }

    /**
     * Get the full path to the XML file in storage
     */
    public function getXmlFilePath(): string
    {
        return "invoices/{$this->id}/factura_{$this->invoice_number}.xml";
    }

    /**
     * Get the full path to the PDF file in storage
     */
    public function getPdfFilePath(): string
    {
        return "invoices/{$this->id}/factura_{$this->invoice_number}.pdf";
    }

    /**
     * Check if physical XML file exists
     */
    public function hasPhysicalXmlFile(): bool
    {
        return \Storage::disk('local')->exists($this->getXmlFilePath());
    }

    /**
     * Check if physical PDF file exists
     */
    public function hasPhysicalPdfFile(): bool
    {
        return \Storage::disk('local')->exists($this->getPdfFilePath());
    }

    /**
     * Mark invoice as error
     */
    public function markAsError(array $errorResponse): void
    {
        $this->update([
            'status' => self::STATUS_ERROR,
            'facturalo_response' => $errorResponse
        ]);
    }
}
