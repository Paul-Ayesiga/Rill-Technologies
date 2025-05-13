<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * Handle invoice payment succeeded.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleInvoicePaymentSucceeded($payload)
    {
        // Custom logic for when a payment succeeds
        // For example, you might want to log this or send a notification
        Log::info('Payment succeeded for invoice: ' . $payload['data']['object']['id']);

        // Then call the parent method to handle the default behavior
        return parent::handleInvoicePaymentSucceeded($payload);
    }

    /**
     * Handle invoice payment failed.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleInvoicePaymentFailed($payload)
    {
        // Custom logic for when a payment fails
        // For example, you might want to notify the user
        Log::warning('Payment failed for invoice: ' . $payload['data']['object']['id']);

        // Then call the parent method to handle the default behavior
        return parent::handleInvoicePaymentFailed($payload);
    }

    /**
     * Handle customer subscription updated.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleCustomerSubscriptionUpdated($payload)
    {
        // Custom logic for when a subscription is updated
        Log::info('Subscription updated: ' . $payload['data']['object']['id']);

        // Then call the parent method to handle the default behavior
        return parent::handleCustomerSubscriptionUpdated($payload);
    }

    /**
     * Handle customer subscription deleted.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleCustomerSubscriptionDeleted($payload)
    {
        // Custom logic for when a subscription is deleted
        Log::info('Subscription deleted: ' . $payload['data']['object']['id']);

        try {
            // Get the subscription data from the payload
            $stripeSubscription = $payload['data']['object'];

            // Find the subscription in our database
            $subscription = \Laravel\Cashier\Subscription::where('stripe_id', $stripeSubscription['id'])->first();

            if ($subscription) {
                Log::info('Found subscription in database', [
                    'id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'stripe_status' => $subscription->stripe_status
                ]);

                // Update the subscription status
                $subscription->stripe_status = 'canceled';

                // Set the ends_at date if it's not already set
                if (!$subscription->ends_at) {
                    $subscription->ends_at = Carbon::createFromTimestamp($stripeSubscription['current_period_end']);
                    Log::info('Setting subscription end date', [
                        'ends_at' => $subscription->ends_at->toIso8601String()
                    ]);
                }

                $subscription->save();
                Log::info('Subscription marked as canceled in database');
            } else {
                Log::warning('Subscription not found in database', [
                    'stripe_id' => $stripeSubscription['id']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error handling subscription deleted webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Then call the parent method to handle the default behavior
        return parent::handleCustomerSubscriptionDeleted($payload);
    }
}
