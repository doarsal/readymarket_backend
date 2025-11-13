<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SubscriptionCancellationService
{
    private MicrosoftAuthService $authService;
    private $partnerCenterApiUrl;

    public function __construct(MicrosoftAuthService $authService)
    {
        $this->authService = $authService;
        $this->partnerCenterApiUrl = config('services.microsoft.partner_center_base_url');
    }

    /**
     * Cancel a subscription in Microsoft Partner Center
     *
     * @param int $subscriptionId Database subscription ID
     * @param string $reason Optional cancellation reason
     * @return array
     * @throws Exception
     */
    public function cancelSubscription(int $subscriptionId, string $reason = 'Customer requested cancellation'): array
    {
        try {
            // Check if we're in fake mode
            if (config('services.microsoft.fake_mode', false)) {
                return $this->cancelSubscriptionFakeMode($subscriptionId, $reason);
            }

            // Get subscription with relationships
            $subscription = Subscription::with(['microsoftAccount', 'order'])
                ->where('id', $subscriptionId)
                ->where('status', 1) // Only active subscriptions
                ->firstOrFail();

            // Check if cancellation is allowed
            if (!$this->canCancelSubscription($subscription)) {
                throw new Exception('This subscription cannot be cancelled. The cancellation window has expired.');
            }

            // Get Microsoft customer ID
            $microsoftCustomerId = $subscription->microsoftAccount->microsoft_id;
            if (!$microsoftCustomerId) {
                throw new Exception('Microsoft customer ID not found');
            }

            // Get authentication token
            $token = $this->getMicrosoftToken();

            // Call Microsoft API to cancel subscription
            $result = $this->cancelMicrosoftSubscription(
                $microsoftCustomerId,
                $subscription->subscription_id,
                $token
            );

            // Update subscription status in database
            $subscription->update([
                'status' => 0, // Mark as inactive
                'modified_by' => auth()->user()->name ?? 'System',
                'updated_at' => now()
            ]);

            // Log cancellation
            Log::channel('microsoft')->info('Subscription cancelled successfully', [
                'subscription_id' => $subscription->id,
                'microsoft_subscription_id' => $subscription->subscription_id,
                'customer_id' => $microsoftCustomerId,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'message' => 'Subscription cancelled successfully',
                'subscription_id' => $subscription->id,
                'microsoft_response' => $result
            ];

        } catch (Exception $e) {
            Log::channel('microsoft')->error('Subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Check if subscription can be cancelled
     * Validates against Microsoft NCE policies
     *
     * @param Subscription $subscription
     * @return bool
     */
    private function canCancelSubscription(Subscription $subscription): bool
    {
        // Must be active
        if ($subscription->status != 1) {
            return false;
        }

        // Load product to determine license type
        $subscription->load('product');

        if (!$subscription->product) {
            return false;
        }

        $billingPlan = $subscription->product->BillingPlan ?? '';
        $termDuration = $subscription->product->TermDuration ?? '';
        $licenseType = $this->determineLicenseType($billingPlan, $termDuration);

        // Use effective_start_date (from Microsoft) if available, otherwise created_at
        $purchaseDate = $subscription->effective_start_date ?? $subscription->created_at;

        if (!$purchaseDate) {
            return false;
        }

        // Calculate calendar days since purchase
        $daysSincePurchase = $purchaseDate->diffInDays(now());

        // PERPETUAL: Can only cancel within 30 days
        if ($licenseType === 'perpetual') {
            return $daysSincePurchase <= 30;
        }

        // SUBSCRIPTIONS: Can always cancel (refund availability is separate concern)
        // - Within 7 days OR before cancellation_allowed_until_date: WITH refund
        // - After: WITHOUT refund (but subscription stays active until commitment_end_date)

        // Note: We allow cancellation even after refund window expires
        // The frontend will explain that no refund is available
        return true;
    }

    /**
     * Determine license type from product data
     *
     * @param string $billingPlan
     * @param string $termDuration
     * @return string
     */
    private function determineLicenseType(string $billingPlan, string $termDuration): string
    {
        // Perpetual: OneTime billing with no term duration
        if ($billingPlan === 'OneTime' && (empty($termDuration) || $termDuration === '')) {
            return 'perpetual';
        }

        // Subscription: Has term duration and recurring billing
        if (!empty($termDuration) && in_array($billingPlan, ['Monthly', 'Annual', 'Triennial'])) {
            return 'subscription';
        }

        return 'other';
    }

    /**
     * Get Microsoft authentication token
     *
     * @return string
     * @throws Exception
     */
    private function getMicrosoftToken(): string
    {
        return $this->authService->getAccessToken();
    }

    /**
     * Cancel subscription in Microsoft Partner Center
     *
     * @param string $customerId
     * @param string $subscriptionId
     * @param string $token
     * @return array
     * @throws Exception
     */
    private function cancelMicrosoftSubscription(string $customerId, string $subscriptionId, string $token): array
    {
        try {
            $url = "{$this->partnerCenterApiUrl}/customers/{$customerId}/subscriptions/{$subscriptionId}";

            // First, get the current subscription to get the ETag
            $getResponse = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ])
                ->get($url);

            if (!$getResponse->successful()) {
                throw new Exception('Failed to retrieve subscription from Microsoft: HTTP ' . $getResponse->status());
            }

            $subscriptionData = $getResponse->json();
            $etag = $getResponse->header('ETag');

            // Update subscription status to "deleted"
            $subscriptionData['status'] = 'deleted';

            // Send PATCH request to cancel
            $patchResponse = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'If-Match' => $etag
                ])
                ->patch($url, $subscriptionData);

            if (!$patchResponse->successful()) {
                $errorBody = $patchResponse->json();
                throw new Exception(sprintf(
                    'Failed to cancel subscription in Microsoft Partner Center: HTTP %d - %s',
                    $patchResponse->status(),
                    $errorBody['description'] ?? $patchResponse->body()
                ));
            }

            return $patchResponse->json();

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Cancel subscription in fake mode (for testing/development)
     *
     * @param int $subscriptionId
     * @param string $reason
     * @return array
     * @throws Exception
     */
    private function cancelSubscriptionFakeMode(int $subscriptionId, string $reason): array
    {
        try {
            // Get subscription
            $subscription = Subscription::with(['microsoftAccount', 'order'])
                ->where('id', $subscriptionId)
                ->where('status', 1)
                ->firstOrFail();

            // Check if cancellation is allowed
            if (!$this->canCancelSubscription($subscription)) {
                throw new Exception('This subscription cannot be cancelled. The cancellation window has expired.');
            }

            // Update subscription status in database
            $subscription->update([
                'status' => 0, // Mark as inactive
                'auto_renew_enabled' => false, // Disable auto-renewal when cancelled
                'modified_by' => auth()->user()->name ?? 'System',
                'updated_at' => now()
            ]);

            // Log cancellation
            Log::info('FAKE MODE - Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'product' => $subscription->product?->ProductTitle,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'message' => '✅ FAKE MODE: Subscription cancelled successfully',
                'subscription_id' => $subscription->id,
                'fake_mode' => true
            ];

        } catch (Exception $e) {
            Log::error('FAKE MODE - Subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
