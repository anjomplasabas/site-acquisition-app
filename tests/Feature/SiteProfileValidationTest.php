<?php

use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\SiteContact;
use App\Models\SiteProfile;
use App\Models\User;

function validSiteProfilePayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Validation Test Site',
        'type' => SiteProfileType::Condo->value,
        'city' => 'Manila',
        'contact_name' => 'Juan dela Cruz',
        'contact_mobile_number' => '09171234567',
    ], $overrides);
}

// --- Store ---

it('rejects a contact name with disallowed special characters on site creation', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('site-profiles.store'), validSiteProfilePayload([
            'contact_name' => 'Juan<>Cruz',
        ]))
        ->assertSessionHasErrors('contact_name');
});

it('rejects an invalid mobile number on site creation', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('site-profiles.store'), validSiteProfilePayload([
            'contact_mobile_number' => '12345',
        ]))
        ->assertSessionHasErrors('contact_mobile_number');
});

it('accepts a valid PH mobile number on site creation', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('site-profiles.store'), validSiteProfilePayload([
            'contact_mobile_number' => '+639171234567',
        ]))
        ->assertSessionDoesntHaveErrors('contact_mobile_number');
});

it('rejects a street value over 255 characters on site creation', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('site-profiles.store'), validSiteProfilePayload([
            'street' => str_repeat('a', 256),
        ]))
        ->assertSessionHasErrors('street');
});

it('rejects a total unit count beyond the database column range on site creation', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('site-profiles.store'), validSiteProfilePayload([
            'total_unit_count' => 2147483648,
        ]))
        ->assertSessionHasErrors('total_unit_count');
});

it('saves the Free WiFi area/location field on site creation', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('site-profiles.store'), validSiteProfilePayload([
            'wifi_area_location' => 'Lobby',
        ]))
        ->assertSessionDoesntHaveErrors('wifi_area_location');

    expect(SiteProfile::where('name', 'Validation Test Site')->first()->wifi_area_location)->toBe('Lobby');
});

// --- Update ---

it('blocks updating a site profile into a duplicate name and city combination', function () {
    $address1 = Address::create(['city' => 'Manila']);
    $address2 = Address::create(['city' => 'Manila']);

    SiteProfile::create(['name' => 'Existing Tower', 'type' => SiteProfileType::Condo, 'address_id' => $address1->id]);
    $target = SiteProfile::create(['name' => 'Other Tower', 'type' => SiteProfileType::Condo, 'address_id' => $address2->id]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('site-profiles.update', $target), [
            'name' => 'Existing Tower',
            'type' => SiteProfileType::Condo->value,
            'city' => 'Manila',
            'contact_name' => 'Juan dela Cruz',
            'contact_mobile_number' => '09171234567',
        ])
        ->assertSessionHasErrors('name');
});

it('allows updating a site profile without changing its own name', function () {
    $address = Address::create(['city' => 'Manila']);
    $target = SiteProfile::create(['name' => 'My Tower', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('site-profiles.update', $target), [
            'name' => 'My Tower',
            'type' => SiteProfileType::Condo->value,
            'city' => 'Manila',
            'contact_name' => 'Juan dela Cruz',
            'contact_mobile_number' => '09171234567',
        ])
        ->assertSessionDoesntHaveErrors('name');
});

it('rejects a street value over 255 characters on site update', function () {
    $address = Address::create(['city' => 'Manila']);
    $target = SiteProfile::create(['name' => 'My Tower', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('site-profiles.update', $target), [
            'name' => 'My Tower',
            'type' => SiteProfileType::Condo->value,
            'city' => 'Manila',
            'street' => str_repeat('a', 256),
            'contact_name' => 'Juan dela Cruz',
            'contact_mobile_number' => '09171234567',
        ])
        ->assertSessionHasErrors('street');
});

it('allows updating a site profile that has no contact yet without providing one', function () {
    $address = Address::create(['city' => 'Manila']);
    $target = SiteProfile::create(['name' => 'Imported Tower', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('site-profiles.update', $target), [
            'name' => 'Imported Tower',
            'type' => SiteProfileType::Condo->value,
            'city' => 'Manila',
            'total_unit_count' => 120,
        ])
        ->assertSessionDoesntHaveErrors('contact_name')
        ->assertRedirect();

    expect($target->fresh()->contact_id)->toBeNull();
});

it('preserves an existing contact name when left blank on site update', function () {
    $address = Address::create(['city' => 'Manila']);
    $contact = SiteContact::create(['name' => 'Juan dela Cruz', 'mobile_number' => '09171234567']);
    $target = SiteProfile::create(['name' => 'My Tower', 'type' => SiteProfileType::Condo, 'address_id' => $address->id, 'contact_id' => $contact->id]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('site-profiles.update', $target), [
            'name' => 'My Tower',
            'type' => SiteProfileType::Condo->value,
            'city' => 'Manila',
        ])
        ->assertSessionDoesntHaveErrors('contact_name')
        ->assertRedirect();

    expect($contact->fresh()->name)->toBe('Juan dela Cruz');
});

it('saves the Free WiFi area/location field on site update', function () {
    $address = Address::create(['city' => 'Manila']);
    $target = SiteProfile::create(['name' => 'My Tower', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('site-profiles.update', $target), [
            'name' => 'My Tower',
            'type' => SiteProfileType::Condo->value,
            'city' => 'Manila',
            'wifi_area_location' => 'Ground Floor Lobby',
        ])
        ->assertSessionDoesntHaveErrors('wifi_area_location');

    expect($target->fresh()->wifi_area_location)->toBe('Ground Floor Lobby');
});

it('allows the same site name in a different city', function () {
    $address1 = Address::create(['city' => 'Manila']);
    $address2 = Address::create(['city' => 'Cebu']);

    SiteProfile::create(['name' => 'Shared Tower Name', 'type' => SiteProfileType::Condo, 'address_id' => $address1->id]);
    $target = SiteProfile::create(['name' => 'My Tower', 'type' => SiteProfileType::Condo, 'address_id' => $address2->id]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('site-profiles.update', $target), [
            'name' => 'Shared Tower Name',
            'type' => SiteProfileType::Condo->value,
            'city' => 'Cebu',
            'contact_name' => 'Juan dela Cruz',
            'contact_mobile_number' => '09171234567',
        ])
        ->assertSessionDoesntHaveErrors('name');
});

// --- Completed pipeline freeze ---

it('blocks editing a site profile once an associated pipeline is Completed', function () {
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Frozen Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    Pipeline::create([
        'name' => 'Done Pipeline',
        'pipeline_id' => nextPipelineId(),
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Completed,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('site-profiles.update', $siteProfile), validSiteProfilePayload(['name' => 'Renamed']))
        ->assertStatus(422);

    expect($siteProfile->fresh()->name)->toBe('Frozen Site');
});

it('still allows editing a site profile whose pipelines are not Completed', function () {
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Live Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    Pipeline::create([
        'name' => 'Live Pipeline',
        'pipeline_id' => nextPipelineId(),
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospecting,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('site-profiles.update', $siteProfile), validSiteProfilePayload(['name' => 'Renamed Live']))
        ->assertSessionDoesntHaveErrors();

    expect($siteProfile->fresh()->name)->toBe('Renamed Live');
});
