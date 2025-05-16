<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/pricing', function () {
    return Inertia::render('Pricing');
})->name('pricing');

// Public subscription routes (no auth required)
Route::get('/subscription/plans', [SubscriptionController::class, 'getPublicPlans'])->name('subscription.plans');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AgentController::class, 'index'])->middleware('redirect.if.admin')->name('dashboard');

    // Agents page
    Route::get('agents', [AgentController::class, 'index'])->middleware('redirect.if.admin')->name('agents');

    // Billing page
    Route::get('billing', function () {
        // Use the SubscriptionController to handle billing
        $subscriptionController = new SubscriptionController();

        // Get the subscription data and plans
        $user = request()->user();

        // Get subscription data
        $subscription = null;
        $paymentMethod = null;
        $invoices = [];
        $connectionError = false;

        try {
            if ($user->hasDefaultPaymentMethod()) {
                try {
                    $paymentMethod = $user->defaultPaymentMethod();
                } catch (\Stripe\Exception\ApiConnectionException $e) {
                    // Handle Stripe connection error with a meaningful message
                    Log::error('Unable to connect to payment service: ' . $e->getMessage());

                    // Add a flash message for the user
                    session()->flash('warning', 'Unable to connect to payment service. Your subscription information will be displayed from local data. Some features may be limited until connection is restored.');

                    $connectionError = true;
                }
            }

            // Get the user's first subscription or use 'default' as fallback
            $subscription = $user->subscriptions()->first();
            $subscriptionType = $subscription ? $subscription->type : 'default';

            try {
                if ($user->subscribed($subscriptionType)) {
                    $subscription = $user->subscription($subscriptionType);

                    // Only try to get invoices if we haven't encountered a connection error
                    if (!$connectionError) {
                        try {
                            $invoices = $user->invoices();
                        } catch (\Stripe\Exception\ApiConnectionException $e) {
                            Log::error('Unable to connect to payment service when retrieving invoices: ' . $e->getMessage());
                            $connectionError = true;
                            $invoices = [];
                        }
                    }
                }
            } catch (\Stripe\Exception\ApiConnectionException $e) {
                Log::error('Unable to connect to payment service when checking subscription: ' . $e->getMessage());
                $connectionError = true;
            }
        } catch (\Exception $e) {
            Log::error('Error retrieving subscription data: ' . $e->getMessage());
            session()->flash('error', 'We encountered an issue retrieving your subscription information. Please try again later or contact support if the problem persists.');
        }

        // Format subscription data for the frontend using the controller's method
        try {
            $formattedSubscription = $subscriptionController->formatSubscription($subscription, $paymentMethod, $invoices);

            // Add connection error flag to the subscription data
            if ($connectionError) {
                $formattedSubscription['connection_error'] = true;
                $formattedSubscription['connection_error_message'] = 'Unable to connect to payment service. Some subscription details may be limited until connection is restored.';
            }
        } catch (\Exception $e) {
            Log::error('Error formatting subscription data: ' . $e->getMessage());
            $formattedSubscription = [
                'connection_error' => true,
                'connection_error_message' => 'Unable to connect to payment service. Some subscription details may be limited until connection is restored.'
            ];
        }

        // Get plans from the SubscriptionController
        try {
            $plans = $subscriptionController->getAvailablePlans();

            // If plans is null, it means there was a connection error
            if ($plans === null) {
                $connectionError = true;
                $plans = [];
            }
        } catch (\Exception $e) {
            Log::error('Error retrieving available plans: ' . $e->getMessage());
            $plans = [];
            $connectionError = true;
        }

        return Inertia::render('Billing', [
            'subscription' => $formattedSubscription,
            'plans' => $plans,
            'connectionError' => $connectionError
        ]);
    })->middleware('redirect.if.admin')->name('billing');

    // Agent routes - protected by subscription
    Route::middleware('subscription')->group(function () {
        Route::post('agents', [AgentController::class, 'store'])->name('agents.store');
        Route::put('agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
        Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
        Route::put('agents/{agent}/toggle-status', [AgentController::class, 'toggleStatus'])->name('agents.toggle-status');
    });

    // Subscription routes (auth required)
    Route::prefix('subscription')->name('subscription.')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index'])->name('index');
        Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::put('/update-payment', [SubscriptionController::class, 'updatePaymentMethod'])->name('update-payment');
        Route::post('/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
        Route::post('/resume', [SubscriptionController::class, 'resume'])->name('resume');
        Route::post('/sync', [SubscriptionController::class, 'syncWithStripe'])->name('sync');
    });

    // Payment routes
    Route::prefix('payment')->name('payment.')->group(function () {
        Route::get('/methods', [PaymentController::class, 'showPaymentMethodForm'])->name('methods');
        Route::post('/methods', [PaymentController::class, 'storePaymentMethod'])->name('methods.store');
        Route::put('/methods/default', [PaymentController::class, 'setDefaultPaymentMethod'])->name('methods.default');
        Route::delete('/methods', [PaymentController::class, 'removePaymentMethod'])->name('methods.destroy');
        Route::post('/setup-intent', [PaymentController::class, 'createSetupIntent'])->name('setup-intent');
    });

    // Invoice routes
    Route::prefix('invoice')->name('invoice.')->group(function () {
        Route::get('/generate/{invoiceId}', [InvoiceController::class, 'generate'])->name('generate');
        Route::post('/generate-batch', [InvoiceController::class, 'generateBatch'])->name('generate-batch');
        Route::get('/download/{path}', [InvoiceController::class, 'download'])->name('download');
        Route::get('/download-direct/{invoiceId}', [InvoiceController::class, 'downloadDirect'])->name('download-direct');
        Route::get('/download-batch/{batchId}', [InvoiceController::class, 'downloadBatch'])->name('download-batch');
    });

    // Notification routes
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [App\Http\Controllers\NotificationController::class, 'getUnreadNotifications'])->name('unread');
        Route::post('/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('mark-read');
        Route::post('/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
    });
});

// Stripe Webhook
Route::post(
    'stripe/webhook',
    [StripeWebhookController::class, 'handleWebhook']
)->name('cashier.webhook');

// Ad routes (public)
Route::get('ads/active', [App\Http\Controllers\Admin\AdsController::class, 'getActiveAds'])->name('ads.active');
Route::post('ads/track-click', [App\Http\Controllers\Admin\AdsController::class, 'trackClick'])->name('ads.track-click');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
