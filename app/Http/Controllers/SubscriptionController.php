<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Exception\CardException;
use Stripe\PaymentMethod;
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

        if ($user->subscribed('default')) {
            $subscription = $user->subscription('default');
            $invoices = $user->invoices();
        }

        // Format subscription data for the frontend
        $formattedSubscription = $this->formatSubscription($subscription, $paymentMethod, $invoices);

        // Get available plans
        $plans = $this->getAvailablePlans();

        return Inertia::render('Subscription', [
            'subscription' => $formattedSubscription,
            'plans' => $plans,
        ]);
    }

    /**
     * Subscribe the user to a plan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'plan' => 'required|string',
            'payment_method' => 'required|string',
        ]);

        $user = Auth::user();
        $paymentMethod = $request->payment_method;
        $planId = $request->plan;

        try {
            // If the user already has a subscription, we'll swap the plan
            if ($user->subscribed('default')) {
                $user->subscription('default')->swap($planId);
            } else {
                // Create a new subscription
                $user->newSubscription('default', $planId)
                    ->create($paymentMethod);
            }

            return redirect()->route('subscription.index')
                ->with('success', 'Subscription created successfully!');
        } catch (IncompletePayment $exception) {
            return redirect()->route('cashier.payment', [
                $exception->payment->id, 'redirect' => route('subscription.index')
            ]);
        } catch (CardException $exception) {
            return back()->withErrors(['error' => $exception->getMessage()]);
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

            return redirect()->route('subscription.index')
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

        if ($user->subscribed('default')) {
            // Store cancellation reason if provided
            if ($request->has('reason') && !empty($request->reason)) {
                // You could store this in a database table if needed
                // For now, we'll just log it
                Log::info('Subscription cancelled. Reason: ' . $request->reason);
            }

            // Determine cancellation type
            if ($request->cancel_type === 'immediately') {
                // Cancel the subscription immediately
                $user->subscription('default')->cancelNow();
                $message = 'Your subscription has been cancelled immediately.';
            } else {
                // Cancel the subscription at the end of the billing period
                $user->subscription('default')->cancel();
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

        if ($user->subscription('default')->cancelled()) {
            $user->subscription('default')->resume();

            return redirect()->route('subscription.index')
                ->with('success', 'Your subscription has been resumed.');
        }

        return back()->withErrors(['error' => 'Your subscription cannot be resumed.']);
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
            // Check if the user has a subscription
            if ($user->subscribed('default')) {
                $subscription = $user->subscription('default');

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
            $formattedInvoices[] = [
                'id' => $invoice->id,
                'date' => $invoice->date()->toIso8601String(),
                'amount' => $invoice->total(),
                'status' => $invoice->status,
                'invoice_pdf' => $invoice->invoice_pdf,
            ];
        }

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
            'invoices' => $formattedInvoices,
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
            Log::error('Error fetching Stripe plans: ' . $e->getMessage());
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
    private function getPlanFeatures($planId)
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
}
