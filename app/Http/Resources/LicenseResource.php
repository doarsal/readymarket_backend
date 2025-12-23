<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Config;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="LicenseResource",
 *     type="object",
 *     title="License Resource",
 *     description="License data structure",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="subscription_id", type="string", example="sub_123456"),
 *     @OA\Property(property="product_name", type="string", example="Microsoft 365 Business Standard"),
 *     @OA\Property(property="sku", type="string", example="M365-BSN-STD"),
 *     @OA\Property(property="publisher", type="string", example="Microsoft"),
 *     @OA\Property(property="quantity", type="integer", example=10),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive"}),
 *     @OA\Property(property="billing_plan", type="string", example="Annual"),
 *     @OA\Property(property="term_duration", type="string", example="P1Y"),
 *     @OA\Property(property="license_type", type="string", enum={"subscription", "perpetual", "azure_reservation", "azure_credit"}),
 *     @OA\Property(property="product_icon", type="string"),
 *     @OA\Property(property="unit_price", type="number", format="float"),
 *     @OA\Property(property="total_price", type="number", format="float"),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="purchased_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="microsoft_account",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="domain", type="string"),
 *         @OA\Property(property="organization", type="string")
 *     ),
 *     @OA\Property(
 *         property="order",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="order_number", type="string"),
 *         @OA\Property(property="total_amount", type="number")
 *     )
 * )
 */
class LicenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Basic license information
            'id'                                        => $this->id,
            'subscription_id'                           => $this->subscription_id,

            // Product information
            'product_name'                              => $this->product?->ProductTitle ?? 'Unknown Product',
            'sku'                                       => $this->product?->SkuTitle ?? null,
            'publisher'                                 => $this->product?->Publisher ?? 'Microsoft',
            'product_icon'                              => $this->getProductIcon(),

            // License details
            'quantity'                                  => $this->quantity,
            'status'                                    => $this->status == 1 ? 'active' : 'inactive',
            'status_numeric'                            => $this->status,

            // Billing information
            'billing_plan'                              => $this->product?->BillingPlan ?? null,
            'term_duration'                             => $this->term_duration,
            'term_duration_readable'                    => $this->getReadableTermDuration(),
            'license_type'                              => $this->getLicenseType(),
            'transaction_type'                          => $this->transaction_type,

            // Pricing
            'unit_price'                                => $this->formatPrice($this->pricing),
            'total_price'                               => $this->formatPrice($this->pricing * $this->quantity),
            'currency'                                  => $this->order?->currency?->code ?? 'MXN',

            // Dates
            'purchased_at'                              => $this->created_at?->toIso8601String(),
            'purchased_at_formatted'                    => $this->created_at?->format('M d, Y'),
            'updated_at_formatted'                      => $this->updated_at?->format('M d, Y H:i'),
            'effective_start_date'                      => $this->effective_start_date?->toIso8601String(),
            'effective_start_date_formatted'            => $this->effective_start_date?->format('M d, Y'),
            'commitment_end_date'                       => $this->commitment_end_date?->toIso8601String(),
            'commitment_end_date_formatted'             => $this->commitment_end_date?->format('M d, Y'),
            'cancellation_allowed_until_date'           => $this->cancellation_allowed_until_date?->toIso8601String(),
            'cancellation_allowed_until_date_formatted' => $this->cancellation_allowed_until_date?->format('M d, Y'),
            'auto_renew_enabled'                        => $this->auto_renew_enabled ?? false,
            'billing_cycle'                             => $this->billing_cycle,
            'can_cancel'                                => $this->canCancelSubscription(),
            'can_cancel_with_refund'                    => $this->canCancelWithRefund(),
            'days_until_expiration'                     => $this->getDaysUntilExpiration(),
            'created_at'                                => $this->created_at?->toIso8601String(),
            'updated_at'                                => $this->updated_at?->toIso8601String(),

            // Related entities
            'microsoft_account'                         => $this->when($this->relationLoaded('microsoftAccount') && $this->microsoftAccount,
                [
                    'id'           => $this->microsoftAccount?->id,
                    'domain'       => $this->microsoftAccount?->domain_concatenated,
                    'organization' => $this->microsoftAccount?->organization,
                    'status'       => $this->microsoftAccount?->status,
                ]),

            'order'        => $this->when($this->relationLoaded('order') && $this->order, [
                'id'           => $this->order?->id,
                'order_number' => $this->order?->order_number,
                'total_amount' => $this->formatPrice($this->order?->total_amount),
                'created_at'   => $this->order?->created_at?->toIso8601String(),
            ]),

            // Computed properties
            'is_renewable' => $this->isRenewable(),
            'renewal_info' => $this->getRenewalInfo(),
            'expires_soon' => $this->expiresSoon(),
        ];
    }

    /**
     * Get product icon URL
     *
     * @return string|null
     */
    private function getProductIcon(): ?string
    {
        if (!$this->product || !$this->product->prod_icon) {
            return null;
        }

        // If it's already a full URL, return as is
        if (filter_var($this->product->prod_icon, FILTER_VALIDATE_URL)) {
            return $this->product->prod_icon;
        }

        // Otherwise, construct the full URL
        return asset('storage/' . $this->product->prod_icon);
    }

    /**
     * Format price to 2 decimal places
     *
     * @param mixed $price
     *
     * @return float|null
     */
    private function formatPrice($price): ?float
    {
        if ($price === null) {
            return null;
        }

        return round((float) $price, 2);
    }

    /**
     * Get readable term duration
     *
     * @return string|null
     */
    private function getReadableTermDuration(): ?string
    {
        // If perpetual license, return "Perpetual"
        if ($this->getLicenseType() === 'perpetual') {
            return 'Perpetua';
        }

        if (!$this->term_duration) {
            return null;
        }

        // Parse ISO 8601 duration format
        $duration = $this->term_duration;

        $map = [
            'P1M' => '1 Mes',
            'P3M' => '3 Meses',
            'P6M' => '6 Meses',
            'P1Y' => '1 Año',
            'P2Y' => '2 Años',
            'P3Y' => '3 Años',
        ];

        return $map[$duration] ?? $duration;
    }

    /**
     * Determine license type based on product characteristics
     *
     * @return string
     */
    private function getLicenseType(): string
    {
        if (!$this->product) {
            return 'unknown';
        }

        $productTitle = strtolower($this->product->ProductTitle ?? '');
        $billingPlan  = $this->product->BillingPlan ?? '';
        $termDuration = $this->product->TermDuration ?? '';

        // Check for Azure products
        if (str_contains($productTitle, 'azure')) {
            if (str_contains($productTitle, 'reserved') || str_contains($productTitle, 'reservation')) {
                return 'azure_reservation';
            }
            if (str_contains($productTitle, 'credit') || str_contains($productTitle,
                    'prepaid') || str_contains($productTitle, 'prepago')) {
                return 'azure_credit';
            }

            return 'azure_plan';
        }

        // Check for perpetual licenses (OneTime billing with no term duration or empty term)
        if ($billingPlan === 'OneTime' && (empty($termDuration) || $termDuration === '')) {
            return 'perpetual';
        }

        // Check for subscription-based licenses (has term duration and recurring billing)
        if (!empty($termDuration) && in_array($billingPlan, ['Monthly', 'Annual', 'Triennial'])) {
            return 'subscription';
        }

        // Default
        return 'other';
    }

    /**
     * Check if license is renewable
     *
     * @return bool
     */
    private function isRenewable(): bool
    {
        if (!$this->product) {
            return false;
        }

        $billingPlan = $this->product->BillingPlan ?? '';
        $licenseType = $this->getLicenseType();

        // Only subscription-based licenses are renewable
        return in_array($billingPlan,
                ['Monthly', 'Annual', 'Triennial']) && $licenseType === 'subscription' && $this->status == 1;
    }

    /**
     * Get renewal information
     *
     * @return array|null
     */
    private function getRenewalInfo(): ?array
    {
        // Use the computed attributes from Subscription model
        // These are automatically calculated based on commitment_end_date

        // For perpetual licenses, return null
        if ($this->isPerpetual()) {
            return null;
        }

        // If no renewal date available, return null
        if (!$this->next_renewal_date) {
            return null;
        }

        try {
            return [
                'next_renewal_date'           => $this->next_renewal_date,
                'next_renewal_date_formatted' => $this->commitment_end_date?->format('M d, Y H:i'),
                'days_until_renewal'          => $this->days_until_renewal,
                'auto_renew_enabled'          => $this->auto_renew_enabled ?? false,
                'renewal_term'                => $this->renewal_frequency, // "Mensual", "Anual", etc
                'expiration_info'             => $this->expiration_info, // Human-readable message
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate renewal date from creation date and term duration
     *
     * @param Carbon $startDate
     * @param string $termDuration
     *
     * @return Carbon
     */
    private function calculateRenewalDate(Carbon $startDate, string $termDuration): Carbon
    {
        $date = $startDate->copy();

        // Parse ISO 8601 duration
        switch ($termDuration) {
            case 'P1M':
                return $date->addMonth();
            case 'P3M':
                return $date->addMonths(3);
            case 'P6M':
                return $date->addMonths(6);
            case 'P1Y':
                return $date->addYear();
            case 'P2Y':
                return $date->addYears(2);
            case 'P3Y':
                return $date->addYears(3);
            default:
                return $date->addYear(); // Default to 1 year
        }
    }

    /**
     * Check if license expires soon (within 30 days)
     *
     * @return bool
     */
    private function expiresSoon(): bool
    {
        if (!$this->isRenewable()) {
            return false;
        }

        $renewalInfo = $this->getRenewalInfo();

        if (!$renewalInfo) {
            return false;
        }

        return $renewalInfo['days_until_renewal'] <= 30 && $renewalInfo['days_until_renewal'] > 0;
    }

    /**
     * Check if subscription can be cancelled WITH REFUND
     *
     * Microsoft New Commerce Cancellation Policy:
     * - Perpetual software: Full refund within 30 calendar days of purchase
     * - Software subscriptions: Prorated refund within 7 calendar days of purchase OR until cancellation_allowed_until_date
     * - After refund window: CAN STILL CANCEL but NO REFUND (subscription stays active until commitment_end_date)
     * - Turning off auto-renew: Different from cancellation - subscription renews until disabled
     *
     * IMPORTANT: This method returns TRUE if cancellation is allowed, regardless of refund eligibility
     * Use canCancelWithRefund() to check if refund is available
     *
     * @return bool
     */
    private function canCancelSubscription(): bool
    {
        // Can only cancel active subscriptions
        if ($this->status != 1) {
            return false;
        }

        $licenseType = $this->getLicenseType();

        // Use effective_start_date if available (Microsoft's date), otherwise use created_at
        $purchaseDate = $this->effective_start_date ?? $this->created_at;

        if (!$purchaseDate) {
            return false;
        }

        // Calculate calendar days since purchase (not business days)
        $daysSincePurchase = $purchaseDate->diffInDays(now());
        $daysToCancel      = (int) Config::get('orders.days_to_cancel');

        // PERPETUAL SOFTWARE: Can only cancel within 30 days WITH refund
        // After 30 days: Cannot cancel at all (it's a one-time purchase)
        if ($licenseType === 'perpetual') {
            return $daysSincePurchase <= $daysToCancel;
        }

        // SUBSCRIPTIONS: Can ALWAYS cancel (even after 7 days)
        // - Within 7 days OR before cancellation_allowed_until_date: WITH prorated refund
        // - After 7 days: WITHOUT refund (subscription stays active until commitment_end_date)

        // For subscriptions, we allow cancellation at any time
        // The refund logic is handled separately
        return true;
    }

    /**
     * Check if subscription can be cancelled WITH REFUND
     *
     * @return bool
     */
    private function canCancelWithRefund(): bool
    {
        if ($this->status != 1) {
            return false;
        }

        $purchaseDate = $this->effective_start_date ?? $this->created_at;

        if (!$purchaseDate) {
            return false;
        }

        $daysSincePurchase = $purchaseDate->diffInDays(now());

        //$licenseType  = $this->getLicenseType();
        // Perpetual: Full refund within 30 days
        //if ($licenseType === 'perpetual') {
        //    return $daysSincePurchase <= 30;
        //}

        // Subscriptions: Prorated refund within cancellation window
        // Priority 1: Microsoft's provided cancellation deadline
        if ($this->cancellation_allowed_until_date) {
            return now()->lte($this->cancellation_allowed_until_date);
        }

        $daysToCancel = (int) Config::get('orders.days_to_cancel');

        // Priority 2: 5 calendar days from purchase (NCE policy)
        return $daysSincePurchase <= $daysToCancel;
    }

    /**
     * Get days until expiration
     *
     * @return int|null
     */
    private function getDaysUntilExpiration(): ?int
    {
        if (!$this->commitment_end_date) {
            return null;
        }

        return now()->diffInDays($this->commitment_end_date, false);
    }
}
