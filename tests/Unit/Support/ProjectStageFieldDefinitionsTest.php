<?php

use App\Enums\ProjectStage;
use App\Support\ProjectStageFieldDefinitions;

it('gives Monitoring\'s Infrastructure Deployment Status field a group title matching its label', function () {
    // The group heading previously read "Deployment Status"
    // while the field itself was labeled "Infrastructure Deployment Status", so the UI rendered
    // both side by side — a near-duplicate label. StagePanel.vue hides a field's own label when
    // it exactly matches its group's title, so the two strings must match for that to apply here.
    $field = collect(ProjectStageFieldDefinitions::forStage(ProjectStage::Monitoring))
        ->firstWhere('key', 'infrastructure_deployment_status');

    expect($field['group'])->toBe($field['label']);
});

it('never puts a lone field in a group whose title differs from the field\'s own label', function (ProjectStage $stage) {
    // StagePanel.vue only suppresses a redundant field label
    // when `field.label === group.title`. A group with exactly one field and a mismatched title
    // renders as two adjacent, near-identical headings.
    $mismatches = collect(ProjectStageFieldDefinitions::forStage($stage))
        ->groupBy('group')
        ->filter(fn ($groupFields) => $groupFields->count() === 1)
        ->reject(fn ($groupFields, $group) => $groupFields->first()['label'] === $group)
        ->keys();

    expect($mismatches)->toBeEmpty();
})->with(ProjectStage::cases());
