<script setup>
import { computed, reactive } from 'vue'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

const STATUS_ICON = {
    Pending: 'radio_button_unchecked',
    'In-Progress': 'progress_activity',
    Done: 'check_circle',
}

const STATUS_ICON_CLASS = {
    Pending: 'text-on-surface-variant/40',
    'In-Progress': 'text-amber-600',
    Done: 'text-emerald-600',
}

const STATUS_PILL_CLASS = {
    Pending: 'bg-surface-container text-on-surface-variant',
    'In-Progress': 'bg-amber-100 text-amber-800',
    Done: 'bg-emerald-100 text-emerald-800',
}

const STATUS_ACCENT_CLASS = {
    Pending: 'bg-outline/20',
    'In-Progress': 'bg-amber-400',
    Done: 'bg-emerald-400',
}

const STATUS_CARD_CLASS = {
    Pending: 'border-outline/15 bg-surface-bright',
    'In-Progress': 'border-amber-200 bg-amber-50/40',
    Done: 'border-emerald-200 bg-emerald-50/40',
}

const props = defineProps({
    tasks: { type: Array, default: () => [] },
})

// Weighted % — only a Done task counts, at its full weight. `weight` may arrive as a decimal
// string (e.g. "9.50") since it's backed by a decimal-cast column, so coerce with Number().
const weightedProgressPct = computed(() =>
    Math.round(
        props.tasks
            .filter(task => task.status === 'Done')
            .reduce((sum, task) => sum + Number(task.weight ?? 0), 0)
        * 100
    ) / 100
)

function displayWeight(task) {
    return Number(task.weight ?? 0)
}

// Purely presentational grouping.
function sectionHeaderFor(index) {
    const task = props.tasks[index]
    if (!task?.section) { return null }

    const previous = props.tasks[index - 1]
    return task.section !== previous?.section ? task.section : null
}

function subsectionHeaderFor(index) {
    const task = props.tasks[index]
    if (!task?.subsection) { return null }

    const previous = props.tasks[index - 1]
    if (task.section !== previous?.section) { return task.subsection }

    return task.subsection !== previous?.subsection ? task.subsection : null
}

function sectionWeightTotal(section) {
    return Math.round(
        props.tasks.filter(task => task.section === section).reduce((sum, task) => sum + Number(task.weight ?? 0), 0)
        * 100
    ) / 100
}

function subsectionWeightTotal(section, subsection) {
    return Math.round(
        props.tasks
            .filter(task => task.section === section && task.subsection === subsection)
            .reduce((sum, task) => sum + Number(task.weight ?? 0), 0)
        * 100
    ) / 100
}

// Every card starts collapsed so a long checklist stays scannable — the chevron expands an
// individual card to reach its notes/attachments, tracked here only for the ones opened.
const expandedTaskIds = reactive(new Set())

function isExpanded(task) {
    return expandedTaskIds.has(task.id)
}

function toggleTask(task) {
    if (expandedTaskIds.has(task.id)) {
        expandedTaskIds.delete(task.id)
    } else {
        expandedTaskIds.add(task.id)
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3 px-5 py-4 rounded-xl border border-outline/15 bg-surface-container-lowest">
            <span class="text-sm font-bold text-on-surface flex-1">Weighted Progress</span>
            <div class="flex flex-col items-end gap-1 w-32">
                <span class="text-xs font-bold text-on-surface-variant whitespace-nowrap">{{ weightedProgressPct }}% done</span>
                <div class="w-full h-2 bg-surface-container rounded-full overflow-hidden">
                    <div class="h-full bg-primary rounded-full transition-all" :style="{ width: `${weightedProgressPct}%` }" />
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <template v-for="(task, index) in tasks" :key="task.id">
                <div v-if="sectionHeaderFor(index)" class="flex items-center justify-between gap-2 px-1 pt-3 first:pt-0">
                    <span class="text-xs font-bold uppercase tracking-widest text-on-surface">{{ sectionHeaderFor(index) }}</span>
                    <span class="text-xs font-bold text-on-surface-variant">{{ sectionWeightTotal(sectionHeaderFor(index)) }}%</span>
                </div>
                <div v-if="subsectionHeaderFor(index)" class="flex items-center justify-between px-3 pt-1">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ subsectionHeaderFor(index) }}</span>
                    <span class="text-[10px] font-semibold text-on-surface-variant/70">{{ subsectionWeightTotal(task.section, subsectionHeaderFor(index)) }}%</span>
                </div>

                <div
                    class="relative overflow-hidden rounded-lg border transition-colors"
                    :class="STATUS_CARD_CLASS[task.status]"
                >
                <span class="absolute inset-y-0 left-0 w-1" :class="STATUS_ACCENT_CLASS[task.status]" />

                <div class="flex flex-col gap-2 px-3 py-2 pl-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <span
                            class="material-symbols-outlined text-[18px] shrink-0"
                            :class="STATUS_ICON_CLASS[task.status]"
                        >{{ STATUS_ICON[task.status] }}</span>

                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-on-surface truncate">{{ task.name }}</p>
                            <p class="text-[10px] text-on-surface-variant/70">
                                <span class="font-mono font-semibold text-on-surface-variant">{{ displayWeight(task) }}% weight</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 sm:justify-end">
                        <span
                            class="text-[11px] font-bold rounded-full px-2.5 py-1"
                            :class="STATUS_PILL_CLASS[task.status]"
                        >{{ task.status }}</span>

                        <button
                            type="button"
                            @click="toggleTask(task)"
                            class="flex items-center gap-1 text-[11px] font-semibold text-on-surface-variant rounded-lg px-1.5 py-1 hover:text-primary hover:bg-surface-container/60 transition-colors"
                        >
                            <span class="material-symbols-outlined text-[14px]">attach_file</span>
                            {{ (task.documents ?? []).length }}
                            <span
                                class="material-symbols-outlined text-[16px] transition-transform"
                                :class="{ 'rotate-180': isExpanded(task) }"
                            >expand_more</span>
                        </button>
                    </div>
                </div>

                <div v-if="isExpanded(task)" class="px-3 pb-3 pl-4 flex flex-col gap-3 border-t border-outline/10 pt-3">
                    <div v-if="task.notes" class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Notes</span>
                        <p class="text-sm text-on-surface whitespace-pre-wrap">{{ task.notes }}</p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Attachments</span>
                        <DocumentRow v-for="document in task.documents" :key="document.id" :document="document" />
                        <p v-if="(task.documents ?? []).length === 0" class="text-xs text-on-surface-variant/50">No files attached.</p>
                    </div>
                </div>
                </div>
            </template>
        </div>
    </div>
</template>
