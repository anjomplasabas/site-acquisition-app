<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

const TASK_STATUSES = ['Pending', 'In-Progress', 'Done']

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
    stageTasks: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    projectId: { type: [String, Number], required: true },
    stageId: { type: [String, Number], required: true },
})

function baseUrl(task) {
    return `/projects/${props.projectId}/stages/${props.stageId}/stage-tasks/${task.id}`
}

function ageFor(task) {
    if (!task.status_changed_at) { return '' }

    const seconds = Math.floor((Date.now() - new Date(task.status_changed_at)) / 1000)
    if (seconds < 60) { return 'just now' }

    const units = [
        ['year', 31536000], ['month', 2592000], ['week', 604800],
        ['day', 86400], ['hour', 3600], ['minute', 60],
    ]

    for (const [label, secondsPerUnit] of units) {
        const value = Math.floor(seconds / secondsPerUnit)
        if (value >= 1) { return `${value}${label[0]} ago` }
    }

    return 'just now'
}

// Weighted % — only a Done task counts, at its full weight. `weight` may arrive as a decimal
// string (e.g. "9.50") since it's backed by a decimal-cast column, so coerce with Number() —
// otherwise `+` would concatenate rather than add.
const weightedProgressPct = computed(() =>
    Math.round(
        props.stageTasks
            .filter(task => (taskFormFor(task).status) === 'Done')
            .reduce((sum, task) => sum + Number(task.weight ?? 0), 0)
        * 100
    ) / 100
)

function displayWeight(task) {
    return Number(task.weight ?? 0)
}

// Purely presentational grouping — null when a task carries no section/subsection (e.g.
// Monitoring's Infrastructure Deployment Status), so those checklists render exactly as before.
function sectionHeaderFor(index) {
    const task = props.stageTasks[index]
    if (!task?.section) { return null }

    const previous = props.stageTasks[index - 1]
    return task.section !== previous?.section ? task.section : null
}

function subsectionHeaderFor(index) {
    const task = props.stageTasks[index]
    if (!task?.subsection) { return null }

    const previous = props.stageTasks[index - 1]
    if (task.section !== previous?.section) { return task.subsection }

    return task.subsection !== previous?.subsection ? task.subsection : null
}

function sectionWeightTotal(section) {
    return Math.round(
        props.stageTasks.filter(task => task.section === section).reduce((sum, task) => sum + Number(task.weight ?? 0), 0)
        * 100
    ) / 100
}

function subsectionWeightTotal(section, subsection) {
    return Math.round(
        props.stageTasks
            .filter(task => task.section === section && task.subsection === subsection)
            .reduce((sum, task) => sum + Number(task.weight ?? 0), 0)
        * 100
    ) / 100
}

// ── Task status ────────────────────────────────────────────────────────────
const taskForms = reactive({})

function taskFormFor(task) {
    if (!taskForms[task.id]) {
        taskForms[task.id] = useForm({ status: task.status })
    }

    return taskForms[task.id]
}

function saveTaskStatus(task) {
    taskFormFor(task).patch(baseUrl(task), { preserveScroll: true })
}

// ── Complete section ─────────────────────────────────────────────────────────
// Per-phase shortcut (Initial Draft / In Progress Design Plan - Fiber / Final Checking for
// Plans/Drawing; MDF Installation / Backhaul Installation / etc for Monitoring's Infrastructure
// checklist) rather than one blanket "complete everything" action — a section is a natural,
// reviewable unit of work, unlike the full ~29-item list at once.
const completeSectionForm = useForm({ field_key: '', section: '' })

function tasksInSection(section) {
    return props.stageTasks.filter(task => task.section === section)
}

function sectionHasIncomplete(section) {
    return tasksInSection(section).some(task => taskFormFor(task).status !== 'Done')
}

function completeSection(section) {
    const tasks = tasksInSection(section)
    if (!tasks.length) { return }
    if (!confirm(`Mark all ${tasks.length} tasks in "${section}" as Done?`)) { return }

    const fieldKey = tasks[0].field_key

    completeSectionForm.transform(() => ({ field_key: fieldKey, section })).patch(
        `/projects/${props.projectId}/stages/${props.stageId}/stage-tasks/complete-all`,
        {
            preserveScroll: true,
            onSuccess: () => {
                // Every task's status useForm is already instantiated by this point (the
                // template calls taskFormFor(task) unconditionally per row), so the fresh
                // stageTasks prop from this reload won't be picked up by those already-cached
                // form instances — sync them locally so the UI reflects Done immediately.
                tasks.forEach(task => { taskFormFor(task).status = 'Done' })
            },
        }
    )
}

