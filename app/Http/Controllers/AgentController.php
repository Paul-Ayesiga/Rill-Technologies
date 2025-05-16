<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Laravel\Cashier\Billable;
use Stripe\Stripe;

class AgentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $agents = $user->agents()->latest()->get();

        // Get subscription data
        $subscription = null;

        try {
            if ($user->hasDefaultPaymentMethod()) {
                try {
                    $paymentMethod = $user->defaultPaymentMethod();
                } catch (\Stripe\Exception\ApiConnectionException $e) {
                    // Handle Stripe connection error with a meaningful message
                    Log::error('Unable to connect to payment service: ' . $e->getMessage());

                    // Add a flash message for the user
                    session()->flash('warning', 'Unable to connect to payment service. Your subscription information will be displayed from local data. Some features may be limited until connection is restored.');

                    // Continue without payment method
                    $paymentMethod = null;
                }

                // Get the subscription type from the database or use 'default' as fallback
                $subscriptionType = $this->getSubscriptionType($user);

                if ($user->subscribed($subscriptionType)) {
                    $subscription = $user->subscription($subscriptionType);

                    // Format subscription data with error handling
                    try {
                        $subscription = $this->formatSubscription($subscription, $paymentMethod);
                    } catch (\Stripe\Exception\ApiConnectionException $e) {
                        // Handle Stripe connection error with a meaningful message
                        Log::error('Unable to connect to payment service when retrieving subscription details: ' . $e->getMessage());

                        // If we haven't already shown a warning, show one now
                        if (!session()->has('warning')) {
                            session()->flash('warning', 'Unable to connect to payment service. Your subscription information will be displayed from local data. Some features may be limited until connection is restored.');
                        }

                        // Create a fallback subscription object with data from the database
                        $subscription = $this->createFallbackSubscription($subscription, $paymentMethod);
                    }
                }
            }
        } catch (\Exception $e) {
            // Handle any other exceptions with a meaningful message
            Log::error('Error retrieving subscription data: ' . $e->getMessage());

            // Add a flash message for the user
            session()->flash('error', 'We encountered an issue retrieving your subscription information. Please try again later or contact support if the problem persists.');

            // Continue without subscription data
            $subscription = null;
        }

        // Get available plans
        $plans = $this->getAvailablePlans();

        // If plans is null, it means there was a connection error
        // We'll still render the page, but the frontend will show a message
        $connectionError = $plans === null;

        // If there was a connection error, set plans to an empty array
        if ($connectionError) {
            $plans = [];
        }

        // Determine which page to render based on the route
        $routeName = request()->route()->getName();

        // Check if user has an active subscription or is on trial
        $hasActiveSubscription = $this->userHasActiveSubscription($user);

        if ($routeName === 'agents') {
            return Inertia::render('Agents', [
                'agents' => $agents,
                'subscription' => $subscription,
                'hasActiveSubscription' => $hasActiveSubscription,
                'connectionError' => $connectionError,
            ]);
        }

        return Inertia::render('Dashboard', [
            'agents' => $agents,
            'subscription' => $subscription,
            'plans' => $plans,
            'connectionError' => $connectionError,
        ]);
    }

    /**
     * Format subscription data for the frontend.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @param  \Stripe\PaymentMethod|null  $paymentMethod
     * @return array
     * @throws \Stripe\Exception\ApiConnectionException
     */
    private function formatSubscription($subscription, $paymentMethod = null)
    {
        // Get plan details from Stripe
        Stripe::setApiKey(config('cashier.secret'));

        try {
            $stripePlan = \Stripe\Price::retrieve($subscription->stripe_price);
            $product = \Stripe\Product::retrieve($stripePlan->product);

            // Get features based on the plan
            $features = $this->getPlanFeatures($stripePlan->id);

            // Get card details if available
            $cardBrand = null;
            $cardLastFour = null;

            if ($paymentMethod) {
                try {
                    $cardBrand = $paymentMethod->card->brand;
                    $cardLastFour = $paymentMethod->card->last4;
                } catch (\Exception $e) {
                    Log::warning('Could not get card details from payment method: ' . $e->getMessage());
                }
            }

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
                'card_brand' => $cardBrand,
                'card_last_four' => $cardLastFour,
                'next_billing_date' => $this->getNextBillingDate($subscription) ?? $this->getFallbackBillingDate($subscription),
                'features' => $features,
                'offline_mode' => false, // Flag to indicate this is online data
            ];
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Let the caller handle this specific exception
            throw $e;
        } catch (\Exception $e) {
            // For other exceptions, log and return a fallback
            Log::error('Error formatting subscription: ' . $e->getMessage());
            return $this->createFallbackSubscription($subscription, $paymentMethod);
        }
    }

    /**
     * Get available subscription plans.
     *
     * @return array|null
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
                    // Get features for this plan
                    $features = $this->getPlanFeatures($price->id);

                    // If features is null, it means there was a connection error
                    // Skip this plan and continue with the next one
                    if ($features === null) {
                        continue;
                    }

                    $plans[] = [
                        'id' => $price->id,
                        'name' => $price->product->name,
                        'price' => $price->unit_amount / 100,
                        'interval' => $price->recurring->interval,
                        'currency' => $price->currency,
                        'features' => $features,
                    ];
                }
            }

            // If we found plans, return them
            if (count($plans) > 0) {
                return $plans;
            }

            // If no plans were found, return an empty array
            return [];

        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Handle Stripe connection error specifically
            Log::warning('Unable to connect to payment service when fetching plans: ' . $e->getMessage());

            // Add a flash message for the user
            session()->flash('error', 'Unable to connect to payment service. Subscription management is unavailable until connection is restored.');

            // Return null to indicate a connection error
            return null;
        } catch (\Exception $e) {
            Log::error('Error fetching Stripe plans in AgentController: ' . $e->getMessage());

            // Return an empty array for other errors
            return [];
        }
    }



    /**
     * Get features for a specific plan.
     *
     * @param  string  $planId
     * @return array|null
     */
    public function getPlanFeatures($planId)
    {
        try {
            // First, get the plan details to determine its name
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
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

            // If no features found in metadata, return an empty array
            // This indicates that we couldn't find features but the connection was successful
            return [];

        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Handle Stripe connection error
            Log::error('Unable to connect to payment service when fetching plan features: ' . $e->getMessage());

            // Return null to indicate a connection error
            // The caller should handle this by showing a toast notification
            return null;
        } catch (\Exception $e) {
            Log::error('Error fetching plan features: ' . $e->getMessage());

            // Return an empty array for other errors
            return [];
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
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Handle Stripe connection error with a meaningful message
            Log::warning('Unable to connect to payment service when retrieving next billing date: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id
            ]);

            // If we haven't already shown a warning, show one now
            if (!session()->has('warning')) {
                session()->flash('warning', 'Unable to connect to payment service. Your next billing date is estimated based on your subscription start date.');
            }

            // Return null to trigger the fallback billing date calculation
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // First check if the user has an active subscription
        if (!$this->userHasActiveSubscription($user)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "You need an active subscription to create agents.",
                    'subscription_required' => true
                ], 402); // 402 Payment Required
            }

            return redirect()->route('billing')->with('error', "You need an active subscription to create agents. Please subscribe to a plan.");
        }

        // Then check subscription limits based on plan
        $agentLimit = $this->getAgentLimit($user);

        // If agentLimit is null, it means there was a connection error
        if ($agentLimit === null) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Unable to connect to payment service. Agent creation is unavailable until connection is restored.",
                    'connection_error' => true
                ], 503); // 503 Service Unavailable
            }

            return redirect()->back()->with('error', "Unable to connect to payment service. Agent creation is unavailable until connection is restored.");
        }

        $currentAgentCount = $user->agents()->count();

        if ($currentAgentCount >= $agentLimit) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "You've reached your plan's limit of {$agentLimit} agents. Please upgrade your plan to create more agents.",
                    'limit_reached' => true
                ], 403);
            }

            return redirect()->route('billing')->with('error', "You've reached your plan's limit of {$agentLimit} agents. Please upgrade your plan to create more agents.");
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
        ]);

        $agent = $user->agents()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'status' => 'training',
            'interactions' => 0,
            'performance' => 0,
            'last_active_at' => null, // Explicitly set to null for new agents
        ]);

        // Load the agent with its relationships
        $agent->load('user');

        // Store the agent in the session for the frontend
        session()->put('agent', $agent);

        // Set the last_active attribute
        $agent->append('last_active');

        if ($request->wantsJson()) {
            return response()->json(['agent' => $agent]);
        }

        // Redirect to agents page if the request came from there
        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, '/agents')) {
            return redirect()->route('agents')->with('agent', $agent);
        }

        return redirect()->route('dashboard')->with('agent', $agent);
    }

    /**
     * Determine if the user has an active subscription or is on trial.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    private function userHasActiveSubscription($user): bool
    {
        // Check if user is on trial
        if ($user->onTrial()) {
            Log::info('User is on trial', ['user_id' => $user->id]);
            return true;
        }

        // Get the subscription
        $subscription = $user->subscription();

        // No subscription
        if (!$subscription) {
            Log::info('User has no subscription', ['user_id' => $user->id]);
            return false;
        }

        // Check if the subscription is on grace period (canceled but still valid until the end date)
        if ($subscription->onGracePeriod()) {
            Log::info('User subscription is on grace period', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'ends_at' => $subscription->ends_at
            ]);
            return true;
        }

        // Check the database status
        $dbStatus = $subscription->stripe_status ?? null;
        $validStatuses = ['active', 'trialing'];

        // If the database status is not valid, check Stripe directly
        if (!in_array($dbStatus, $validStatuses)) {
            Log::info('Database subscription status is not valid', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'db_status' => $dbStatus
            ]);
            return false;
        }

        // Now verify with Stripe to ensure the statuses match
        $stripeStatus = $this->getStripeSubscriptionStatus($subscription);

        // If we couldn't get the Stripe status, fall back to the database status
        if ($stripeStatus === null) {
            Log::warning('Could not verify subscription status with Stripe, using database status', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'db_status' => $dbStatus
            ]);
            return in_array($dbStatus, $validStatuses);
        }

        // Check if both statuses are valid and match
        $statusesMatch = $dbStatus === $stripeStatus;
        $stripeStatusValid = in_array($stripeStatus, $validStatuses);

        if (!$statusesMatch) {
            Log::warning('Subscription status mismatch between database and Stripe', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'db_status' => $dbStatus,
                'stripe_status' => $stripeStatus
            ]);

            // If the statuses don't match, update the database
            $this->updateSubscriptionStatus($subscription, $stripeStatus);
        }

        // Both database and Stripe must have valid statuses
        $isValid = $stripeStatusValid && in_array($dbStatus, $validStatuses);

        Log::info('Subscription status check result', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'db_status' => $dbStatus,
            'stripe_status' => $stripeStatus,
            'statuses_match' => $statusesMatch,
            'is_valid' => $isValid
        ]);

        return $isValid;
    }

    /**
     * Get the subscription status directly from Stripe.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @return string|null
     */
    private function getStripeSubscriptionStatus($subscription): ?string
    {
        // Use cache to avoid excessive API calls
        $cacheKey = 'stripe_subscription_status_' . $subscription->stripe_id;

        // Check if we have a cached status (cache for 5 minutes)
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Set Stripe API key
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Get the subscription from Stripe
            $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);

            // Cache the status for 5 minutes
            Cache::put($cacheKey, $stripeSubscription->status, now()->addMinutes(5));

            return $stripeSubscription->status;
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Handle Stripe connection error with a meaningful message
            Log::warning('Unable to connect to payment service when verifying subscription status: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id
            ]);

            // If we haven't already shown a warning, show one now
            if (!session()->has('warning')) {
                session()->flash('warning', 'Unable to connect to payment service. Your subscription status will be verified using local data only.');
            }

            // Return the database status as a fallback
            return $subscription->stripe_status;
        } catch (\Exception $e) {
            Log::error('Error fetching subscription status from Stripe', [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Update the subscription status in the database.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @param  string  $stripeStatus
     * @return void
     */
    private function updateSubscriptionStatus($subscription, $stripeStatus): void
    {
        try {
            $subscription->stripe_status = $stripeStatus;
            $subscription->save();

            Log::info('Updated subscription status in database', [
                'subscription_id' => $subscription->id,
                'new_status' => $stripeStatus
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating subscription status in database', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the agent limit based on the user's subscription plan.
     *
     * @param  \App\Models\User  $user
     * @return int|null
     */
    private function getAgentLimit($user)
    {
        // Default limit for free users or unknown plans
        $defaultLimit = 0;

        // If user is on trial, we need to check the trial plan's limit
        if ($user->onTrial()) {
            try {
                // Get the trial subscription
                $subscription = $user->subscription();
                if (!$subscription) {
                    return $defaultLimit;
                }

                // Get the plan details from Stripe
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $stripePlan = \Stripe\Price::retrieve([
                    'id' => $subscription->stripe_price,
                    'expand' => ['product']
                ]);

                // Try to get agent limit from product metadata
                if (isset($stripePlan->product->metadata->features)) {
                    try {
                        $features = json_decode($stripePlan->product->metadata->features, true);
                        if (is_array($features)) {
                            // Look for an "Agents" feature
                            foreach ($features as $feature) {
                                if (isset($feature['name']) && strtolower($feature['name']) === 'agents' &&
                                    isset($feature['value']) && $feature['included']) {

                                    // Extract the number from the value (e.g., "5 agents" -> 5)
                                    $value = $feature['value'];
                                    if (preg_match('/(\d+)/', $value, $matches)) {
                                        return (int) $matches[1];
                                    } elseif (stripos($value, 'unlimited') !== false) {
                                        return PHP_INT_MAX;
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to parse features from product metadata for trial user', [
                            'product_id' => $stripePlan->product->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // If we couldn't get the limit from metadata, return null
                Log::warning('Could not determine agent limit for trial user');
                session()->flash('error', 'Unable to determine your trial plan\'s agent limit. Agent creation is unavailable until connection is restored.');
                return null;

            } catch (\Stripe\Exception\ApiConnectionException $e) {
                // Handle Stripe connection error
                Log::warning('Unable to connect to payment service when determining trial user agent limit: ' . $e->getMessage());

                // Add a flash message for the user
                session()->flash('error', 'Unable to connect to payment service. Agent creation is unavailable until connection is restored.');

                // Return null to indicate a connection error
                return null;
            } catch (\Exception $e) {
                Log::error('Error determining trial user agent limit: ' . $e->getMessage());
                return $defaultLimit;
            }
        }

        // If user doesn't have an active subscription, return the default limit
        if (!$this->userHasActiveSubscription($user)) {
            return $defaultLimit;
        }

        // Get the subscription
        $subscription = $user->subscription();
        if (!$subscription) {
            return $defaultLimit;
        }

        try {
            // Get the plan details from Stripe
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            $stripePlan = \Stripe\Price::retrieve([
                'id' => $subscription->stripe_price,
                'expand' => ['product']
            ]);

            // Try to get agent limit from product metadata
            if (isset($stripePlan->product->metadata->features)) {
                try {
                    $features = json_decode($stripePlan->product->metadata->features, true);
                    if (is_array($features)) {
                        // Look for an "Agents" feature
                        foreach ($features as $feature) {
                            if (isset($feature['name']) && strtolower($feature['name']) === 'agents' &&
                                isset($feature['value']) && $feature['included']) {

                                // Extract the number from the value (e.g., "5 agents" -> 5)
                                $value = $feature['value'];
                                if (preg_match('/(\d+)/', $value, $matches)) {
                                    return (int) $matches[1];
                                } elseif (stripos($value, 'unlimited') !== false) {
                                    return PHP_INT_MAX;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to parse features from product metadata when determining agent limit', [
                        'product_id' => $stripePlan->product->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // If we couldn't get the limit from metadata, we can't determine the limit
            // Return null to indicate a connection error
            Log::warning('Could not determine agent limit from Stripe metadata');

            // Add a flash message for the user
            session()->flash('error', 'Unable to determine your plan\'s agent limit. Agent creation is unavailable until connection is restored.');

            return null;

        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Handle Stripe connection error
            Log::warning('Unable to connect to payment service when determining agent limit: ' . $e->getMessage());

            // Add a flash message for the user
            session()->flash('error', 'Unable to connect to payment service. Agent creation is unavailable until connection is restored.');

            // Return null to indicate a connection error
            return null;
        } catch (\Exception $e) {
            Log::error('Error determining agent limit: ' . $e->getMessage());
            return $defaultLimit;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Agent $agent)
    {
        // Check if the agent belongs to the authenticated user
        if ($agent->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:active,paused,training',
        ]);

        // If status is changing to active, update last_active_at
        if (isset($validated['status']) && $validated['status'] === 'active' && $agent->status !== 'active') {
            $validated['last_active_at'] = now();
        }

        $agent->update($validated);

        // Refresh the agent to get the updated data
        $agent->refresh();

        // Set the last_active attribute
        $agent->append('last_active');

        if ($request->wantsJson()) {
            return response()->json(['agent' => $agent]);
        }

        return redirect()->route('dashboard');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Agent $agent)
    {
        // Check if the agent belongs to the authenticated user
        if ($agent->user_id !== Auth::id()) {
            abort(403);
        }

        $agent->delete();

        // Redirect to agents page if the request came from there
        $referer = request()->headers->get('referer');
        if ($referer && str_contains($referer, '/agents')) {
            return redirect()->route('agents');
        }

        return redirect()->route('dashboard');
    }

    /**
     * Toggle the status of the agent between active and paused.
     */
    public function toggleStatus(Request $request, Agent $agent)
    {
        // Check if the agent belongs to the authenticated user
        if ($agent->user_id !== Auth::id()) {
            abort(403);
        }

        // Toggle between active and paused
        $newStatus = $agent->status === 'active' ? 'paused' : 'active';

        // Don't change if in training
        if ($agent->status === 'training') {
            $newStatus = 'training';
        }

        // Update last_active_at timestamp if the agent is being activated
        $updateData = ['status' => $newStatus];
        if ($newStatus === 'active') {
            $updateData['last_active_at'] = now();
        }

        $agent->update($updateData);

        // Refresh the agent to get the updated data
        $agent->refresh();

        // Set the last_active attribute
        $agent->append('last_active');

        if ($request->wantsJson()) {
            return response()->json(['agent' => $agent]);
        }

        // Redirect to agents page if the request came from there
        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, '/agents')) {
            // Use flash data to pass the agent to the frontend
            session()->flash('agent', $agent);
            return redirect()->route('agents');
        }

        // Use flash data to pass the agent to the frontend
        session()->flash('agent', $agent);
        return redirect()->route('dashboard');
    }



    /**
     * Get the subscription type for a user.
     * This method retrieves the subscription type from the database or returns 'default' as fallback.
     *
     * @param  \App\Models\User  $user
     * @return string
     */
    private function getSubscriptionType($user)
    {
        // Check if the user has any subscription
        $subscription = $user->subscriptions()->first();

        // If a subscription exists, return its type, otherwise return 'default'
        return $subscription ? $subscription->type : 'default';
    }

    /**
     * Create a fallback subscription object when Stripe API is unavailable.
     * This uses data from the database to provide basic subscription information.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @param  \Stripe\PaymentMethod|null  $paymentMethod
     * @return array
     */
    private function createFallbackSubscription($subscription, $paymentMethod = null)
    {
        Log::info('Creating fallback subscription object from database data');

        // Get the card details if available
        $cardBrand = null;
        $cardLastFour = null;

        if ($paymentMethod) {
            try {
                $cardBrand = $paymentMethod->card->brand;
                $cardLastFour = $paymentMethod->card->last4;
            } catch (\Exception $e) {
                Log::warning('Could not get card details from payment method: ' . $e->getMessage());
            }
        }

        // Use the database information to determine the subscription details
        // If we don't have specific information, use generic placeholders
        $planName = 'Your Subscription';
        $price = null; // We'll show "Unavailable" in the UI instead of a specific price
        $interval = $subscription->stripe_status === 'trialing' ? 'trial' : 'month';

        // Try to get a more specific plan name if possible
        if ($subscription->name) {
            $planName = $subscription->name;
        }

        // Empty features array - we don't want to use hardcoded values
        $features = [];

        // Create a fallback subscription array with data from the database
        return [
            'name' => $planName,
            'stripe_status' => $subscription->stripe_status,
            'ends_at' => $subscription->ends_at ? $subscription->ends_at->toIso8601String() : null,
            'trial_ends_at' => $subscription->trial_ends_at ? $subscription->trial_ends_at->toIso8601String() : null,
            'stripe_price' => $subscription->stripe_price,
            'price' => $price,
            'interval' => $interval,
            'currency' => 'usd',
            'quantity' => $subscription->quantity,
            'card_brand' => $cardBrand,
            'card_last_four' => $cardLastFour,
            'next_billing_date' => $this->getFallbackBillingDate($subscription),
            'features' => $features,
            'offline_mode' => true, // Flag to indicate this is offline data
            'connection_error' => true, // Flag to indicate there was a connection error
            'connection_error_message' => 'Unable to connect to payment service. Some subscription details may be limited until connection is restored.'
        ];
    }




}
