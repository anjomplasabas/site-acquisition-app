<script setup>
import { computed, reactive } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { ageFrom } from '@/utils/pipeline.js'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

const props = defineProps({
    assignment: Object,
    providerStatuses: Array,
    canManageFeasibility: Boolean,
    canCompleteFeasibility: Boolean,
})

const createForm = useForm({
    isp_name: '',
    contact_name: '',
    contact_email: '',
    status: 'Checking',
    notes: '',
})

const editForms = reactive({})
const uploadForms = reactive({})
const deleteForm = useForm({})
const completeForm = useForm({})
const refreshOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['assignment', 'can', 'activityLogs', 'flash'],
}

// Any tower's project unlocks the JO checklist — pipeline.project (the untowered default)
// is null once a site declares towers.
const hasProject = (props.assignment.pipeline.projects ?? []).length > 0

// Feasibility entries (add/edit/delete/documents) may only be managed while the pipeline is
// still Site Survey Pending or Site Survey — mirrors NetworkController::EDITABLE_STATUSES.
// Computed (not a one-off const) so the section locks immediately once Network Feasibility is
// marked Completed, without needing a full page reload.
const isEditableWindow = computed(() => ['Site Survey Pending', 'Site Survey'].includes(props.assignment.pipeline.status))
const canEditFeasibility = computed(() => props.canManageFeasibility && isEditableWindow.value && !props.assignment.site_survey?.network_feasibility_completed_at)

const STATUS_BADGES = {
    Checking: 'bg-amber-50 text-amber-700',
    Feasible: 'bg-emerald-50 text-emerald-700',
    'Non Feasible': 'bg-red-50 text-red-700',
}

function statusBadge(status) {
    return STATUS_BADGES[status] ?? 'bg-surface-container text-on-surface-variant'
}

const TASK_STATUSES = ['Pending', 'In-Progress', 'Done']

const TASK_STATUS_PILL = {
    Pending: 'bg-surface-container text-on-surface-variant',
    'In-Progress': 'bg-amber-100 text-amber-800',
    Done: 'bg-emerald-100 text-emerald-800',
}

const TASK_STATUS_ICON = {
    Pending: 'radio_button_unchecked',
    'In-Progress': 'progress_activity',
    Done: 'check_circle',
}

const TASK_STATUS_ICON_CLASS = {
    Pending: 'text-on-surface-variant/40',
    'In-Progress': 'text-amber-600',
    Done: 'text-emerald-600',
}

const TASK_CARD_CLASS = {
    Pending: 'border-outline/15 bg-surface-bright',
    'In-Progress': 'border-amber-200 bg-amber-50/40',
    Done: 'border-emerald-200 bg-emerald-50/40',
}

// ── JO checklist (task status + per-task document) ────────────────────────────
// Every card starts collapsed, same pattern as Project Management's weighted checklists
// (StageTaskListField.vue) — keeps a 9-task list scannable at a glance; the chevron expands an
// individual card to reach its notes/document.
const expandedTaskIds = reactive(new Set())

function isTaskExpanded(task) {
    return expandedTaskIds.has(task.id)
}

function toggleTaskExpanded(task) {
    if (expandedTaskIds.has(task.id)) {
        expandedTaskIds.delete(task.id)
    } else {
        expandedTaskIds.add(task.id)
    }
}

const taskForms = reactive({})
const taskUploadForms = reactive({})

function taskFormFor(task) {
    if (!taskForms[task.id]) {
        taskForms[task.id] = useForm({ status: task.status })
    }

    return taskForms[task.id]
}

function saveTaskStatus(entry, task) {
    taskFormFor(task).patch(`/network/${props.assignment.id}/feasibilities/${entry.id}/tasks/${task.id}`, refreshOptions)
}

const taskNotesForms = reactive({})

function taskNotesFormFor(task) {
    if (!taskNotesForms[task.id]) {
        taskNotesForms[task.id] = useForm({ notes: task.notes ?? '' })
    }

    return taskNotesForms[task.id]
}

function saveTaskNotes(entry, task) {
    taskNotesFormFor(task).patch(`/network/${props.assignment.id}/feasibilities/${entry.id}/tasks/${task.id}`, refreshOptions)
}

function taskUploadFormFor(task) {
    if (!taskUploadForms[task.id]) {
        taskUploadForms[task.id] = useForm({ files: [] })
    }

    return taskUploadForms[task.id]
}

