<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SubscriptionAutoRenewService
{
    private $tokenEndpoint;
    private $partnerCenterApiUrl;

    public function __construct()
    {
        $this->tokenEndpoint = config('services.microsoft.credentials_url');
        $this->partnerCenterApiUrl = config('services.microsoft.partner_center_base_url');
    }

    /**
     * Toggle auto-renewal for a subscription
     *
     * @param int $subscriptionId Database subscription ID
     * @param bool $autoRenewEnabled Enable or disable auto-renewal
     * @return array
     * @throws Exception
     */
    public function toggleAutoRenew(int $subscriptionId, bool $autoRenewEnabled): array
    {
        try {
            // Check if we're in fake mode
            if (config('services.microsoft.fake_mode', false)) {
                return $this->toggleAutoRenewFakeMode($subscriptionId, $autoRenewEnabled);
            }

            // Get subscription with relationships
            $subscription = Subscription::with(['microsoftAccount', 'product'])
                ->where('id', $subscriptionId)
                ->where('status', 1) // Only active subscriptions
                ->firstOrFail();

            // Only subscriptions can have auto-renew toggled (not perpetual licenses)
            if (!$this->isRenewable($subscription)) {
                throw new Exception('This license type does not support auto-renewal');
            }

            // Get Microsoft customer ID
            $microsoftCustomerId = $subscription->microsoftAccount->microsoft_id;
            if (!$microsoftCustomerId) {
                throw new Exception('Microsoft customer ID not found');
            }

            // Validate Microsoft subscription ID (must be GUID format)
            if (empty($subscription->subscription_id)) {
                throw new Exception('Microsoft subscription ID is empty. This subscription may not have been provisioned correctly.');
            }

            // Validate GUID format (8-4-4-4-12 pattern)
            $guidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
            if (!preg_match($guidPattern, $subscription->subscription_id)) {
                throw new Exception(sprintf(
                    'Invalid Microsoft subscription ID format. Expected GUID, got: %s',
                    $subscription->subscription_id
                ));
            }

            // Get authentication token
            $token = $this->getMicrosoftToken();

            // Update auto-renew in Microsoft Partner Center
            $result = $this->updateAutoRenewInMicrosoft(
                $microsoftCustomerId,
                $subscription->subscription_id,
                $autoRenewEnabled,
                $token
            );

            // Update subscription in database
            $subscription->update([
                'auto_renew_enabled' => $autoRenewEnabled,
                'modified_by' => auth()->user()->name ?? 'System',
                'updated_at' => now()
            ]);

            // Log the change
            Log::channel('microsoft')->info('Auto-renewal toggled', [
                'subscription_id' => $subscription->id,
                'microsoft_subscription_id' => $subscription->subscription_id,
                'customer_id' => $microsoftCustomerId,
                'auto_renew_enabled' => $autoRenewEnabled
            ]);

            return [
                'success' => true,
                'message' => $autoRenewEnabled ? 'Auto-renewal enabled successfully' : 'Auto-renewal disabled successfully',
                'subscription_id' => $subscription->id,
                'auto_renew_enabled' => $autoRenewEnabled,
                'microsoft_response' => $result
            ];

        } catch (Exception $e) {
            Log::channel('microsoft')->error('Auto-renewal toggle failed', [
                'subscription_id' => $subscriptionId,
                'auto_renew_enabled' => $autoRenewEnabled,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Check if subscription supports auto-renewal
     *
     * @param Subscription $subscription
     * @return bool
     */
    private function isRenewable(Subscription $subscription): bool
    {
        if (!$subscription->product) {
            return false;
        }

        $billingPlan = $subscription->product->BillingPlan ?? '';
        $termDuration = $subscription->product->TermDuration ?? '';

        // Only subscription-based licenses support auto-renewal (not perpetual/OneTime)
        return in_array($billingPlan, ['Monthly', 'Annual', 'Triennial'])
            && !empty($termDuration);
    }

    /**
     * Get Microsoft authentication token
     *
     * @return string
     * @throws Exception
     */
    private function getMicrosoftToken(): string
    {
        try {
            $response = Http::timeout(config('services.microsoft.token_timeout', 60))
                ->get($this->tokenEndpoint);

            if (!$response->successful()) {
                throw new Exception('Failed to get Microsoft token: HTTP ' . $response->status());
            }

            $data = $response->json();

            if (!isset($data['item']['token'])) {
                throw new Exception('Invalid token response from Microsoft');
            }

            return $data['item']['token'];

        } catch (Exception $e) {
            throw new Exception('Failed to authenticate with Microsoft Partner Center: ' . $e->getMessage());
        }
    }

    /**
     * Update auto-renewal setting in Microsoft Partner Center
     *
     * @param string $customerId
     * @param string $subscriptionId
     * @param bool $autoRenewEnabled
     * @param string $token
     * @return array
     * @throws Exception
     */
    private function updateAutoRenewInMicrosoft(
        string $customerId,
        string $subscriptionId,
        bool $autoRenewEnabled,
        string $token
    ): array {
        try {
            $url = "{$this->partnerCenterApiUrl}/customers/{$customerId}/subscriptions/{$subscriptionId}";

            Log::info('Attempting to get subscription from Microsoft', [
                'url' => $url,
                'customer_id' => $customerId,
                'subscription_id' => $subscriptionId
            ]);

            // First, get the current subscription to get the ETag
            $getResponse = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ])
                ->get($url);

            if (!$getResponse->successful()) {
                $errorBody = $getResponse->json();
                Log::error('Microsoft GET subscription failed', [
                    'url' => $url,
                    'status' => $getResponse->status(),
                    'error_body' => $errorBody,
                    'response_body' => $getResponse->body()
                ]);
                throw new Exception('Failed to retrieve subscription from Microsoft: HTTP ' . $getResponse->status() . ' - ' . ($errorBody['description'] ?? $getResponse->body()));
            }

            $subscriptionData = $getResponse->json();
            $etag = $getResponse->header('ETag');

            // Update autoRenewEnabled field
            $subscriptionData['autoRenewEnabled'] = $autoRenewEnabled;

            // Send PATCH request to update auto-renewal
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
                    'Failed to update auto-renewal in Microsoft Partner Center: HTTP %d - %s',
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
     * Toggle auto-renewal in fake mode (for testing/development)
     *
     * @param int $subscriptionId
     * @param bool $autoRenewEnabled
     * @return array
     * @throws Exception
     */
    private function toggleAutoRenewFakeMode(int $subscriptionId, bool $autoRenewEnabled): array
    {
        try {
            // Get subscription
            $subscription = Subscription::with(['microsoftAccount', 'product'])
                ->where('id', $subscriptionId)
                ->where('status', 1)
                ->firstOrFail();

            // Only subscriptions can have auto-renew toggled
            if (!$this->isRenewable($subscription)) {
                throw new Exception('This license type does not support auto-renewal');
            }

            // Update subscription in database
            $subscription->update([
                'auto_renew_enabled' => $autoRenewEnabled,
                'modified_by' => auth()->user()->name ?? 'System',
                'updated_at' => now()
            ]);

            // Log the change
            Log::info('FAKE MODE - Auto-renewal toggled', [
                'subscription_id' => $subscription->id,
                'auto_renew_enabled' => $autoRenewEnabled,
                'product' => $subscription->product?->ProductTitle
            ]);

            return [
                'success' => true,
                'message' => '✅ FAKE MODE: ' . ($autoRenewEnabled ? 'Auto-renewal enabled successfully' : 'Auto-renewal disabled successfully'),
                'subscription_id' => $subscription->id,
                'auto_renew_enabled' => $autoRenewEnabled,
                'fake_mode' => true
            ];

        } catch (Exception $e) {
            Log::error('FAKE MODE - Auto-renewal toggle failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
