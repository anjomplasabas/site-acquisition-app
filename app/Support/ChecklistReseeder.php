<?php

namespace App\Support;

use App\Enums\IspTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rebuilds a weighted checklist (SitePlanningTask or StageTask rows) from a new set of template
 * definitions while carrying over work that is still valid: a new task whose
 * section+name also existed in the old checklist keeps the old task's status, status_changed_at,
 * notes, and document attachments. Old tasks with no counterpart in the new definitions are
 * dropped — their Document rows and stored files are NOT deleted, they just lose their task pin.
 * Template order and weights always win, so the rebuilt checklist exactly matches the new type's
 * matrix.
 */
class ChecklistReseeder
{
    /**
     * @param  HasMany<Model, Model>  $tasks
     * @param  array<int, array{name: string, weight: int|float, section?: string, subsection?: string}>  $definitions
     * @param  array<string, mixed>  $extraAttributes  attributes every recreated row needs (e.g. StageTask's field_key)
     */
    public static function reseed(HasMany $tasks, array $definitions, array $extraAttributes = []): void
    {
        if ($definitions === []) {
            return;
        }

        $existing = $tasks->with('documents')->get()
            ->keyBy(fn ($task): string => self::carryOverKey($task->section, $task->name));

        foreach ($existing as $task) {
            $task->documents()->detach();
            $task->delete();
        }

        foreach ($definitions as $index => $definition) {
            $previous = $existing->get(self::carryOverKey($definition['section'] ?? null, $definition['name']));

            $task = $tasks->create([
                ...$extraAttributes,
                'section' => $definition['section'] ?? null,
                'subsection' => $definition['subsection'] ?? null,
                'name' => $definition['name'],
                'weight' => $definition['weight'],
                'status' => $previous->status ?? IspTaskStatus::Pending,
                'status_changed_at' => $previous->status_changed_at ?? now(),
                'notes' => $previous->notes ?? null,
                'sort_order' => $index,
            ]);

            if ($previous !== null && $previous->documents->isNotEmpty()) {
                $task->documents()->attach(
                    $previous->documents->pluck('id')->mapWithKeys(fn (int $id): array => [$id => ['created_at' => now()]])->all()
                );
            }
        }
    }

    /**
     * Section+name composite, since some matrices repeat a task name across sections
     * (e.g. FAMILIA's "Installation of Distribution Boxes" appears under both Vertical
     * Distribution and LCP/NAP Installation).
     */
    public static function carryOverKey(?string $section, string $name): string
    {
        return ($section ?? '').'|'.$name;
    }
}
