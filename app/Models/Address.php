<?php

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['street', 'barangay', 'city', 'province'])]
#[Table(timestamps: false)]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    public function siteProfiles(): HasMany
    {
        return $this->hasMany(SiteProfile::class);
    }
}
