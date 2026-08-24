<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'branches';

    protected $fillable = [
        'company_profile_id', 'name', 'code', 'address', 'phone',
        'lat', 'lng', 'radius_meters', 'is_active',
    ];

    protected $casts = [
        'lat'           => 'float',
        'lng'           => 'float',
        'radius_meters' => 'integer',
        'is_active'     => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────

    public function companyProfile()
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function presences()
    {
        return $this->hasMany(Presence::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * A branch can only geofence if someone has set its coordinates.
     */
    public function hasGeofence(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /**
     * Great-circle distance in metres from this branch to a point.
     */
    public function distanceTo(float $lat, float $lng): ?float
    {
        if (! $this->hasGeofence()) {
            return null;
        }

        $earthRadius = 6371000;

        $latFrom = deg2rad((float) $this->lat);
        $lonFrom = deg2rad((float) $this->lng);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lng);

        $latDiff = $latTo - $latFrom;
        $lonDiff = $lonTo - $lonFrom;

        $a = sin($latDiff / 2) ** 2
           + cos($latFrom) * cos($latTo) * sin($lonDiff / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Is a point inside this branch's geofence?
     */
    public function contains(float $lat, float $lng): bool
    {
        $distance = $this->distanceTo($lat, $lng);

        if ($distance === null) {
            return false;
        }

        return $distance <= max(1, (int) $this->radius_meters);
    }
}
