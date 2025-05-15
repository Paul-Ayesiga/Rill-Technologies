<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Subscription;
use Stripe\Exception\CardException;
use Stripe\Stripe;

class SubscriptionController extends Controller
{
    /**
     * Display the subscription page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $user = Auth::user();

        // Get subscription data
        $subscription = null;
        $paymentMethod = null;
        $invoices = [];

        if ($user->hasDefaultPaymentMethod()) {
            $paymentMethod = $user->defaultPaymentMethod();
        }

        // Get the subscription type from the database or use 'default' as fallback
        $subscriptionType = $this->getSubscriptionType($user);

        if ($user->subscribed($subscriptionType)) {
            $subscription = $user->subscription($subscriptionType);
            $invoices = $user->invoices();
        }

        // Format subscription data for the frontend
        $formattedSubscription = $this->formatSubscription($subscription, $paymentMethod, $invoices);

        // Get available plans
        $plans = $this->getAvailablePlans();

        return Inertia::render('Billing', [
            'subscription' => $formattedSubscription,
            'plans' => $plans,
        ]);
    }

    /**
     * Subscribe the user to a plan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'plan' => 'required|string',
            'payment_method' => 'nullable|string', // Make payment_method nullable for plan changes
        ]);

        $user = Auth::user();
        $paymentMethod = $request->payment_method;
        $planId = $request->plan;

        try {
            // Get the subscription type from the database or use 'default' as fallback
            $subscriptionType = $this->getSubscriptionType($user);

            // Log the subscription details for debugging
            Log::info('Subscription change attempt', [
                'user_id' => $user->id,
                'subscription_type' => $subscriptionType,
                'plan_id' => $planId,
                'has_subscription' => $user->subscribed($subscriptionType),
                'current_plan' => $user->subscribed($subscriptionType) ? $user->subscription($subscriptionType)->stripe_price : null
            ]);

            // If the user already has a subscription, we'll swap the plan
            if ($user->subscribed($subscriptionType)) {
                try {
                    // Get the current plan ID before swapping
                    $oldPlanId = $user->subscription($subscriptionType)->stripe_price;

                    // Check if the user is trying to change to the same plan
                    if ($oldPlanId === $planId) {
                        Log::info('User attempted to switch to the same plan', [
                            'user_id' => $user->id,
                            'subscription_type' => $subscriptionType,
                            'plan_id' => $planId
                        ]);

                        return redirect()->route('billing')
                            ->with('info', 'You are already subscribed to this plan.');
                    }

                    // Swap the plan
                    $user->subscription($subscriptionType)->swap($planId);

                    Log::info('Plan swapped successfully', [
                        'user_id' => $user->id,
                        'subscription_type' => $subscriptionType,
                        'old_plan' => $oldPlanId,
                        'new_plan' => $planId
                    ]);
                } catch (\Stripe\Exception\CardException $e) {
                    Log::error('Stripe card error when swapping plan', [
                        'user_id' => $user->id,
                        'subscription_type' => $subscriptionType,
                        'plan_id' => $planId,
                        'error' => $e->getMessage(),
                        'code' => $e->getCode()
                    ]);
                    throw $e;
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    Log::error('Stripe invalid request error when swapping plan', [
                        'user_id' => $user->id,
                        'subscription_type' => $subscriptionType,
                        'plan_id' => $planId,
                        'error' => $e->getMessage(),
                        'code' => $e->getCode()
                    ]);
                    throw $e;
                } catch (\Exception $e) {
                    Log::error('Error swapping plan', [
                        'user_id' => $user->id,
                        'subscription_type' => $subscriptionType,
                        'plan_id' => $planId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            } else {
                // Create a new subscription
                try {
                    // Check if the plan has a trial period
                    $trialDays = $this->getPlanTrialDays($planId);

                    // Create a subscription builder
                    $subscriptionBuilder = $user->newSubscription($subscriptionType, $planId);

                    // Apply trial period if available
                    if ($trialDays > 0) {
                        $subscriptionBuilder->trialDays($trialDays);
                    }

                    // Create the subscription
                    $subscriptionBuilder->create($paymentMethod);

                    Log::info('New subscription created', [
                        'user_id' => $user->id,
                        'subscription_type' => $subscriptionType,
                        'plan_id' => $planId,
                        'trial_days' => $trialDays
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error creating subscription', [
                        'user_id' => $user->id,
                        'subscription_type' => $subscriptionType,
                        'plan_id' => $planId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }

            // Check if the request expects JSON (AJAX request)
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription updated successfully!',
                    'subscription' => $this->formatSubscription(
                        $user->subscription($subscriptionType),
                        $user->defaultPaymentMethod(),
                        $user->invoices()
                    )
                ]);
            }

            return redirect()->route('billing')
                ->with('success', 'Subscription created successfully!');
        } catch (IncompletePayment $exception) {
            Log::warning('Incomplete payment during subscription', [
                'user_id' => $user->id,
                'payment_id' => $exception->payment->id,
                'error' => $exception->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Incomplete payment',
                    'payment_id' => $exception->payment->id,
                    'redirect' => route('cashier.payment', [$exception->payment->id, 'redirect' => route('billing')])
                ], 402);
            }

            return redirect()->route('cashier.payment', [
                $exception->payment->id, 'redirect' => route('billing')
            ]);
        } catch (CardException $exception) {
            Log::error('Card exception during subscription', [
                'user_id' => $user->id,
                'error' => $exception->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $exception->getMessage()
                ], 400);
            }

            return back()->withErrors(['error' => $exception->getMessage()]);
        } catch (\Exception $exception) {
            Log::error('Unexpected error during subscription', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'There was an error processing your request: ' . $exception->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'There was an error processing your request: ' . $exception->getMessage()]);
        }
    }

    /**
     * Update the user's payment method.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string',
        ]);

        $user = Auth::user();
        $paymentMethod = $request->payment_method;

        try {
            $user->updateDefaultPaymentMethod($paymentMethod);
            $user->updateDefaultPaymentMethodFromStripe();

            return redirect()->route('billing')
                ->with('success', 'Payment method updated successfully!');
        } catch (CardException $exception) {
            return back()->withErrors(['error' => $exception->getMessage()]);
        }
    }

    /**
     * Cancel the user's subscription at the end of the billing period.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancel(Request $request)
    {
        $request->validate([
            'reason' => 'nullable|string',
            'cancel_type' => 'required|in:end_of_period,immediately',
        ]);

        $user = Auth::user();

        // Get the subscription type from the database or use 'default' as fallback
        $subscriptionType = $this->getSubscriptionType($user);

        if ($user->subscribed($subscriptionType)) {
            // Store cancellation reason if provided
            if ($request->has('reason') && !empty($request->reason)) {
                // You could store this in a database table if needed
                // For now, we'll just log it
                Log::info('Subscription cancelled. Reason: ' . $request->reason);
            }

            // Determine cancellation type
            if ($request->cancel_type === 'immediately') {
                // Cancel the subscription immediately
                $user->subscription($subscriptionType)->cancelNow();
                $message = 'Your subscription has been cancelled immediately.';
            } else {
                // Cancel the subscription at the end of the billing period
                $user->subscription($subscriptionType)->cancel();
                $message = 'Your subscription has been cancelled and will end at the end of your billing period.';
            }

            return redirect()->route('billing')
                ->with('success', $message);
        }

        return back()->withErrors(['error' => 'You do not have an active subscription.']);
    }

    /**
     * Resume a cancelled subscription.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resume()
    {
        $user = Auth::user();
        $subscriptionType = $this->getSubscriptionType($user);
        $subscription = $user->subscription($subscriptionType);

        if (!$subscription) {
            return back()->withErrors(['error' => 'No subscription found.']);
        }

        // Check if the subscription is on grace period (can be resumed)
        if ($subscription->onGracePeriod()) {
            try {
                $subscription->resume();

                if (request()->expectsJson()) {
                    return response()->json([
                        'message' => 'Your subscription has been resumed successfully.'
                    ]);
                }

                return redirect()->route('billing')
                    ->with('success', 'Your subscription has been resumed successfully.');
            } catch (\Exception $e) {
                Log::error('Error resuming subscription: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id
                ]);

                if (request()->expectsJson()) {
                    return response()->json([
                        'error' => 'Failed to resume subscription: ' . $e->getMessage()
                    ], 500);
                }

                return back()->withErrors(['error' => 'Failed to resume subscription: ' . $e->getMessage()]);
            }
        }

        if (request()->expectsJson()) {
            return response()->json([
                'error' => 'Your subscription cannot be resumed. It must be on a grace period to be resumed.'
            ], 400);
        }

        return back()->withErrors(['error' => 'Your subscription cannot be resumed. It must be on a grace period to be resumed.']);
    }

    /**
     * Get available subscription plans for public display.
     * This endpoint doesn't require authentication.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPublicPlans()
    {
        try {
            // Set Stripe API key
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Fetch prices from Stripe
            $prices = \Stripe\Price::all([
                'active' => true,
                'limit' => 10,
                'expand' => ['data.product']
            ]);

            // Process the prices into a format suitable for the frontend
            $plans = [];

            foreach ($prices->data as $price) {
                // Skip if product is not active
                if (!$price->product->active) {
                    continue;
                }

                // Get the plan name from the product
                $name = $price->product->name;

                // Get the price details
                $amount = $price->unit_amount / 100; // Convert from cents to dollars
                $currency = strtolower($price->currency);
                $interval = $price->recurring->interval;

                // Get features from product metadata or use defaults
                $features = [];

                // Try to get features from product metadata
                $features = [];

                // Check if product has metadata with features
                if (isset($price->product->metadata->features)) {
                    try {
                        // Try to parse features from metadata
                        $featuresData = json_decode($price->product->metadata->features, true);
                        if (is_array($featuresData)) {
                            $features = $featuresData;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to parse features from product metadata', [
                            'product_id' => $price->product->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // If no features found, use default features
                if (empty($features)) {
                    $features = $this->getDefaultFeatures();
                }

                // Get trial days from product metadata
                $trialDays = null;
                if (isset($price->product->metadata->trial_days)) {
                    $trialDays = (int) $price->product->metadata->trial_days;
                }

                // Add the plan to the list
                $plans[] = [
                    'id' => $price->id,
                    'name' => $name,
                    'price' => $amount,
                    'interval' => $interval,
                    'currency' => $currency,
                    'features' => $features,
                    'trial_days' => $trialDays
                ];
            }

            // If no plans were found, use fallback plans
            if (empty($plans)) {
                $plans = $this->getFallbackPlans();
            }

            return response()->json($plans);
        } catch (\Exception $e) {
            Log::error('Error fetching public plans: ' . $e->getMessage());

            // Return fallback plans on error
            $fallbackPlans = $this->getFallbackPlans();
            return response()->json($fallbackPlans);
        }
    }

    /**
     * Get empty array when Stripe API fails.
     * We no longer use hardcoded fallback plans.
     *
     * @return array
     */
    private function getFallbackPlans()
    {
        // Log the error for monitoring
        Log::warning('Using empty plans array as fallback because Stripe API failed');

        // Return empty array instead of hardcoded plans
        return [];
    }