// Top-bar shortcut that marks every task Done regardless of section — distinct from
// completeSection()'s per-phase shortcut above. Omitting `section` entirely from the request
// (rather than sending `section: null`) tells StageTaskController::completeAll to skip the
// section filter altogether, so this also covers any sectionless checklist in one request.
const hasIncompleteTasks = computed(() => props.stageTasks.some(task => taskFormFor(task).status !== 'Done'))

function completeAllTasks() {
    const tasks = props.stageTasks.filter(task => taskFormFor(task).status !== 'Done')
    if (!tasks.length) { return }
    if (!confirm(`Mark all ${tasks.length} tasks as Done?`)) { return }

    const fieldKey = tasks[0].field_key

    completeSectionForm.transform(() => ({ field_key: fieldKey })).patch(
        `/projects/${props.projectId}/stages/${props.stageId}/stage-tasks/complete-all`,
        {
            preserveScroll: true,
            onSuccess: () => {
                tasks.forEach(task => { taskFormFor(task).status = 'Done' })
            },
        }
    )
}

// ── Task notes ─────────────────────────────────────────────────────────────
const notesForms = reactive({})

function notesFormFor(task) {
    if (!notesForms[task.id]) {
        notesForms[task.id] = useForm({ notes: task.notes ?? '' })
    }

    return notesForms[task.id]
}

function saveTaskNotes(task) {
    notesFormFor(task).patch(baseUrl(task), { preserveScroll: true })
}

// ── Task documents ────────────────────────────────────────────────────────────
const uploadForms = reactive({})

function uploadFormFor(task) {
    if (!uploadForms[task.id]) {
        uploadForms[task.id] = useForm({ files: [] })
    }

    return uploadForms[task.id]
}

// Every card starts collapsed so a long checklist (Planning's Plans/Drawing runs to ~29 cards)
// stays scannable — the chevron expands an individual card to reach its notes/attachments,
// tracked here only for the ones a user has opened.
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

function submitTaskUpload(task) {
    uploadFormFor(task).post(`${baseUrl(task)}/documents`, {
        preserveScroll: true,
        onSuccess: () => uploadFormFor(task).reset('files'),
    })
}

