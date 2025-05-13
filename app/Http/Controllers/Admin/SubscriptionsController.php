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
                return [
                    'id' => $subscription->id,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'name' => $subscription->name,
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
            'subscriptions' => $subscriptions,
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

        // Get subscription data
        $subscriptionData = [
            'id' => $subscription->id,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->format('F j, Y') . ' (' . $user->created_at->diffForHumans() . ')',
            ],
            'name' => $subscription->name,
            'stripe_id' => $subscription->stripe_id,
            'stripe_status' => $subscription->stripe_status,
            'stripe_price' => $subscription->stripe_price,
            'quantity' => $subscription->quantity,
            'trial_ends_at' => $subscription->trial_ends_at ? $subscription->trial_ends_at->format('F j, Y') . ' (' . $subscription->trial_ends_at->diffForHumans() . ')' : null,
            'ends_at' => $subscription->ends_at ? $subscription->ends_at->format('F j, Y') . ' (' . $subscription->ends_at->diffForHumans() . ')' : null,
            'created_at' => $subscription->created_at->format('F j, Y') . ' (' . $subscription->created_at->diffForHumans() . ')',
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
        $invoices = $user->invoices()->map(function ($invoice) {
            $date = $invoice->date();
            return [
                'id' => $invoice->id,
                'total' => $invoice->total(),
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
            if ($request->cancel_type === 'immediately') {
                $user->subscription($subscription->name)->cancelNow();
                $message = 'Subscription has been cancelled immediately.';
            } else {
                $user->subscription($subscription->name)->cancel();
                $message = 'Subscription has been cancelled and will end at the end of the billing period.';
            }

            return redirect()->route('admin.subscriptions.show', $id)
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Error cancelling subscription: ' . $e->getMessage());
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
            if ($subscription->ends_at && $subscription->stripe_status === 'canceled') {
                $user->subscription($subscription->name)->resume();
                return redirect()->route('admin.subscriptions.show', $id)
                    ->with('success', 'Subscription has been resumed.');
            }

            return back()->with('error', 'This subscription cannot be resumed.');
        } catch (\Exception $e) {
            Log::error('Error resuming subscription: ' . $e->getMessage());
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
            $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);

            // Update local subscription data
            $subscription->stripe_status = $stripeSubscription->status;
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
            $prices = \Stripe\Price::all(['active' => true, 'limit' => 10, 'expand' => ['data.product']]);

            $plans = [];
            foreach ($prices->data as $price) {
                if ($price->type === 'recurring') {
                    $plans[] = [
                        'id' => $price->id,
                        'name' => $price->product->name,
                        'price' => $price->unit_amount / 100,
                        'interval' => $price->recurring->interval,
                        'currency' => $price->currency,
                    ];
                }
            }

            return $plans;
        } catch (\Exception $e) {
            Log::error('Error fetching plans from Stripe: ' . $e->getMessage());
            return [];
        }
    }
}
