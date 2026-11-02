<?php

use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\SiteContact;
use App\Models\SiteProfile;
use App\Models\User;

function validSiteContactPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Juan dela Cruz',
        'designation' => 'Property Manager',
        'email' => 'juan@example.com',
        'mobile_number' => '09171234567',
    ], $overrides);
}

it('flashes a success message when creating a contact', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('site-contacts.store'), validSiteContactPayload())
        ->assertRedirect()
        ->assertSessionHas('success', 'Contact created successfully.');
});

it('rejects an invalid mobile number on contact creation', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('site-contacts.store'), validSiteContactPayload([
            'mobile_number' => 'not-a-number',
        ]))
        ->assertSessionHasErrors('mobile_number');

    expect(SiteContact::count())->toBe(0);
});

it('rejects an invalid mobile number on contact update', function () {
    $contact = SiteContact::create(validSiteContactPayload());

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('site-contacts.update', $contact), validSiteContactPayload([
            'mobile_number' => 'call-me-maybe',
        ]))
        ->assertSessionHasErrors('mobile_number');

    expect($contact->fresh()->mobile_number)->toBe('09171234567');
});

it('rejects a duplicate contact with the same name, mobile number, and email', function () {
    SiteContact::create(validSiteContactPayload());

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('site-contacts.store'), validSiteContactPayload())
        ->assertSessionHasErrors();

    expect(SiteContact::count())->toBe(1);
});

it('allows a same-name contact when the mobile number and email differ', function () {
    SiteContact::create(validSiteContactPayload());

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('site-contacts.store'), validSiteContactPayload([
            'mobile_number' => '09179998888',
            'email' => 'juan.other@example.com',
        ]))
        ->assertSessionDoesntHaveErrors();

    expect(SiteContact::count())->toBe(2);
});

it('blocks deleting a contact linked to an active pipeline', function () {
    $contact = SiteContact::create(validSiteContactPayload());
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
        'contact_id' => $contact->id,
    ]);
    Pipeline::create([
        'name' => 'Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => User::factory()->create()->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospect,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete(route('site-contacts.destroy', $contact))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(SiteContact::find($contact->id))->not->toBeNull();
});

it('allows deleting a contact whose only linked pipeline is Inactive', function () {
    $contact = SiteContact::create(validSiteContactPayload());
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
        'contact_id' => $contact->id,
    ]);
    Pipeline::create([
        'name' => 'Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => User::factory()->create()->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Inactive,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete(route('site-contacts.destroy', $contact))
        ->assertRedirect()
        ->assertSessionHas('success', 'Contact deleted.');

    expect(SiteContact::withTrashed()->findOrFail($contact->id)->trashed())->toBeTrue();
});

it('allows deleting a contact with no linked pipelines', function () {
    $contact = SiteContact::create(validSiteContactPayload());

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete(route('site-contacts.destroy', $contact))
        ->assertRedirect()
        ->assertSessionHas('success', 'Contact deleted.');

    expect(SiteContact::withTrashed()->findOrFail($contact->id)->trashed())->toBeTrue();
});