    /**
     * Sync subscription status with Stripe.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function syncWithStripe()
    {
        $user = Auth::user();

        try {
            // Get the subscription type from the database or use 'default' as fallback
            $subscriptionType = $this->getSubscriptionType($user);

            // Check if the user has a subscription
            if ($user->subscribed($subscriptionType)) {
                $subscription = $user->subscription($subscriptionType);

                // Get the subscription from Stripe
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);

                Log::info('Syncing subscription with Stripe', [
                    'stripe_id' => $subscription->stripe_id,
                    'stripe_status' => $stripeSubscription->status,
                    'current_status' => $subscription->stripe_status
                ]);

                // Update the subscription status in the database
                if ($stripeSubscription->status !== $subscription->stripe_status) {
                    Log::info('Updating subscription status', [
                        'from' => $subscription->stripe_status,
                        'to' => $stripeSubscription->status
                    ]);

                    // Update the status
                    $subscription->stripe_status = $stripeSubscription->status;

                    // If the subscription is canceled in Stripe, update the ends_at date
                    if ($stripeSubscription->status === 'canceled' && !$subscription->ends_at) {
                        $subscription->ends_at = Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                    }

                    $subscription->save();
                }

                return redirect()->route('billing')
                    ->with('success', 'Subscription status has been synced with Stripe.');
            }

            return redirect()->route('billing')
                ->with('info', 'No active subscription found to sync.');

        } catch (\Exception $e) {
            Log::error('Error syncing subscription with Stripe: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('billing')
                ->withErrors(['error' => 'There was an error syncing your subscription with Stripe. Please try again.']);
        }
    }

    /**
     * Format subscription data for the frontend.
     *
     * @param  \Laravel\Cashier\Subscription|null  $subscription
     * @param  \Stripe\PaymentMethod|null  $paymentMethod
     * @param  array  $invoices
     * @return array
     */
    public function formatSubscription($subscription, $paymentMethod, $invoices)
    {
        if (!$subscription) {
            return null;
        }

        // Get plan details from Stripe
        Stripe::setApiKey(config('cashier.secret'));
        $stripePlan = \Stripe\Price::retrieve($subscription->stripe_price);
        $product = \Stripe\Product::retrieve($stripePlan->product);

        // Format invoices
        $formattedInvoices = [];
        foreach ($invoices as $invoice) {
            // Format the date with month name
            $date = $invoice->date()->format('F j, Y');

            // Format the amount with currency symbol
            try {
                // Log the raw invoice total for debugging
                $rawTotal = $invoice->total();
                Log::info('Raw invoice total', [
                    'invoice_id' => $invoice->id,
                    'raw_total' => $rawTotal,
                    'type' => gettype($rawTotal)
                ]);

                // If the amount already includes a currency symbol (e.g., '$20')
                if (is_string($rawTotal) && strpos($rawTotal, '$') === 0) {
                    // Remove the currency symbol and any commas
                    $cleanAmount = str_replace(['$', ','], '', $rawTotal);
                    // Convert to float and then to integer cents
                    $amount = (int)round(floatval($cleanAmount) * 100);
                    // Use the original string as the formatted amount
                    $formattedAmount = $rawTotal;
                } else {
                    // Handle as before - convert to integer and format
                    $amount = (int)$rawTotal;
                    $formattedAmount = '$' . number_format($amount / 100, 2);
                }
            } catch (\Exception $e) {
                // If there's an error formatting the amount, use a fallback
                Log::error('Error formatting invoice amount', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage()
                ]);
                $amount = 0;
                $formattedAmount = '$0.00';
            }

            $formattedInvoices[] = [
                'id' => $invoice->id,
                'date' => $date,
                'total' => $amount,
                'formatted_amount' => $formattedAmount,
                'status' => $invoice->status,
                'invoice_pdf' => $invoice->invoice_pdf,
            ];
        }

