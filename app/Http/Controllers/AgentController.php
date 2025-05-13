<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
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

        if ($user->hasDefaultPaymentMethod()) {
            $paymentMethod = $user->defaultPaymentMethod();

            if ($user->subscribed('default')) {
                $subscription = $user->subscription('default');

                // Format subscription data
                $subscription = $this->formatSubscription($subscription, $paymentMethod);
            }
        }

        // Always get available plans
        $plans = $this->getAvailablePlans();

        // Determine which page to render based on the route
        $routeName = request()->route()->getName();

        // Check if user has an active subscription or is on trial
        $hasActiveSubscription = $this->userHasActiveSubscription($user);

        if ($routeName === 'agents') {
            return Inertia::render('Agents', [
                'agents' => $agents,
                'subscription' => $subscription,
                'hasActiveSubscription' => $hasActiveSubscription,
            ]);
        }

        return Inertia::render('Dashboard', [
            'agents' => $agents,
            'subscription' => $subscription,
            'plans' => $plans,
        ]);
    }

    /**
     * Format subscription data for the frontend.
     *
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @param  \Stripe\PaymentMethod  $paymentMethod
     * @return array
     */
    private function formatSubscription($subscription, $paymentMethod)
    {
        // Get plan details from Stripe
        Stripe::setApiKey(config('cashier.secret'));
        $stripePlan = \Stripe\Price::retrieve($subscription->stripe_price);
        $product = \Stripe\Product::retrieve($stripePlan->product);

        // Get features based on the plan
        $features = $this->getPlanFeatures($stripePlan->id);

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
            'features' => $features,
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
                    $plans[] = [
                        'id' => $price->id,
                        'name' => $price->product->name,
                        'price' => $price->unit_amount / 100,
                        'interval' => $price->recurring->interval,
                        'currency' => $price->currency,
                        'features' => $this->getPlanFeatures($price->id),
                    ];
                }
            }

            // If we found plans, return them
            if (count($plans) > 0) {
                return $plans;
            }
        } catch (\Exception $e) {
            Log::error('Error fetching Stripe plans in AgentController: ' . $e->getMessage());
        }

        // Fallback to hardcoded plans if Stripe fetch fails
        return [
            [
                'id' => 'price_1RFkCl06DSRI9z5wXnLQZJnO',
                'name' => 'Starter',
                'price' => 29.99,
                'interval' => 'month',
                'currency' => 'usd',
                'features' => $this->getPlanFeatures('price_1RFkCl06DSRI9z5wXnLQZJnO'),
            ],
            [
                'id' => 'price_1RFkDK06DSRI9z5wJnLQZJnO',
                'name' => 'Professional',
                'price' => 79.99,
                'interval' => 'month',
                'currency' => 'usd',
                'features' => $this->getPlanFeatures('price_1RFkDK06DSRI9z5wJnLQZJnO'),
            ],
            [
                'id' => 'price_1RFkDr06DSRI9z5wJnLQZJnO',
                'name' => 'Enterprise',
                'price' => 199.99,
                'interval' => 'month',
                'currency' => 'usd',
                'features' => $this->getPlanFeatures('price_1RFkDr06DSRI9z5wJnLQZJnO'),
            ],
        ];
    }

    /**
     * Get features for a specific plan.
     *
     * @param  string  $planId
     * @return array
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

            // Get the product name
            $productName = $price->product->name;

            // Map features based on product name (case-insensitive)
            $productNameLower = strtolower($productName);

            if (strpos($productNameLower, 'starter') !== false) {
                return $this->getStarterFeatures();
            } elseif (strpos($productNameLower, 'professional') !== false) {
                return $this->getProfessionalFeatures();
            } elseif (strpos($productNameLower, 'enterprise') !== false) {
                return $this->getEnterpriseFeatures();
            }

            // If no specific match, return default features
            return $this->getDefaultFeatures();

        } catch (\Exception $e) {
            Log::error('Error fetching plan features: ' . $e->getMessage());

            // Fallback to hardcoded features based on price ID patterns
            // This is a backup in case the Stripe API call fails
            if (strpos($planId, '1RFkCl') !== false) {
                return $this->getStarterFeatures();
            } elseif (strpos($planId, '1RFkDK') !== false) {
                return $this->getProfessionalFeatures();
            } elseif (strpos($planId, '1RFkDr') !== false) {
                return $this->getEnterpriseFeatures();
            }

            // Default fallback
            return $this->getDefaultFeatures();
        }
    }

    /**
     * Get features for the Starter plan.
     *
     * @return array
     */
    private function getStarterFeatures()
    {
        return [
            ['name' => '1 AI Agent', 'included' => true],
            ['name' => '5,000 interactions per month', 'included' => true],
            ['name' => 'Basic analytics', 'included' => true],
            ['name' => 'Email support', 'included' => true],
            ['name' => 'File uploads (up to 50MB)', 'included' => true],
            ['name' => 'Website training', 'included' => true],
            ['name' => 'API access', 'included' => false],
            ['name' => 'Custom branding', 'included' => false],
            ['name' => 'Advanced analytics', 'included' => false],
            ['name' => 'Priority support', 'included' => false],
        ];
    }

    /**
     * Get features for the Professional plan.
     *
     * @return array
     */
    private function getProfessionalFeatures()
    {
        return [
            ['name' => '5 AI Agents', 'included' => true],
            ['name' => '25,000 interactions per month', 'included' => true],
            ['name' => 'Advanced analytics', 'included' => true],
            ['name' => 'Priority email support', 'included' => true],
            ['name' => 'File uploads (up to 200MB)', 'included' => true],
            ['name' => 'Website training', 'included' => true],
            ['name' => 'API access', 'included' => true],
            ['name' => 'Custom branding', 'included' => true],
            ['name' => 'Team collaboration', 'included' => false],
            ['name' => '24/7 phone support', 'included' => false],
        ];
    }

    /**
     * Get features for the Enterprise plan.
     *
     * @return array
     */
    private function getEnterpriseFeatures()
    {
        return [
            ['name' => 'Unlimited AI Agents', 'included' => true],
            ['name' => 'Unlimited interactions', 'included' => true],
            ['name' => 'Advanced analytics & reporting', 'included' => true],
            ['name' => '24/7 priority support', 'included' => true],
            ['name' => 'Unlimited file uploads', 'included' => true],
            ['name' => 'Website & API training', 'included' => true],
            ['name' => 'Advanced API access', 'included' => true],
            ['name' => 'Custom branding & white labeling', 'included' => true],
            ['name' => 'Team collaboration', 'included' => true],
            ['name' => 'Dedicated account manager', 'included' => true],
        ];
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
            return true;
        }

        // Get the subscription
        $subscription = $user->subscription();

        // No subscription
        if (!$subscription) {
            return false;
        }

        // Check if the subscription is on grace period (canceled but still valid until the end date)
        // This is the key check for canceled subscriptions that are still running
        if ($subscription->onGracePeriod()) {
            return true;
        }

        // Check the Stripe status directly
        if (isset($subscription->stripe_status)) {
            // Valid statuses that allow access
            $validStatuses = ['active', 'trialing'];

            // If the status is one of the valid ones, allow access
            if (in_array($subscription->stripe_status, $validStatuses)) {
                return true;
            }
        }

        // Fallback to the Laravel Cashier methods if stripe_status is not available

        // Check if the subscription is active (not expired) and not canceled
        if ($subscription->active() && !$subscription->canceled()) {
            return true;
        }

        // If we get here, the subscription is not valid
        return false;
    }

    /**
     * Get the agent limit based on the user's subscription plan.
     *
     * @param  \App\Models\User  $user
     * @return int
     */
    private function getAgentLimit($user)
    {
        // Default limit for free users or unknown plans
        $defaultLimit = 0;

        // If user is on trial, give them the Professional plan limit
        if ($user->onTrial()) {
            return 5; // Professional plan limit
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

            // Get the product name
            $productName = $stripePlan->product->name ?? '';
            $productNameLower = strtolower($productName);

            // Determine the limit based on the plan name
            if (strpos($productNameLower, 'starter') !== false) {
                return 1; // Starter plan: 1 agent
            } elseif (strpos($productNameLower, 'professional') !== false) {
                return 5; // Professional plan: 5 agents
            } elseif (strpos($productNameLower, 'enterprise') !== false) {
                return PHP_INT_MAX; // Enterprise plan: unlimited agents
            }

            // Fallback to default limit if plan name doesn't match
            return $defaultLimit;

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


}
