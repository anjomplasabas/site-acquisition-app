<?php

use App\Enums\NotificationType;
use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\SiteProfile;
use App\Models\User;
use App\Notifications\PipelineNotification;

function notifiedPipeline(User $manager): Pipeline
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Notification Controller Test Site '.uniqid(),
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    return Pipeline::create([
        'name' => 'Notification Controller Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospect,
    ]);
}

it('marks a single notification as read and redirects to its url', function () {
    $user = User::factory()->create();
    $pipeline = notifiedPipeline($user);
    $url = route('pipelines.show', $pipeline);

    $user->notify(new PipelineNotification(NotificationType::SaqAssigned, $pipeline, 'Test message.', $url));
    $notification = $user->notifications()->first();

    expect($notification->read_at)->toBeNull();

    $this->actingAs($user)
        ->post(route('notifications.read', $notification->id))
        ->assertRedirect($url);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('redirects to the dashboard with an error when the notified pipeline no longer exists', function () {
    $user = User::factory()->create();
    $pipeline = notifiedPipeline($user);
    $url = route('pipelines.show', $pipeline);

    $user->notify(new PipelineNotification(NotificationType::SaqAssigned, $pipeline, 'Test message.', $url));
    $notification = $user->notifications()->first();

    $pipeline->delete();

    $this->actingAs($user)
        ->post(route('notifications.read', $notification->id))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error');

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $user = User::factory()->create();
    $pipeline = notifiedPipeline($user);

    foreach (range(1, 3) as $i) {
        $user->notify(new PipelineNotification(NotificationType::SaqAssigned, $pipeline, "Message {$i}.", route('pipelines.show', $pipeline)));
    }

    expect($user->unreadNotifications()->count())->toBe(3);

    $this->actingAs($user)
        ->post(route('notifications.readAll'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('prevents marking another users notification as read', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $pipeline = notifiedPipeline($owner);

    $owner->notify(new PipelineNotification(NotificationType::SaqAssigned, $pipeline, 'Test message.', route('pipelines.show', $pipeline)));
    $notification = $owner->notifications()->first();

    $this->actingAs($intruder)
        ->post(route('notifications.read', $notification->id))
        ->assertRedirect(route('dashboard'));

    expect($notification->fresh()->read_at)->toBeNull();
});
