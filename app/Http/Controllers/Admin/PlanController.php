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

            Log::info('Attempting to archive plan with ID: ' . $id);

            // Get the price to get the product ID
            try {
                $price = Price::retrieve($id);
                Log::info('Retrieved price: ' . json_encode($price));
            } catch (\Exception $e) {
                Log::error('Failed to retrieve price: ' . $e->getMessage());
                return back()->withErrors(['error' => 'Failed to retrieve price: ' . $e->getMessage()]);
            }

            // First, archive the product
            try {
                if (!isset($price->product)) {
                    Log::error('Product ID not found in price object');
                    return back()->withErrors(['error' => 'Product ID not found in price object']);
                }

                // Get the product to check if this price is the default and preserve metadata
                $product = Product::retrieve($price->product);
                Log::info('Retrieved product: ' . json_encode($product));

                // Preserve the metadata
                $metadata = isset($product->metadata) ? $product->metadata->toArray() : [];
                Log::info('Preserving metadata: ' . json_encode($metadata));

                // Archive the product first while preserving metadata
                Product::update($price->product, [
                    'active' => false,
                    'metadata' => $metadata, // Preserve the existing metadata
                ]);
                Log::info('Product archived successfully');
            } catch (\Exception $e) {
                Log::error('Failed to archive product: ' . $e->getMessage());
                return back()->withErrors(['error' => 'Failed to archive product: ' . $e->getMessage()]);
            }

            // Now try to archive the price
            try {
                Price::update($id, [
                    'active' => false,
                ]);
                Log::info('Price archived successfully');
            } catch (\Exception $e) {
                // If we can't archive the price but we've already archived the product,
                // that's okay - the product being archived is what matters most
                Log::warning('Could not archive price, but product was archived: ' . $e->getMessage());
                // We don't return an error here because the product was successfully archived
            }

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan archived successfully!');
        } catch (ApiErrorException $e) {
            Log::error('Stripe API Error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to archive plan: ' . $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('General Error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to archive plan: ' . $e->getMessage()]);
        }
    }

    /**
     * Unarchive a plan in Stripe.
     *
     * @param  string  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function unarchive($id)
    {
        try {
            Stripe::setApiKey(config('cashier.secret'));

            Log::info('Attempting to unarchive plan with ID: ' . $id);

            // Get the price to get the product ID
            try {
                $price = Price::retrieve($id);
                Log::info('Retrieved price: ' . json_encode($price));
            } catch (\Exception $e) {
                Log::error('Failed to retrieve price: ' . $e->getMessage());
                return back()->withErrors(['error' => 'Failed to retrieve price: ' . $e->getMessage()]);
            }

            // First, unarchive the product
            try {
                if (!isset($price->product)) {
                    Log::error('Product ID not found in price object');
                    return back()->withErrors(['error' => 'Product ID not found in price object']);
                }

                // Get the product to check if this price is the default and preserve metadata
                $product = Product::retrieve($price->product);
                Log::info('Retrieved product: ' . json_encode($product));

                // Preserve the metadata
                $metadata = isset($product->metadata) ? $product->metadata->toArray() : [];
                Log::info('Preserving metadata: ' . json_encode($metadata));

                // Unarchive the product first while preserving metadata
                Product::update($price->product, [
                    'active' => true,
                    'metadata' => $metadata, // Preserve the existing metadata
                ]);
                Log::info('Product unarchived successfully');
            } catch (\Exception $e) {
                Log::error('Failed to unarchive product: ' . $e->getMessage());
                return back()->withErrors(['error' => 'Failed to unarchive product: ' . $e->getMessage()]);
            }

            // Now try to unarchive the price
            try {
                Price::update($id, [
                    'active' => true,
                ]);
                Log::info('Price unarchived successfully');
            } catch (\Exception $e) {
                // If we can't unarchive the price but we've already unarchived the product,
                // that's okay - the product being unarchived is what matters most
                Log::warning('Could not unarchive price, but product was unarchived: ' . $e->getMessage());
                // We don't return an error here because the product was successfully unarchived
            }

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan unarchived successfully!');
        } catch (ApiErrorException $e) {
            Log::error('Stripe API Error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to unarchive plan: ' . $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('General Error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to unarchive plan: ' . $e->getMessage()]);
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

            $plans = [];

            // Get all active prices with their products
            $activePrices = Price::all([
                'active' => true,
                'type' => 'recurring',
                'expand' => ['data.product'],
                'limit' => 100,
            ]);

            // Process active prices with active products
            foreach ($activePrices->data as $price) {
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
                    'active' => true,
                    'archived' => false,
                ];
            }

            // Get archived products
            $archivedProducts = Product::all([
                'active' => false,
                'limit' => 100,
            ]);

            // For each archived product, get its prices
            foreach ($archivedProducts->data as $product) {
                $productPrices = Price::all([
                    'product' => $product->id,
                    'type' => 'recurring',
                    'limit' => 10,
                ]);

                foreach ($productPrices->data as $price) {
                    // Extract features from product metadata
                    $features = [];
                    if (isset($product->metadata->features)) {
                        $features = json_decode($product->metadata->features, true) ?? [];
                    }

                    // Extract trial days from product metadata
                    $trialDays = null;
                    if (isset($product->metadata->trial_days)) {
                        $trialDays = (int) $product->metadata->trial_days;
                    }

                    $plans[] = [
                        'id' => $price->id,
                        'product_id' => $product->id,
                        'name' => $product->name . ' (Archived)',
                        'description' => $product->description,
                        'price' => $price->unit_amount / 100,
                        'interval' => $price->recurring->interval,
                        'currency' => $price->currency,
                        'features' => $features,
                        'trial_days' => $trialDays,
                        'created' => date('Y-m-d H:i:s', $price->created),
                        'active' => false,
                        'archived' => true,
                    ];
                }
            }

            return $plans;
        } catch (ApiErrorException $e) {
            Log::error('Stripe API Error: ' . $e->getMessage());
            return [];
        }
    }
}
