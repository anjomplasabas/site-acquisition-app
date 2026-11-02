<?php

namespace App\Models;

use App\Enums\SiteProfileType;
use App\Support\LeadName;
use Database\Factories\SiteProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'normalized_name', 'developer', 'type', 'type_other', 'address_id', 'contact_id',
    'building_operation_date',
    'total_building_count',
    'total_floors',
    'total_floors_per_tower',
    'total_unit_count',
    'total_parking_floors',
    'residential_floor_starts_at',
    'floors_not_in_plan',
    'amenities',
    'total_commercial_unit_count',
    'residential_occupancy_percentage',
    'total_units_per_floor',
    'room_types',
    'turnover_rate',
    'occupancy_rate',
    'whz_location',
    'total_whz_allocation',
    'wifi_area_location',
])]
class SiteProfile extends Model
{
    /** @use HasFactory<SiteProfileFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (SiteProfile $siteProfile): void {
            if ($siteProfile->isDirty('name')) {
                $siteProfile->normalized_name = LeadName::normalize($siteProfile->name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => SiteProfileType::class,
            'building_operation_date' => 'date',
            'residential_occupancy_percentage' => 'decimal:2',
            'turnover_rate' => 'decimal:2',
            'occupancy_rate' => 'decimal:2',
        ];
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(SiteContact::class, 'contact_id');
    }

    public function siteSurveys(): HasMany
    {
        return $this->hasMany(SiteSurvey::class);
    }

    public function towers(): HasMany
    {
        return $this->hasMany(Tower::class);
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class);
    }
}