function taskDocumentUrl(task, document) {
    return `${baseUrl(task)}/documents/${document.id}`
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3 px-5 py-4 rounded-xl border border-outline/15 bg-surface-container-lowest">
            <span class="text-sm font-bold text-on-surface flex-1">Weighted Progress</span>
            <button
                v-if="!disabled && hasIncompleteTasks"
                type="button"
                @click="completeAllTasks"
                :disabled="completeSectionForm.processing"
                class="flex items-center gap-1.5 text-xs font-semibold text-primary border border-primary/30 bg-primary/5 hover:bg-primary/10 rounded-lg px-3 py-1.5 transition-colors disabled:opacity-60 shrink-0"
            >
                <span class="material-symbols-outlined text-[15px]">done_all</span>
                Complete All
            </button>
            <div class="flex flex-col items-end gap-1 w-32">
                <span class="text-xs font-bold text-on-surface-variant whitespace-nowrap">{{ weightedProgressPct }}% done</span>
                <div class="w-full h-2 bg-surface-container rounded-full overflow-hidden">
                    <div class="h-full bg-primary rounded-full transition-all" :style="{ width: `${weightedProgressPct}%` }" />
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <template v-for="(task, index) in stageTasks" :key="task.id">
                <div v-if="sectionHeaderFor(index)" class="flex items-center justify-between gap-2 px-1 pt-3 first:pt-0">
                    <span class="text-xs font-bold uppercase tracking-widest text-on-surface">{{ sectionHeaderFor(index) }}</span>
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            v-if="!disabled && sectionHasIncomplete(sectionHeaderFor(index))"
                            type="button"
                            @click="completeSection(sectionHeaderFor(index))"
                            :disabled="completeSectionForm.processing"
                            class="flex items-center gap-1 text-[10px] font-bold text-primary border border-primary/30 bg-primary/5 hover:bg-primary/10 rounded-full px-2.5 py-1 transition-colors disabled:opacity-60"
                        >
                            <span class="material-symbols-outlined text-[12px]">done_all</span>
                            Complete
                        </button>
                        <span class="text-xs font-bold text-on-surface-variant">{{ sectionWeightTotal(sectionHeaderFor(index)) }}%</span>
                    </div>
                </div>
                <div v-if="subsectionHeaderFor(index)" class="flex items-center justify-between px-3 pt-1">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ subsectionHeaderFor(index) }}</span>
                    <span class="text-[10px] font-semibold text-on-surface-variant/70">{{ subsectionWeightTotal(task.section, subsectionHeaderFor(index)) }}%</span>
                </div>

                <div
                    class="relative overflow-hidden rounded-lg border transition-colors"
                    :class="STATUS_CARD_CLASS[taskFormFor(task).status]"
                >
                <span class="absolute inset-y-0 left-0 w-1" :class="STATUS_ACCENT_CLASS[taskFormFor(task).status]" />

                <div class="flex flex-col gap-2 px-3 py-2 pl-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <span
                            class="material-symbols-outlined text-[18px] shrink-0"
                            :class="STATUS_ICON_CLASS[taskFormFor(task).status]"
                        >{{ STATUS_ICON[taskFormFor(task).status] }}</span>

                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-on-surface truncate">{{ task.name }}</p>
                            <p class="text-[10px] text-on-surface-variant/70">
                                <span class="font-mono font-semibold text-on-surface-variant">{{ displayWeight(task) }}% weight</span>
                                <span v-if="ageFor(task)"> · updated {{ ageFor(task) }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 sm:justify-end">
                        <select
                            v-model="taskFormFor(task).status"
                            :disabled="disabled"
                            @change="saveTaskStatus(task)"
                            class="text-[11px] font-bold rounded-full pl-2.5 pr-6 py-1 border-0 appearance-none bg-[right_0.45rem_center] bg-no-repeat focus:outline-none focus:ring-2 focus:ring-primary/50 disabled:opacity-60"
                            :class="STATUS_PILL_CLASS[taskFormFor(task).status]"
                            style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2210%22 height=%2210%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%223%22><polyline points=%226 9 12 15 18 9%22/></svg>')"
                        >
                            <option v-for="option in TASK_STATUSES" :key="option" :value="option">{{ option }}</option>
                        </select>

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
                    <div class="flex flex-col gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Notes</span>
                        <textarea
                            v-model="notesFormFor(task).notes"
                            :disabled="disabled"
                            @blur="saveTaskNotes(task)"
                            rows="2"
                            placeholder="Add notes for this task (optional)"
                            class="w-full text-sm bg-surface-container-lowest border border-outline/30 rounded-lg px-3 py-2 resize-none focus:outline-none focus:ring-1 focus:ring-primary/50 disabled:opacity-60"
                        />
                        <span v-if="notesFormFor(task).processing" class="text-[10px] font-semibold text-on-surface-variant">Saving…</span>
                        <span v-else-if="notesFormFor(task).recentlySuccessful" class="text-[10px] font-semibold text-emerald-700">Saved</span>
                    </div>

                    <div class="flex flex-col gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Attachments</span>
                        <DocumentRow
                            v-for="document in task.documents"
                            :key="document.id"
                            :document="document"
                            :replace-url="!disabled ? `${taskDocumentUrl(task, document)}/replace` : null"
                            :delete-url="!disabled ? taskDocumentUrl(task, document) : null"
                            :can-manage="!disabled"
                        />
                        <p v-if="(task.documents ?? []).length === 0" class="text-xs text-on-surface-variant/50">No files attached.</p>

                        <form v-if="!disabled" @submit.prevent="submitTaskUpload(task)" class="flex items-center gap-2 pt-1">
                            <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary border border-primary/30 bg-primary/5 hover:bg-primary/10 rounded-lg px-3 py-1.5 cursor-pointer transition-colors shrink-0">
                                <span class="material-symbols-outlined text-[15px]">attach_file</span>
                                Choose files
                                <input
                                    type="file"
                                    multiple
                                    class="hidden"
                                    @change="uploadFormFor(task).files = Array.from($event.target.files)"
                                />
                            </label>
                            <span class="text-xs text-on-surface-variant truncate flex-1 min-w-0">
                                {{ uploadFormFor(task).files.length ? uploadFormFor(task).files.map((file) => file.name).join(', ') : 'No file chosen' }}
                            </span>
                            <button
                                type="submit"
                                :disabled="!uploadFormFor(task).files.length || uploadFormFor(task).processing"
                                class="bg-primary text-white text-xs font-semibold px-3 py-1.5 rounded-lg disabled:opacity-40 shrink-0"
                            >{{ uploadFormFor(task).processing ? 'Uploading…' : 'Upload' }}</button>
                        </form>
                    </div>
                </div>
                </div>
            </template>
        </div>
    </div>
</template>