function submitTaskUpload(entry, task) {
    taskUploadFormFor(task).post(`/network/${props.assignment.id}/feasibilities/${entry.id}/tasks/${task.id}/documents`, {
        ...refreshOptions,
        onSuccess: () => {
            taskUploadFormFor(task).reset('files')
            // Uploading a document auto-completes a requires_document task server-side —
            // reflect that immediately rather than waiting for the next full reload.
            if (task.requires_document) {
                taskFormFor(task).status = 'Done'
            }
        },
    })
}

function taskDocumentUrl(entry, task, document) {
    return `/network/${props.assignment.id}/feasibilities/${entry.id}/tasks/${task.id}/documents/${document.id}`
}

function formFor(entry) {
    if (!editForms[entry.id]) {
        editForms[entry.id] = useForm({
            isp_name: entry.isp_name ?? '',
            contact_name: entry.contact_name ?? '',
            contact_email: entry.contact_email ?? '',
            status: entry.status ?? 'Checking',
            notes: entry.notes ?? '',
        })
    }

    return editForms[entry.id]
}

function addEntry() {
    createForm.post(`/network/${props.assignment.id}/feasibilities`, {
        ...refreshOptions,
        onSuccess: () => createForm.reset(),
    })
}

function updateEntry(entry) {
    formFor(entry).patch(`/network/${props.assignment.id}/feasibilities/${entry.id}`, refreshOptions)
}

function deleteEntry(entry) {
    if (!confirm(`Remove feasibility for "${entry.isp_name}"?`)) { return }
    deleteForm.delete(`/network/${props.assignment.id}/feasibilities/${entry.id}`, refreshOptions)
}

function uploadFormFor(entry) {
    if (!uploadForms[entry.id]) {
        uploadForms[entry.id] = useForm({ files: [] })
    }

    return uploadForms[entry.id]
}

function submitUpload(entry) {
    uploadFormFor(entry).post(`/network/${props.assignment.id}/feasibilities/${entry.id}/documents`, {
        ...refreshOptions,
        onSuccess: () => uploadFormFor(entry).reset('files'),
    })
}

function feasibilityDocumentUrl(entry, document) {
    return `/network/${props.assignment.id}/feasibilities/${entry.id}/documents/${document.id}`
}

function completeNetworkFeasibility() {
    completeForm.post(`/network/${props.assignment.id}/complete-feasibility`, refreshOptions)
}
</script>

