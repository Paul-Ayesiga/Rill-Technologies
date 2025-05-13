<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the created_at attribute formatted for display.
     */
    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at->format('F j, Y');
    }

    /**
     * Get the created_at attribute with time ago for display.
     */
    public function getCreatedAtDiffAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the created_at attribute with both date and time ago.
     */
    public function getCreatedAtFormattedAttribute(): string
    {
        return $this->created_at->format('F j, Y') . ' (' . $this->created_at->diffForHumans() . ')';
    }

    /**
     * Get the agents for the user.
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
