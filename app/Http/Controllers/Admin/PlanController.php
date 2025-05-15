<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;

class PlanController extends Controller
{
    /**
     * Display a listing of the plans.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $plans = $this->getPlansWithFeatures();

        return Inertia::render('admin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    /**
     * Store a newly created plan in Stripe.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:month,year',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'required|array',
            'features.*.name' => 'required|string|max:255',
            'features.*.included' => 'required|boolean',
        ]);

        try {
            Stripe::setApiKey(config('cashier.secret'));

            // Create a product in Stripe
            $metadata = [
                'features' => json_encode($request->features),
            ];

            // Add trial days to metadata if provided
            if ($request->has('trial_days') && $request->trial_days > 0) {
                $metadata['trial_days'] = $request->trial_days;
            }

            $product = Product::create([
                'name' => $request->name,
                'description' => $request->description,
                'metadata' => $metadata,
            ]);

            // Create a price for the product
            $price = Price::create([
                'product' => $product->id,
                'unit_amount' => $request->price * 100, // Convert to cents
                'currency' => 'usd',
                'recurring' => [
                    'interval' => $request->interval,
                ],
                'metadata' => [
                    'product_name' => $request->name,
                ],
            ]);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan created successfully!');
        } catch (ApiErrorException $e) {
            Log::error('Stripe API Error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to create plan: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified plan in Stripe.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'required|array',
            'features.*.name' => 'required|string|max:255',
            'features.*.included' => 'required|boolean',
        ]);

        try {
            Stripe::setApiKey(config('cashier.secret'));

            // Get the price to get the product ID
            $price = Price::retrieve($id);

            // Prepare metadata for update
            $metadata = [
                'features' => json_encode($request->features),
            ];

            // Add trial days to metadata if provided
            if ($request->has('trial_days') && $request->trial_days > 0) {
                $metadata['trial_days'] = $request->trial_days;
            }

            // Update the product
            Product::update($price->product, [
                'name' => $request->name,
                'description' => $request->description,
                'metadata' => $metadata,
            ]);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan updated successfully!');
        } catch (ApiErrorException $e) {
            Log::error('Stripe API Error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update plan: ' . $e->getMessage()]);
        }
    }

    /**
     * Archive the specified plan in Stripe.
     *
     * @param  string  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        try {
            Stripe::setApiKey(config('cashier.secret'));

            // Archive the price (cannot delete prices in Stripe)
            Price::update($id, [
                'active' => false,
            ]);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan archived successfully!');
        } catch (ApiErrorException $e) {
            Log::error('Stripe API Error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to archive plan: ' . $e->getMessage()]);
        }
    }

    /**
     * Get all plans with their features from Stripe.
     *
     * @return array
     */
    private function getPlansWithFeatures()
    {
        try {
            Stripe::setApiKey(config('cashier.secret'));

            // Get all active prices with their products
            $prices = Price::all([
                'active' => true,
                'type' => 'recurring',
                'expand' => ['data.product'],
                'limit' => 100,
            ]);

            $plans = [];

            foreach ($prices->data as $price) {
                // Skip if product is not active
                if (!$price->product->active) {
                    continue;
                }

                // Extract features from product metadata
                $features = [];
                if (isset($price->product->metadata->features)) {
                    $features = json_decode($price->product->metadata->features, true) ?? [];
                }

                // Extract trial days from product metadata
                $trialDays = null;
                if (isset($price->product->metadata->trial_days)) {
                    $trialDays = (int) $price->product->metadata->trial_days;
                }

                $plans[] = [
                    'id' => $price->id,
                    'product_id' => $price->product->id,
                    'name' => $price->product->name,
                    'description' => $price->product->description,
                    'price' => $price->unit_amount / 100,
                    'interval' => $price->recurring->interval,
                    'currency' => $price->currency,
                    'features' => $features,
                    'trial_days' => $trialDays,
                    'created' => date('Y-m-d H:i:s', $price->created),
                ];
            }

            return $plans;
        } catch (ApiErrorException $e) {
            Log::error('Stripe API Error: ' . $e->getMessage());
            return [];
        }
    }
}