<template>
    <div class="bg-surface-bright rounded-2xl border border-outline/10 p-7">
        <div class="flex flex-col gap-4 mb-7 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-blue-600 text-[22px]">lan</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-on-surface">Network Feasibility</p>
                    <p class="text-xs text-on-surface-variant mt-0.5">Track ISP feasibility checks for this site.</p>
                </div>
            </div>
            <button
                v-if="canCompleteFeasibility && assignment.pipeline.status === 'Site Survey' && !assignment.site_survey?.network_feasibility_completed_at"
                type="button"
                :disabled="completeForm.processing"
                @click="completeNetworkFeasibility"
                class="flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition-all hover:brightness-110 disabled:opacity-60 shrink-0"
            >
                <span v-if="completeForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                <span v-else class="material-symbols-outlined text-[16px]">task_alt</span>
                Complete Network Feasibility
            </button>
            <div v-else-if="assignment.site_survey?.network_feasibility_completed_at" class="flex flex-col items-end gap-1 shrink-0">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700"
                >
                    <span class="material-symbols-outlined text-[15px]">check_circle</span>
                    Feasibility Completed
                </span>
                <span class="text-[10px] text-on-surface-variant/70">{{ ageFrom(assignment.site_survey.network_feasibility_completed_at) }}</span>
            </div>
        </div>

        <form v-if="canEditFeasibility" @submit.prevent="addEntry" class="bg-surface-container-low rounded-xl p-5 mb-6">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Add ISP</p>
            <div class="grid grid-cols-1 sm:grid-cols-[1fr_180px_auto] gap-3">
                <input
                    v-model="createForm.isp_name"
                    type="text"
                    placeholder="ISP name"
                    class="bg-surface-bright border rounded-xl px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                    :class="createForm.errors.isp_name ? 'border-error' : 'border-outline/40'"
                />
                <select v-model="createForm.status" class="bg-surface-bright border border-outline/40 rounded-xl px-4 py-2.5 text-sm text-on-surface">
                    <option v-for="status in providerStatuses" :key="status" :value="status">{{ status }}</option>
                </select>
                <button type="submit" :disabled="createForm.processing" class="flex items-center justify-center gap-1.5 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-xl disabled:opacity-60 hover:brightness-110 transition-all">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Add
                </button>
                <div class="sm:col-span-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input
                        v-model="createForm.contact_name"
                        type="text"
                        placeholder="Contact person name (optional)"
                        class="bg-surface-bright border rounded-xl px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                        :class="createForm.errors.contact_name ? 'border-error' : 'border-outline/40'"
                    />
                    <input
                        v-model="createForm.contact_email"
                        type="email"
                        placeholder="Contact person email (optional)"
                        class="bg-surface-bright border rounded-xl px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                        :class="createForm.errors.contact_email ? 'border-error' : 'border-outline/40'"
                    />
                </div>
                <textarea
                    v-model="createForm.notes"
                    rows="2"
                    placeholder="Notes (optional)"
                    class="sm:col-span-3 bg-surface-bright border rounded-xl px-4 py-2.5 text-sm text-on-surface resize-none focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                    :class="createForm.errors.notes ? 'border-error' : 'border-outline/40'"
                />
            </div>
            <p v-if="createForm.errors.isp_name" class="text-xs text-error mt-2">{{ createForm.errors.isp_name }}</p>
            <p v-if="createForm.errors.contact_name" class="text-xs text-error mt-2">{{ createForm.errors.contact_name }}</p>
            <p v-if="createForm.errors.contact_email" class="text-xs text-error mt-2">{{ createForm.errors.contact_email }}</p>
            <p v-if="createForm.errors.notes" class="text-xs text-error mt-2">{{ createForm.errors.notes }}</p>
        </form>

        <div v-if="assignment.site_survey?.network_feasibilities?.length" class="flex flex-col gap-3">
            <div
                v-for="entry in assignment.site_survey.network_feasibilities"
                :key="entry.id"
                class="border border-outline/10 rounded-xl p-5"
            >
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-on-surface truncate">{{ entry.isp_name }}</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold mt-1.5" :class="statusBadge(entry.status)">
                            {{ entry.status }}
                        </span>
                        <p class="text-[11px] text-on-surface-variant/70 mt-1.5">
                            Added {{ ageFrom(entry.created_at) }}
                            <template v-if="entry.feasible_at"> · Made feasible {{ ageFrom(entry.feasible_at) }}</template>
                        </p>
                        <p v-if="entry.contact_name || entry.contact_email" class="text-xs text-on-surface-variant mt-1.5 truncate">
                            <span class="material-symbols-outlined text-[13px] align-middle mr-1">person</span>
                            <span v-if="entry.contact_name">{{ entry.contact_name }}</span>
                            <span v-if="entry.contact_name && entry.contact_email"> · </span>
                            <span v-if="entry.contact_email">{{ entry.contact_email }}</span>
                        </p>
                    </div>
                    <button
                        v-if="canEditFeasibility"
                        type="button"
                        :disabled="deleteForm.processing"
                        @click="deleteEntry(entry)"
                        class="p-2 text-on-surface-variant hover:text-error hover:bg-error/5 rounded-lg transition-colors shrink-0"
                        title="Delete"
                    >
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                    </button>
                </div>

                <form v-if="canEditFeasibility" @submit.prevent="updateEntry(entry)" class="grid grid-cols-1 sm:grid-cols-[1fr_160px_auto] gap-3">
                    <input v-model="formFor(entry).isp_name" type="text" class="bg-surface-container-low border border-outline/30 rounded-xl px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                    <select v-model="formFor(entry).status" class="bg-surface-container-low border border-outline/30 rounded-xl px-4 py-2.5 text-sm text-on-surface">
                        <option v-for="status in providerStatuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                    <button type="submit" :disabled="formFor(entry).processing" class="flex items-center justify-center gap-1.5 border border-outline/30 text-on-surface font-semibold text-sm px-4 py-2.5 rounded-xl hover:bg-surface-container-low transition-colors">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        Save
                    </button>
                    <div class="sm:col-span-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input
                            v-model="formFor(entry).contact_name"
                            type="text"
                            placeholder="Contact person name"
                            class="bg-surface-container-low border rounded-xl px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                            :class="formFor(entry).errors.contact_name ? 'border-error' : 'border-outline/30'"
                        />
                        <input v-model="formFor(entry).contact_email" type="email" placeholder="Contact person email" class="bg-surface-container-low border border-outline/30 rounded-xl px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                    </div>
                    <textarea
                        v-model="formFor(entry).notes"
                        rows="2"
                        placeholder="Notes"
                        class="sm:col-span-3 bg-surface-container-low border rounded-xl px-4 py-2.5 text-sm text-on-surface resize-none focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                        :class="formFor(entry).errors.notes ? 'border-error' : 'border-outline/30'"
                    />
                    <p v-if="formFor(entry).errors.contact_name" class="sm:col-span-3 text-xs text-error">{{ formFor(entry).errors.contact_name }}</p>
                    <p v-if="formFor(entry).errors.contact_email" class="sm:col-span-3 text-xs text-error">{{ formFor(entry).errors.contact_email }}</p>
                    <p v-if="formFor(entry).errors.notes" class="sm:col-span-3 text-xs text-error">{{ formFor(entry).errors.notes }}</p>
                </form>
                <p v-else-if="entry.notes" class="text-sm text-on-surface-variant mt-1">{{ entry.notes }}</p>

                <div class="mt-4 pt-4 border-t border-outline/10">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-2">Attachments</p>
                    <div v-if="(entry.documents ?? []).length" class="flex flex-col gap-1.5 mb-3">
                        <DocumentRow
                            v-for="document in entry.documents"
                            :key="document.id"
                            :document="document"
                            :replace-url="canEditFeasibility ? `${feasibilityDocumentUrl(entry, document)}/replace` : null"
                            :delete-url="canEditFeasibility ? feasibilityDocumentUrl(entry, document) : null"
                            :can-manage="canEditFeasibility"
                        />
                    </div>
                    <p v-else class="text-xs text-on-surface-variant/50 mb-3">No files attached.</p>

                    <form v-if="canEditFeasibility" @submit.prevent="submitUpload(entry)" class="flex items-center gap-2">
                        <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary border border-primary/30 bg-primary/5 hover:bg-primary/10 rounded-lg px-3 py-1.5 cursor-pointer transition-colors shrink-0">
                            <span class="material-symbols-outlined text-[15px]">attach_file</span>
                            Choose files
                            <input
                                type="file"
                                multiple
                                class="hidden"
                                @change="uploadFormFor(entry).files = Array.from($event.target.files)"
                            />
                        </label>
                        <span class="text-xs text-on-surface-variant truncate flex-1 min-w-0">
                            {{ uploadFormFor(entry).files.length ? uploadFormFor(entry).files.map((file) => file.name).join(', ') : 'No file chosen' }}
                        </span>
                        <button
                            type="submit"
                            :disabled="!uploadFormFor(entry).files.length || uploadFormFor(entry).processing"
                            class="bg-primary text-white text-xs font-semibold px-3 py-1.5 rounded-lg disabled:opacity-40 shrink-0"
                        >{{ uploadFormFor(entry).processing ? 'Uploading…' : 'Upload' }}</button>
                    </form>
                    <p v-if="uploadFormFor(entry).errors['files.0']" class="text-xs text-error mt-1">{{ uploadFormFor(entry).errors['files.0'] }}</p>
                </div>

                <!-- JO checklist — only unlocks once the pipeline is approved and a Project exists.
                     Tasks themselves may not be seeded yet: they're only created once MOA and NTP
                     are both attached, either at the pipeline's Commercials stage or later on the
                     project's Planning stage — see NetworkFeasibilityTaskSeeder. -->
                <div v-if="hasProject" class="mt-4 pt-4 border-t border-outline/10">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-2">Task Checklist</p>
                    <p v-if="!(entry.tasks ?? []).length" class="text-xs text-on-surface-variant/60 italic">
                        Task checklist will appear once MOA and NTP are uploaded and approved.
                    </p>
                    <div v-else class="flex flex-col gap-1.5">
                        <div
                            v-for="task in entry.tasks ?? []"
                            :key="task.id"
                            class="rounded-lg border transition-colors"
                            :class="TASK_CARD_CLASS[taskFormFor(task).status]"
                        >
                            <div class="flex items-center gap-2 px-3 py-2">
                                <span
                                    class="material-symbols-outlined text-[16px] shrink-0"
                                    :class="TASK_STATUS_ICON_CLASS[taskFormFor(task).status]"
                                >{{ TASK_STATUS_ICON[taskFormFor(task).status] }}</span>
                                <span class="text-sm text-on-surface flex-1 min-w-0 truncate">{{ task.name }}</span>
                                <select
                                    v-model="taskFormFor(task).status"
                                    :disabled="!canManageFeasibility"
                                    @change="saveTaskStatus(entry, task)"
                                    class="text-[11px] font-bold rounded-full px-2.5 py-1 border-0 shrink-0 focus:outline-none focus:ring-1 focus:ring-primary/50 disabled:opacity-60"
                                    :class="TASK_STATUS_PILL[taskFormFor(task).status]"
                                >
                                    <option v-for="option in TASK_STATUSES" :key="option" :value="option">{{ option }}</option>
                                </select>
                                <button
                                    type="button"
                                    @click="toggleTaskExpanded(task)"
                                    class="flex items-center gap-1 text-[11px] font-semibold text-on-surface-variant rounded-lg px-1.5 py-1 hover:text-primary hover:bg-surface-container/60 transition-colors shrink-0"
                                >
                                    <template v-if="task.requires_document">
                                        <span class="material-symbols-outlined text-[13px]">attach_file</span>
                                        {{ (task.documents ?? []).length }}
                                    </template>
                                    <span
                                        class="material-symbols-outlined text-[15px] transition-transform"
                                        :class="{ 'rotate-180': isTaskExpanded(task) }"
                                    >expand_more</span>
                                </button>
                            </div>

                            <p v-if="taskFormFor(task).errors.status" class="text-xs text-error px-3 pb-2">
                                {{ taskFormFor(task).errors.status }}
                            </p>

                            <div v-if="isTaskExpanded(task)" class="px-3 pb-3 flex flex-col gap-2 border-t border-outline/10 pt-2">
                                <div v-if="task.requires_document" class="flex flex-col gap-1.5">
                                    <DocumentRow
                                        v-for="document in task.documents ?? []"
                                        :key="document.id"
                                        :document="document"
                                        :replace-url="canManageFeasibility ? `${taskDocumentUrl(entry, task, document)}/replace` : null"
                                        :delete-url="canManageFeasibility ? taskDocumentUrl(entry, task, document) : null"
                                        :can-manage="canManageFeasibility"
                                    />
                                    <p v-if="(task.documents ?? []).length === 0" class="text-xs text-on-surface-variant/50">Attach the JO document to mark this done.</p>
                                    <form v-if="canManageFeasibility" @submit.prevent="submitTaskUpload(entry, task)" class="flex items-center gap-2">
                                        <label class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary border border-primary/30 bg-primary/5 hover:bg-primary/10 rounded-lg px-2.5 py-1 cursor-pointer transition-colors shrink-0">
                                            <span class="material-symbols-outlined text-[13px]">attach_file</span>
                                            Choose file
                                            <input
                                                type="file"
                                                class="hidden"
                                                @change="taskUploadFormFor(task).files = Array.from($event.target.files)"
                                            />
                                        </label>
                                        <span class="text-[11px] text-on-surface-variant truncate flex-1 min-w-0">
                                            {{ taskUploadFormFor(task).files[0]?.name ?? 'No file chosen' }}
                                        </span>
                                        <button
                                            type="submit"
                                            :disabled="!taskUploadFormFor(task).files.length || taskUploadFormFor(task).processing"
                                            class="bg-primary text-white text-[10px] font-semibold px-2.5 py-1 rounded-lg disabled:opacity-40 shrink-0"
                                        >{{ taskUploadFormFor(task).processing ? 'Uploading…' : 'Upload' }}</button>
                                    </form>
                                </div>

                                <div class="flex flex-col gap-1">
                                    <textarea
                                        v-model="taskNotesFormFor(task).notes"
                                        :disabled="!canManageFeasibility"
                                        @blur="saveTaskNotes(entry, task)"
                                        rows="2"
                                        placeholder="Add notes for this task (optional)"
                                        class="w-full text-xs bg-surface-container-lowest border border-outline/30 rounded-lg px-3 py-2 resize-none focus:outline-none focus:ring-1 focus:ring-primary/50 disabled:opacity-60"
                                    />
                                    <span v-if="taskNotesFormFor(task).processing" class="text-[10px] font-semibold text-on-surface-variant">Saving…</span>
                                    <span v-else-if="taskNotesFormFor(task).recentlySuccessful" class="text-[10px] font-semibold text-emerald-700">Saved</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <p v-else class="text-sm text-on-surface-variant/60 text-center py-6">No network feasibility entries yet.</p>
    </div>
</template>
