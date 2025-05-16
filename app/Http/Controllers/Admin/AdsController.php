<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class AdsController extends Controller
{
    /**
     * Display a listing of the ads.
     */
    public function index()
    {
        $ads = Ad::latest()->get()->map(function ($ad) {
            return [
                'id' => $ad->id,
                'name' => $ad->name,
                'type' => $ad->type,
                'position' => $ad->position,
                'is_active' => $ad->is_active,
                'impressions' => $ad->impressions,
                'clicks' => $ad->clicks,
                'ctr' => $ad->impressions > 0 ? round(($ad->clicks / $ad->impressions) * 100, 2) : 0,
                'start_date' => $ad->start_date ? $ad->start_date->format('Y-m-d') : null,
                'end_date' => $ad->end_date ? $ad->end_date->format('Y-m-d') : null,
                'created_at' => $ad->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $ad->updated_at->format('Y-m-d H:i:s'),
                'image_url' => $ad->image_url,
                'title' => $ad->title,
                'description' => $ad->description,
                'url' => $ad->url,
                'ad_id' => $ad->ad_id,
                'pages' => $ad->pages,
                'settings' => $ad->settings,
            ];
        });

        return Inertia::render('admin/Ads/Index', [
            'ads' => $ads,
        ]);
    }

    /**
     * Store a newly created ad in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:banner,floating,top',
            'position' => 'nullable|string|in:left,right,top,bottom,bottom-right,bottom-left,top-right,top-left',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'url' => 'nullable|url',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'pages' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $adData = $request->except('image');

            // Generate a unique ad ID
            $adData['ad_id'] = Ad::generateAdId();

            // Handle image upload
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('ads', 'public');
                $adData['image_url'] = Storage::url($path);
            }

            // Create the ad
            Ad::create($adData);

            return redirect()->route('admin.ads.index')
                ->with('success', 'Ad created successfully.');
        } catch (\Exception $e) {
            Log::error('Error creating ad: ' . $e->getMessage());
            return back()->with('error', 'Failed to create ad: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified ad in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:banner,floating,top',
            'position' => 'nullable|string|in:left,right,top,bottom,bottom-right,bottom-left,top-right,top-left',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'url' => 'nullable|url',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'pages' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $ad = Ad::findOrFail($id);
            $adData = $request->except(['image', 'ad_id']);

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($ad->image_url) {
                    $oldPath = str_replace('/storage/', '', $ad->image_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $path = $request->file('image')->store('ads', 'public');
                $adData['image_url'] = Storage::url($path);
            }

            // Update the ad
            $ad->update($adData);

            return redirect()->route('admin.ads.index')
                ->with('success', 'Ad updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating ad: ' . $e->getMessage());
            return back()->with('error', 'Failed to update ad: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified ad from storage.
     */
    public function destroy($id)
    {
        try {
            $ad = Ad::findOrFail($id);

            // Delete image if exists
            if ($ad->image_url) {
                $path = str_replace('/storage/', '', $ad->image_url);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            // Delete the ad
            $ad->delete();

            return redirect()->route('admin.ads.index')
                ->with('success', 'Ad deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting ad: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete ad: ' . $e->getMessage());
        }
    }

    /**
     * Toggle the active status of the specified ad.
     */
    public function toggleStatus($id)
    {
        try {
            $ad = Ad::findOrFail($id);
            $ad->is_active = !$ad->is_active;
            $ad->save();

            return back()->with('success', 'Ad status updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error toggling ad status: ' . $e->getMessage());
            return back()->with('error', 'Failed to update ad status: ' . $e->getMessage());
        }
    }

    /**
     * Get active ads for a specific page.
     */
    public function getActiveAds(Request $request)
    {
        $page = $request->input('page', 'all');

        $ads = Ad::active()
            ->where(function ($query) use ($page) {
                $query->whereJsonContains('pages', $page)
                    ->orWhereJsonContains('pages', 'all')
                    ->orWhereNull('pages');
            })
            ->get()
            ->map(function ($ad) {
                // Increment impression count
                $ad->incrementImpressions();

                return [
                    'id' => $ad->id,
                    'ad_id' => $ad->ad_id,
                    'type' => $ad->type,
                    'position' => $ad->position,
                    'image_url' => $ad->image_url,
                    'title' => $ad->title,
                    'description' => $ad->description,
                    'url' => $ad->url,
                    'settings' => $ad->settings,
                ];
            });

        return response()->json(['ads' => $ads]);
    }

    /**
     * Track ad click.
     */
    public function trackClick(Request $request)
    {
        $adId = $request->input('ad_id');

        $ad = Ad::where('ad_id', $adId)->first();

        if ($ad) {
            $ad->incrementClicks();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    }
}
