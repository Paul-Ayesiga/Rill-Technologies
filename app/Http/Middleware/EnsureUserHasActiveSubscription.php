<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
}
