<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/pricing', function () {
    return Inertia::render('Pricing');
})->name('pricing');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AgentController::class, 'index'])->name('dashboard');

    // Agents page
    Route::get('agents', [AgentController::class, 'index'])->name('agents');

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

        if ($user->hasDefaultPaymentMethod()) {
            $paymentMethod = $user->defaultPaymentMethod();
        }

        if ($user->subscribed('default')) {
            $subscription = $user->subscription('default');
            $invoices = $user->invoices();
        }

        // Format subscription data for the frontend using the controller's method
        $formattedSubscription = $subscriptionController->formatSubscription($subscription, $paymentMethod, $invoices);

        // Get plans from the SubscriptionController
        $plans = $subscriptionController->getAvailablePlans();

        return Inertia::render('Billing', [
            'subscription' => $formattedSubscription,
            'plans' => $plans
        ]);
    })->name('billing');

    // Agent routes
    Route::post('agents', [AgentController::class, 'store'])->name('agents.store');
    Route::put('agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
    Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
    Route::put('agents/{agent}/toggle-status', [AgentController::class, 'toggleStatus'])->name('agents.toggle-status');

    // Subscription routes
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
});

// Stripe Webhook
Route::post(
    'stripe/webhook',
    [StripeWebhookController::class, 'handleWebhook']
)->name('cashier.webhook');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
