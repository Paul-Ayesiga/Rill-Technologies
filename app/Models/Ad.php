<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'position',
        'image_url',
        'title',
        'description',
        'url',
        'ad_id',
        'is_active',
        'start_date',
        'end_date',
        'impressions',
        'clicks',
        'pages',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'pages' => 'array',
        'settings' => 'array',
    ];

    /**
     * Generate a unique ad ID.
     *
     * @return string
     */
    public static function generateAdId(): string
    {
        return 'ad-' . uniqid();
    }

    /**
     * Scope a query to only include active ads.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Increment the impression count.
     *
     * @return void
     */
    public function incrementImpressions(): void
    {
        $this->increment('impressions');
    }

    /**
     * Increment the click count.
     *
     * @return void
     */
    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }
}
