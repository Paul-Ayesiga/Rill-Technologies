<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class CustomersController extends Controller
{
    /**
     * Display a listing of the customers.
     */
    public function index()
    {
        // Get all users with the 'customer' role
        $customers = User::role('customer')
            ->with('roles')
            ->withCount('agents')
            ->latest()
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at_formatted,
                    'status' => $user->status ?? 'active',
                    'agents_count' => $user->agents_count,
                    'subscription_status' => $this->getSubscriptionStatus($user),
                    'last_login' => $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never',
                ];
            });

        return Inertia::render('admin/Customers/Index', [
            'customers' => $customers,
        ]);
    }

    /**
     * Display the specified customer.
     */
    public function show($id)
    {
        $customer = User::findOrFail($id);

        // Check if user has customer role
        if (!$customer->hasRole('customer')) {
            return redirect()->route('admin.customers.index')
                ->with('error', 'This user is not a customer.');
        }

        // Basic customer info that loads immediately
        $customerData = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'created_at' => $customer->created_at_formatted,
            'status' => $customer->status ?? 'active',
        ];

        return Inertia::render('admin/Customers/Show', [
            'customer' => $customerData,
            // Defer loading of agents data
            'agents' => Inertia::lazy(function () use ($customer) {
                $customer->load('agents');
                return $customer->agents;
            }),
            // Defer loading of subscription data
            'subscription' => Inertia::lazy(function () use ($customer) {
                if (!$customer->subscribed('default')) {
                    return null;
                }

                $subscription = $customer->subscription('default');
                return [
                    'name' => $subscription->name,
                    'stripe_status' => $subscription->stripe_status,
                    'stripe_price' => $subscription->stripe_price,
                    'quantity' => $subscription->quantity,
                    'trial_ends_at' => $subscription->trial_ends_at ? $subscription->trial_ends_at->format('F j, Y') . ' (' . $subscription->trial_ends_at->diffForHumans() . ')' : null,
                    'ends_at' => $subscription->ends_at ? $subscription->ends_at->format('F j, Y') . ' (' . $subscription->ends_at->diffForHumans() . ')' : null,
                ];
            }),
            // Defer loading of payment method data
            'paymentMethod' => Inertia::lazy(function () use ($customer) {
                if (!$customer->hasDefaultPaymentMethod()) {
                    return null;
                }

                $paymentMethod = $customer->defaultPaymentMethod();
                return [
                    'brand' => $paymentMethod->card->brand,
                    'last4' => $paymentMethod->card->last4,
                    'exp_month' => $paymentMethod->card->exp_month,
                    'exp_year' => $paymentMethod->card->exp_year,
                ];
            }),
            // Defer loading of invoices data
            'invoices' => Inertia::lazy(function () use ($customer) {
                if (!$customer->subscribed('default')) {
                    return [];
                }

                $invoices = $customer->invoices();
                return collect($invoices)->map(function ($invoice) {
                    $date = $invoice->date();
                    return [
                        'id' => $invoice->id,
                        'total' => $invoice->total(),
                        'date' => $date->format('F j, Y') . ' (' . $date->diffForHumans() . ')',
                        'status' => $invoice->status,
                    ];
                });
            }),
        ]);
    }

    /**
     * Update the customer status.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,suspended,banned',
        ]);

        $customer = User::findOrFail($id);

        // Check if user has customer role
        if (!$customer->hasRole('customer')) {
            return redirect()->route('admin.customers.index')
                ->with('error', 'This user is not a customer.');
        }

        $customer->status = $request->status;
        $customer->save();

        return redirect()->back()->with('success', "Customer status updated to {$request->status}.");
    }

    /**
     * Get the subscription status for a user.
     */
    private function getSubscriptionStatus($user)
    {
        if ($user->onTrial()) {
            return 'trialing';
        }

        if (!$user->subscribed()) {
            return 'none';
        }

        $subscription = $user->subscription();

        if (!$subscription) {
            return 'none';
        }

        return $subscription->stripe_status;
    }
}