        // Get features based on the plan
        $features = $this->getPlanFeatures($stripePlan->id);

        // Get trial days from product metadata
        $trialDays = $this->getPlanTrialDays($stripePlan->id);

        // Check if the subscription is on grace period
        $onGracePeriod = $subscription->onGracePeriod();

        return [
            'name' => $product->name,
            'stripe_status' => $subscription->stripe_status,
            'ends_at' => $subscription->ends_at ? $subscription->ends_at->toIso8601String() : null,
            'trial_ends_at' => $subscription->trial_ends_at ? $subscription->trial_ends_at->toIso8601String() : null,
            'stripe_price' => $subscription->stripe_price,
            'price' => $stripePlan->unit_amount / 100,
            'interval' => $stripePlan->recurring->interval,
            'currency' => $stripePlan->currency,
            'quantity' => $subscription->quantity,
            'card_brand' => $paymentMethod ? $paymentMethod->card->brand : null,
            'card_last_four' => $paymentMethod ? $paymentMethod->card->last4 : null,
            'next_billing_date' => $this->getNextBillingDate($subscription) ?? $this->getFallbackBillingDate($subscription),
            'invoices' => $formattedInvoices,
            'features' => $features,
            'on_grace_period' => $onGracePeriod,
            'trial_days' => $trialDays,
        ];
    }

    /**
     * Get available subscription plans.
     *
     * @return array
     */
    public function getAvailablePlans()
    {
        // Fetch plans from Stripe
        try {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            $prices = \Stripe\Price::all(['active' => true, 'limit' => 10, 'expand' => ['data.product']]);

            $plans = [];
            foreach ($prices->data as $price) {
                // Only include prices with recurring payments
                if ($price->type === 'recurring') {
                    // Get trial days from product metadata
                    $trialDays = null;
                    if (isset($price->product->metadata->trial_days)) {
                        $trialDays = (int) $price->product->metadata->trial_days;
                    }

                    $plans[] = [
                        'id' => $price->id,
                        'name' => $price->product->name,
                        'price' => $price->unit_amount / 100,
                        'interval' => $price->recurring->interval,
                        'currency' => $price->currency,
                        'features' => $this->getPlanFeatures($price->id),
                        'trial_days' => $trialDays,
                    ];
                }
            }

            // If we found plans, return them
            if (count($plans) > 0) {
                return $plans;
            }
        } catch (\Exception $e) {
            Log::error('Error fetching Stripe plans: ' . $e->getMessage());
        }

        // Return empty array instead of hardcoded plans
        Log::warning('No plans found from Stripe API, returning empty array');
        return [];
    }

    /**
     * Get features for a specific plan.
     *
     * @param  string  $planId
     * @return array
     */
    private function getPlanFeatures($planId)
    {
        try {
            // Try to get plan details from Stripe
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Retrieve the price and product to check for metadata
            $price = \Stripe\Price::retrieve([
                'id' => $planId,
                'expand' => ['product']
            ]);

            // Check if product has metadata with features
            if (isset($price->product->metadata->features)) {
                try {
                    // Try to parse features from metadata
                    $featuresData = json_decode($price->product->metadata->features, true);
                    if (is_array($featuresData)) {
                        return $featuresData;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to parse features from product metadata', [
                        'product_id' => $price->product->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // If no features found in metadata, return default features
            return $this->getDefaultFeatures();

        } catch (\Exception $e) {
            Log::error('Error fetching plan features: ' . $e->getMessage());

            // Return default features instead of hardcoded ones
            Log::warning('Could not fetch plan features from Stripe, using default features');

            // Default fallback
            return $this->getDefaultFeatures();
        }
    }



    /**
     * Get default features for unknown plans.
     *
     * @return array
     */
    private function getDefaultFeatures()
    {
        return [
            ['name' => 'Basic features', 'included' => true],
            ['name' => 'Standard support', 'included' => true],
        ];
    }

    /**
     * Get trial days for a specific plan.
     *
     * @param  string  $planId
     * @return int
     */
    private function getPlanTrialDays($planId)
    {
        try {
            // Try to get plan details from Stripe
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Retrieve the price and product to check for metadata
            $price = \Stripe\Price::retrieve([
                'id' => $planId,
                'expand' => ['product']
            ]);

            // Check if product has metadata with trial days
            if (isset($price->product->metadata->trial_days)) {
                return (int) $price->product->metadata->trial_days;
            }

            // No trial days found
            return 0;

        } catch (\Exception $e) {
            Log::error('Error fetching plan trial days: ' . $e->getMessage());

            // Return 0 trial days on error
            return 0;
        }
    }

    /**
     * Get the next billing date for a subscription.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @return string|null
     */
    private function getNextBillingDate($subscription)
    {
        try {
            // Check if the subscription is active
            if (!$subscription->active()) {
                Log::info('Subscription not active, returning null for next billing date');
                return null;
            }

            // Get the current period end date from Stripe
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);

            // Log the Stripe subscription data for debugging
            Log::info('Stripe subscription data for next billing date', [
                'stripe_id' => $subscription->stripe_id,
                'current_period_end' => $stripeSubscription->current_period_end ?? 'not set',
                'status' => $stripeSubscription->status ?? 'unknown'
            ]);

            // Convert the timestamp to a Carbon instance and format it
            if (isset($stripeSubscription->current_period_end)) {
                $date = Carbon::createFromTimestamp($stripeSubscription->current_period_end)->toIso8601String();
                Log::info('Next billing date calculated', ['date' => $date]);
                return $date;
            }

            Log::info('No current_period_end found in Stripe subscription');
            return null;
        } catch (\Exception $e) {
            Log::error('Error getting next billing date: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get a fallback billing date when Stripe data is not available.
     * This calculates a date one month from now as a reasonable fallback.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @return string
     */
    private function getFallbackBillingDate($subscription)
    {
        Log::info('Using fallback billing date calculation');

        // If the subscription has a created_at date, use that as a base
        if ($subscription->created_at) {
            $baseDate = $subscription->created_at->copy();
        } else {
            // Otherwise use current date
            $baseDate = Carbon::now();
        }

        // Add one month to the base date
        $nextBillingDate = $baseDate->addMonth()->toIso8601String();

        Log::info('Fallback billing date calculated', ['date' => $nextBillingDate]);
        return $nextBillingDate;
    }

    /**
     * Get the subscription type for a user.
     * This method retrieves the subscription type from the database or returns 'default' as fallback.
     *
     * @param  \App\Models\User  $user
     * @return string
     */
    public function getSubscriptionType($user)
    {
        // Check if the user has any subscription
        $subscription = $user->subscriptions()->first();

        // If a subscription exists, return its type, otherwise return 'default'
        return $subscription ? $subscription->type : 'default';
    }
}
