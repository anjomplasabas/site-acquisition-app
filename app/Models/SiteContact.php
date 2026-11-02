<?php

namespace App\Models;

use Database\Factories\SiteContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'designation', 'email', 'mobile_number'])]
class SiteContact extends Model
{
    /** @use HasFactory<SiteContactFactory> */
    use HasFactory, SoftDeletes;

    public function siteProfiles(): HasMany
    {
        return $this->hasMany(SiteProfile::class, 'contact_id');
    }

    public static function duplicateExists(string $name, ?string $mobileNumber, ?string $email, ?int $ignoreId = null): bool
    {
        return static::firstMatching($name, $mobileNumber, $email, $ignoreId) !== null;
    }

    /**
     * The existing contact a typed-in name + mobile/email combination refers to, if any —
     * name alone is too weak a signal to merge on, so at least one of mobile/email must
     * match too. Used both for duplicate validation (contact CRUD) and to reuse the match
     * instead of creating a twin during manual pipeline creation.
     */
    public static function firstMatching(string $name, ?string $mobileNumber, ?string $email, ?int $ignoreId = null): ?self
    {
        if (! $mobileNumber && ! $email) {
            return null;
        }

        return static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($mobileNumber, fn ($query) => $query->where('mobile_number', $mobileNumber))
            ->when($email, fn ($query) => $query->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))]))
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->first();
    }
}
