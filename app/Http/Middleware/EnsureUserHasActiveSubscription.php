<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Check if user has an active subscription or is on trial
        if (!$this->userHasActiveSubscription($request->user())) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Subscription required',
                    'subscription_required' => true
                ], 402); // 402 Payment Required
            }

            return redirect()->route('billing')->with('error', 'You need an active subscription to access this feature.');
        }

        return $next($request);
    }

    /**
     * Determine if the user has an active subscription or is on trial.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    protected function userHasActiveSubscription($user): bool
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
    protected function getStripeSubscriptionStatus($subscription): ?string
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
    protected function updateSubscriptionStatus($subscription, $stripeStatus): void
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
}
