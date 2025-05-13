<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PaymentController extends Controller
{
    /**
     * Create a setup intent for the user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSetupIntent()
    {
        $user = Auth::user();
        
        // Create a setup intent
        $setupIntent = $user->createSetupIntent();
        
        return response()->json([
            'client_secret' => $setupIntent->client_secret,
        ]);
    }
    
    /**
     * Display the payment method form.
     *
     * @return \Inertia\Response
     */
    public function showPaymentMethodForm()
    {
        $user = Auth::user();
        
        // Get the user's payment methods
        $paymentMethods = $user->paymentMethods();
        
        // Format payment methods for the frontend
        $formattedPaymentMethods = [];
        foreach ($paymentMethods as $method) {
            $formattedPaymentMethods[] = [
                'id' => $method->id,
                'brand' => $method->card->brand,
                'last4' => $method->card->last4,
                'exp_month' => $method->card->exp_month,
                'exp_year' => $method->card->exp_year,
                'is_default' => $user->defaultPaymentMethod() && $user->defaultPaymentMethod()->id === $method->id,
            ];
        }
        
        // Create a setup intent for adding a new payment method
        $setupIntent = $user->createSetupIntent();
        
        return Inertia::render('Payment/Methods', [
            'paymentMethods' => $formattedPaymentMethods,
            'setupIntent' => [
                'client_secret' => $setupIntent->client_secret,
            ],
            'stripeKey' => config('cashier.key'),
        ]);
    }
    
    /**
     * Store a new payment method.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storePaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string',
        ]);
        
        $user = Auth::user();
        
        // Attach the payment method to the user
        $paymentMethod = $user->addPaymentMethod($request->payment_method);
        
        // If this is the user's first payment method, make it the default
        if (!$user->hasDefaultPaymentMethod()) {
            $user->updateDefaultPaymentMethod($paymentMethod->id);
        }
        
        return redirect()->route('payment.methods')
            ->with('success', 'Payment method added successfully!');
    }
    
    /**
     * Set a payment method as default.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setDefaultPaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string',
        ]);
        
        $user = Auth::user();
        
        // Update the default payment method
        $user->updateDefaultPaymentMethod($request->payment_method);
        
        return redirect()->route('payment.methods')
            ->with('success', 'Default payment method updated successfully!');
    }
    
    /**
     * Remove a payment method.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removePaymentMethod(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string',
        ]);
        
        $user = Auth::user();
        
        // Get the payment method
        $paymentMethod = $user->findPaymentMethod($request->payment_method);
        
        // Check if this is the default payment method
        $isDefault = $user->defaultPaymentMethod() && $user->defaultPaymentMethod()->id === $paymentMethod->id;
        
        // Delete the payment method
        $paymentMethod->delete();
        
        // If this was the default payment method, set a new default if available
        if ($isDefault) {
            $newDefaultMethod = $user->paymentMethods()->first();
            if ($newDefaultMethod) {
                $user->updateDefaultPaymentMethod($newDefaultMethod->id);
            }
        }
        
        return redirect()->route('payment.methods')
            ->with('success', 'Payment method removed successfully!');
    }
}
