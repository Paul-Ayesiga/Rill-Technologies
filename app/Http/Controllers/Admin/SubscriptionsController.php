<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Cashier\Subscription;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class SubscriptionsController extends Controller
{
    /**
     * Display a listing of all subscriptions.
     */
    public function index()
    {
        // Get all subscriptions with their users
        $subscriptions = Subscription::with('user')
            ->latest()
            ->get()
            ->map(function ($subscription) {
                $user = $subscription->user;

                // Get plan name from Stripe if possible
                $planName = $subscription->type; // Use type as fallback
                if ($subscription->stripe_price) {
                    try {
                        \Stripe\Stripe::setApiKey(config('cashier.secret'));
                        $price = \Stripe\Price::retrieve([
                            'id' => $subscription->stripe_price,
                            'expand' => ['product']
                        ]);

                        if (isset($price->product->name)) {
                            $planName = $price->product->name;

                            // Check if the product is archived (not active)
                            if (isset($price->product->active) && $price->product->active === false) {
                                $planName .= ' (Archived)';
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Could not fetch plan name from Stripe: ' . $e->getMessage(), [
                            'subscription_id' => $subscription->id,
                            'stripe_price' => $subscription->stripe_price
                        ]);
                    }
                }

                return [
                    'id' => $subscription->id,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'name' => $planName,
                    'stripe_id' => $subscription->stripe_id,
                    'stripe_status' => $subscription->stripe_status,
                    'stripe_price' => $subscription->stripe_price,
                    'quantity' => $subscription->quantity,
                    'trial_ends_at' => $subscription->trial_ends_at ? $subscription->trial_ends_at->format('F j, Y') . ' (' . $subscription->trial_ends_at->diffForHumans() . ')' : null,
                    'ends_at' => $subscription->ends_at ? $subscription->ends_at->format('F j, Y') . ' (' . $subscription->ends_at->diffForHumans() . ')' : null,
                    'created_at' => $subscription->created_at->format('F j, Y') . ' (' . $subscription->created_at->diffForHumans() . ')',
                ];
            });

        // Get subscription stats
        $stats = [
            'total' => Subscription::count(),
            'active' => Subscription::where('stripe_status', 'active')->count(),
            'trialing' => Subscription::where('stripe_status', 'trialing')->count(),
            'canceled' => Subscription::where('stripe_status', 'canceled')->count(),
            'past_due' => Subscription::where('stripe_status', 'past_due')->count(),
            'incomplete' => Subscription::where('stripe_status', 'incomplete')->count(),
            'incomplete_expired' => Subscription::where('stripe_status', 'incomplete_expired')->count(),
        ];

        // Get available plans from Stripe
        $plans = $this->getAvailablePlans();

        return Inertia::render('admin/Subscriptions/Index', [
            'subscriptions' => Inertia::defer(fn () => $subscriptions),
            'stats' => $stats,
            'plans' => $plans,
        ]);
    }

    /**
     * Display the specified subscription.
     */
    public function show($id)
    {
        $subscription = Subscription::with('user')->findOrFail($id);
        $user = $subscription->user;

        // Get plan name from Stripe if possible
        $planName = $subscription->type; // Use type as fallback
        if ($subscription->stripe_price) {
            try {
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $price = \Stripe\Price::retrieve([
                    'id' => $subscription->stripe_price,
                    'expand' => ['product']
                ]);

                if (isset($price->product->name)) {
                    $planName = $price->product->name;
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch plan name from Stripe in show method: ' . $e->getMessage(), [
                    'subscription_id' => $subscription->id,
                    'stripe_price' => $subscription->stripe_price
                ]);
            }
        }

        // Check if the subscription is on grace period
        $userSubscription = $user->subscription($subscription->type);
        $onGracePeriod = $userSubscription ? $userSubscription->onGracePeriod() : false;

        // Get subscription data
        $subscriptionData = [
            'id' => $subscription->id,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->format('F j, Y') . ' (' . $user->created_at->diffForHumans() . ')',
            ],
            'name' => $planName,
            'stripe_id' => $subscription->stripe_id,
            'stripe_status' => $subscription->stripe_status,
            'stripe_price' => $subscription->stripe_price,
            'quantity' => $subscription->quantity,
            'trial_ends_at' => $subscription->trial_ends_at ? $subscription->trial_ends_at->format('F j, Y') . ' (' . $subscription->trial_ends_at->diffForHumans() . ')' : null,
            'ends_at' => $subscription->ends_at ? $subscription->ends_at->format('F j, Y') . ' (' . $subscription->ends_at->diffForHumans() . ')' : null,
            'created_at' => $subscription->created_at->format('F j, Y') . ' (' . $subscription->created_at->diffForHumans() . ')',
            'on_grace_period' => $onGracePeriod,
        ];

        // Get payment method if available
        $paymentMethod = null;
        if ($user->hasDefaultPaymentMethod()) {
            $pm = $user->defaultPaymentMethod();
            $paymentMethod = [
                'brand' => $pm->card->brand,
                'last4' => $pm->card->last4,
                'exp_month' => $pm->card->exp_month,
                'exp_year' => $pm->card->exp_year,
            ];
        }

        // Get invoices
        $invoices = $user->invoicesIncludingPending()->map(function ($invoice) {
            $date = $invoice->date();
            $total = $invoice->total();

            // Log the invoice total for debugging
            Log::info('Invoice total', [
                'invoice_id' => $invoice->id,
                'total' => $total,
                'total_type' => gettype($total)
            ]);

            // Check if total is already a formatted string (e.g., "$29.99")
            if (is_string($total) && strpos($total, '$') !== false) {
                // Extract the numeric value
                $numericValue = (float) preg_replace('/[^0-9.]/', '', $total);
                if ($numericValue > 0) {
                    // Convert to cents for consistency with our formatting function
                    $total = $numericValue * 100;
                    Log::info('Converted formatted string to number', [
                        'invoice_id' => $invoice->id,
                        'original' => $total,
                        'converted' => $numericValue,
                        'cents' => $total
                    ]);
                }
            }

            // If still not valid, set to 0
            if ($total === null || !is_numeric($total)) {
                $total = 0;
            }

            return [
                'id' => $invoice->id,
                'total' => $total,
                'date' => $date->format('F j, Y') . ' (' . $date->diffForHumans() . ')',
                'status' => $invoice->status,
            ];
        });

        return Inertia::render('admin/Subscriptions/Show', [
            'subscription' => $subscriptionData,
            'paymentMethod' => $paymentMethod,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Request $request, $id)
    {
        $subscription = Subscription::findOrFail($id);
        $user = $subscription->user;

        try {
            // Check if the user has the subscription with this type
            $userSubscription = $user->subscription($subscription->type);

            if (!$userSubscription) {
                Log::error('Subscription not found for user', [
                    'user_id' => $user->id,
                    'subscription_type' => $subscription->type,
                    'subscription_id' => $subscription->id
                ]);
                return back()->with('error', 'Subscription not found for this user. Try syncing with Stripe first.');
            }

            if ($request->cancel_type === 'immediately') {
                $userSubscription->cancelNowAndInvoice();
                $message = 'Subscription has been cancelled immediately.';
            } else {
                $userSubscription->cancel();
                $message = 'Subscription has been cancelled and will end at the end of the billing period.';
            }

            return redirect()->route('admin.subscriptions.show', $id)
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Error cancelling subscription: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'subscription_type' => $subscription->type
            ]);
            return back()->with('error', 'Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resume($id)
    {
        $subscription = Subscription::findOrFail($id);
        $user = $subscription->user;

        try {
            // Check if the subscription can be resumed
            $userSubscription = $user->subscription($subscription->type);
            if (!$userSubscription) {
                Log::error('Subscription not found for user when trying to resume', [
                    'user_id' => $user->id,
                    'subscription_type' => $subscription->type,
                    'subscription_id' => $subscription->id
                ]);
                return back()->with('error', 'Subscription not found for this user. Try syncing with Stripe first.');
            }

            if (!$userSubscription->onGracePeriod()) {
                Log::warning('Attempted to resume subscription not on grace period', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'subscription_type' => $subscription->type,
                    'ends_at' => $subscription->ends_at,
                    'stripe_status' => $subscription->stripe_status
                ]);
                return back()->with('error', 'This subscription cannot be resumed. It must be on a grace period to be resumed.');
            }

            $userSubscription->resume();
            return redirect()->route('admin.subscriptions.show', $id)
                ->with('success', 'Subscription has been resumed.');
        } catch (\Exception $e) {
            Log::error('Error resuming subscription: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'subscription_type' => $subscription->type
            ]);
            return back()->with('error', 'Failed to resume subscription: ' . $e->getMessage());
        }
    }



    /**
     * Sync subscription with Stripe.
     */
    public function sync($id)
    {
        $subscription = Subscription::findOrFail($id);

        try {
            // Get the subscription from Stripe
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            $stripeSubscription = \Stripe\Subscription::retrieve([
                'id' => $subscription->stripe_id,
                'expand' => ['items.data.price.product']
            ]);

            // Log the Stripe subscription data for debugging
            Log::info('Stripe subscription data:', [
                'stripe_id' => $subscription->stripe_id,
                'status' => $stripeSubscription->status,
                'items' => $stripeSubscription->items->data
            ]);

            // Update local subscription data
            $subscription->stripe_status = $stripeSubscription->status;

            // Update the price ID if it has changed
            if (!empty($stripeSubscription->items->data)) {
                $item = $stripeSubscription->items->data[0];
                if ($item->price->id !== $subscription->stripe_price) {
                    $subscription->stripe_price = $item->price->id;

                    // We don't need to update the name since there's no name column in the subscriptions table
                    // The name is fetched directly from Stripe when needed
                }
            }

            $subscription->save();

            return redirect()->route('admin.subscriptions.show', $id)
                ->with('success', 'Subscription synced with Stripe successfully.');
        } catch (ApiErrorException $e) {
            Log::error('Error syncing subscription with Stripe: ' . $e->getMessage());
            return back()->with('error', 'Failed to sync with Stripe: ' . $e->getMessage());
        }
    }



    /**
     * Get available subscription plans from Stripe.
     */
    private function getAvailablePlans()
    {
        try {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Get active prices
            $activePrices = \Stripe\Price::all(['active' => true, 'limit' => 100, 'expand' => ['data.product']]);

            $plans = [];

            // Process active prices
            foreach ($activePrices->data as $price) {
                if ($price->type === 'recurring') {
                    $plans[] = [
                        'id' => $price->id,
                        'name' => $price->product->name,
                        'price' => $price->unit_amount / 100,
                        'interval' => $price->recurring->interval,
                        'currency' => $price->currency,
                        'active' => true,
                        'archived' => false,
                        'product_id' => $price->product->id,
                    ];
                }
            }

            // Get archived products (products that are not active)
            $archivedProducts = \Stripe\Product::all(['active' => false, 'limit' => 100]);

            // For each archived product, get its prices
            foreach ($archivedProducts->data as $product) {
                $productPrices = \Stripe\Price::all([
                    'product' => $product->id,
                    'limit' => 10,
                ]);

                foreach ($productPrices->data as $price) {
                    if ($price->type === 'recurring') {
                        $plans[] = [
                            'id' => $price->id,
                            'name' => $product->name . ' (Archived)',
                            'price' => $price->unit_amount / 100,
                            'interval' => $price->recurring->interval,
                            'currency' => $price->currency,
                            'active' => false,
                            'archived' => true,
                            'product_id' => $product->id,
                        ];
                    }
                }
            }

            return $plans;
        } catch (\Exception $e) {
            Log::error('Error fetching plans from Stripe: ' . $e->getMessage());
            return [];
        }
    }
}
