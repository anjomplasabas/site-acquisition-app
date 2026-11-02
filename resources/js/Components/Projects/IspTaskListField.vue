<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

const page = usePage()
const canViewNetworkModule = computed(() => (page.props.auth?.modules ?? []).some(module => module.value === 'network'))

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

const TASK_STATUSES = ['Pending', 'In-Progress', 'Done']

const props = defineProps({
    isps: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    projectId: { type: [String, Number], required: true },
    stageId: { type: [String, Number], required: true },
    stageName: { type: String, default: null },
    networkSiteAssignmentId: { type: [String, Number], default: null },
    canManageNetworkFeasibility: { type: Boolean, default: false },
})

// Only Monitoring's Network Deployment Status checklist can be actioned directly from Project
// Management (in addition to the Network module) — Planning/Execution stay read-only, matching
// how the JO checklist has always worked everywhere else.
const canActionChecklist = props.stageName === 'Monitoring' && props.canManageNetworkFeasibility

// Planning, Execution, and Monitoring all show the ISP's full 9-task JO checklist, matching the
// Network module's own view exactly — only Monitoring can action it directly here
// (canActionChecklist above); Planning/Execution stay read-only.
function displayedTasks(isp) {
    return isp.network_feasibility?.tasks ?? []
}

function progressFor(isp) {
    const tasks = displayedTasks(isp)

    return { done: tasks.filter(task => task.status === 'Done').length, total: tasks.length }
}

function baseUrl(isp) {
    return `/projects/${props.projectId}/stages/${props.stageId}/isps/${isp.id}`
}

// ── JO checklist task actions (Monitoring only, see canActionChecklist) ────────
// These post to the Network module's own routes — the same ones NetworkFeasibilityCard.vue uses
// — since a NetworkFeasibilityTask is a Network module resource, not a Project Management one.
function taskUrl(isp, task) {
    return `/network/${props.networkSiteAssignmentId}/feasibilities/${isp.network_feasibility_id}/tasks/${task.id}`
}

const taskForms = reactive({})

function taskFormFor(task) {
    if (!taskForms[task.id]) {
        taskForms[task.id] = useForm({ status: task.status })
    }

    return taskForms[task.id]
}

function saveTaskStatus(isp, task) {
    taskFormFor(task).patch(taskUrl(isp, task), { preserveScroll: true })
}

const taskNotesForms = reactive({})

function taskNotesFormFor(task) {
    if (!taskNotesForms[task.id]) {
        taskNotesForms[task.id] = useForm({ notes: task.notes ?? '' })
    }

    return taskNotesForms[task.id]
}

function saveTaskNotes(isp, task) {
    taskNotesFormFor(task).patch(taskUrl(isp, task), { preserveScroll: true })
}

const taskUploadForms = reactive({})

function taskUploadFormFor(task) {
    if (!taskUploadForms[task.id]) {
        taskUploadForms[task.id] = useForm({ files: [] })
    }

    return taskUploadForms[task.id]
}

function submitTaskUpload(isp, task) {
    taskUploadFormFor(task).post(`${taskUrl(isp, task)}/documents`, {
        preserveScroll: true,
        onSuccess: () => {
            taskUploadFormFor(task).reset('files')
            if (task.requires_document) {
                taskFormFor(task).status = 'Done'
            }
        },
    })
}

function taskDocumentUrl(isp, task, document) {
    return `${taskUrl(isp, task)}/documents/${document.id}`
}

function progressPct(isp) {
    const total = progressFor(isp).total
    return total ? Math.round((progressFor(isp).done / total) * 100) : 0
}

// ── Collapse (per-ISP, whole checklist toggles together) ────────────────────
// Collapsed by default so a stage with several ISPs doesn't turn into a very long page — same
// expand_more/rotate-180 chevron convention as StageTaskListField.vue/NetworkFeasibilityCard.vue.
const expandedIspIds = reactive(new Set())

function isIspExpanded(isp) {
    return expandedIspIds.has(isp.id)
}

function toggleIsp(isp) {
    if (expandedIspIds.has(isp.id)) {
        expandedIspIds.delete(isp.id)
    } else {
        expandedIspIds.add(isp.id)
    }
}

// ── Add ISP ─────────────────────────────────────────────────────────────────
const addForm = useForm({ isp_name: '', notes: '' })
const showAddNotes = ref(false)

function addIsp() {
    addForm.post(`/projects/${props.projectId}/stages/${props.stageId}/isps`, {
        preserveScroll: true,
        onSuccess: () => { addForm.reset(); showAddNotes.value = false },
    })
}

// ── Rename/notes ISP ──────────────────────────────────────────────────────────
const ispForms = reactive({})

function ispFormFor(isp) {
    if (!ispForms[isp.id]) {
        ispForms[isp.id] = useForm({ isp_name: isp.isp_name, notes: isp.notes ?? '' })
    }

    return ispForms[isp.id]
}

function saveIsp(isp) {
    ispFormFor(isp).patch(baseUrl(isp), { preserveScroll: true })
}

function removeIsp(isp) {
    if (!confirm(`Remove ISP "${isp.isp_name}"?`)) { return }
    router.delete(baseUrl(isp), { preserveScroll: true })
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div v-for="isp in isps" :key="isp.id" class="bg-surface-container-lowest rounded-xl border border-outline/15 overflow-hidden">
            <!-- ISP header -->
            <div class="flex items-center gap-3 px-4 py-3 border-b border-outline/10">
                <div class="flex-1 min-w-0">
                    <input
                        v-model="ispFormFor(isp).isp_name"
                        type="text"
                        placeholder="ISP name"
                        :disabled="disabled"
                        @change="saveIsp(isp)"
                        class="w-full text-sm font-bold text-on-surface bg-transparent border-0 focus:outline-none px-0 py-0.5 disabled:opacity-60 truncate"
                    />
                    <input
                        v-model="ispFormFor(isp).notes"
                        type="text"
                        placeholder="Add a note…"
                        :disabled="disabled"
                        @change="saveIsp(isp)"
                        class="w-full text-xs text-on-surface-variant bg-transparent border-0 focus:outline-none px-0 py-0.5 placeholder:text-on-surface-variant/50 disabled:opacity-60"
                    />
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <div v-if="progressFor(isp).total" class="flex flex-col items-end gap-1 w-24">
                        <span class="text-[10px] font-bold text-on-surface-variant whitespace-nowrap">{{ progressFor(isp).done }}/{{ progressFor(isp).total }} done</span>
                        <div class="w-full h-1.5 bg-surface-container rounded-full overflow-hidden">
                            <div class="h-full bg-primary rounded-full transition-all" :style="{ width: `${progressPct(isp)}%` }" />
                        </div>
                    </div>

                    <button
                        v-if="progressFor(isp).total > 0"
                        type="button"
                        @click="toggleIsp(isp)"
                        class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container/60 rounded-lg transition-colors shrink-0"
                        title="Toggle checklist"
                    >
                        <span
                            class="material-symbols-outlined text-[18px] transition-transform"
                            :class="{ 'rotate-180': isIspExpanded(isp) }"
                        >expand_more</span>
                    </button>

                    <button
                        v-if="!disabled"
                        type="button"
                        @click="removeIsp(isp)"
                        title="Remove ISP"
                        class="p-1.5 text-on-surface-variant hover:text-error hover:bg-error/5 rounded-lg transition-colors shrink-0"
                    >
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                    </button>
                </div>
            </div>

            <!-- JO checklist — read-only, except Monitoring's when canActionChecklist -->
            <div v-if="progressFor(isp).total > 0 && isIspExpanded(isp)" class="divide-y divide-outline/10">
                <div v-for="task in displayedTasks(isp)" :key="task.id" class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <span
                            class="material-symbols-outlined text-[18px] shrink-0"
                            :class="STATUS_ICON_CLASS[taskFormFor(task).status]"
                        >{{ STATUS_ICON[taskFormFor(task).status] }}</span>
                        <span class="text-sm text-on-surface flex-1 min-w-0 truncate">{{ task.name }}</span>

                        <select
                            v-if="canActionChecklist"
                            v-model="taskFormFor(task).status"
                            @change="saveTaskStatus(isp, task)"
                            class="text-[11px] font-bold rounded-full pl-2.5 pr-6 py-1 border-0 shrink-0 appearance-none bg-[right_0.4rem_center] bg-no-repeat focus:outline-none focus:ring-1 focus:ring-primary/50"
                            :class="STATUS_PILL_CLASS[taskFormFor(task).status]"
                            style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22currentColor%22 stroke-width=%223%22><polyline points=%226 9 12 15 18 9%22/></svg>')"
                        >
                            <option v-for="option in TASK_STATUSES" :key="option" :value="option">{{ option }}</option>
                        </select>
                        <span v-else class="text-[10px] font-bold text-on-surface-variant shrink-0">{{ task.status }}</span>
                    </div>

                    <div v-if="canActionChecklist && task.requires_document" class="mt-2 pl-7 flex flex-col gap-1.5">
                        <DocumentRow
                            v-for="document in task.documents ?? []"
                            :key="document.id"
                            :document="document"
                            :replace-url="`${taskDocumentUrl(isp, task, document)}/replace`"
                            :delete-url="taskDocumentUrl(isp, task, document)"
                            :can-manage="true"
                        />
                        <p v-if="(task.documents ?? []).length === 0" class="text-xs text-on-surface-variant/50">Attach a document before marking this done.</p>
                        <form @submit.prevent="submitTaskUpload(isp, task)" class="flex items-center gap-2">
                            <label class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary border border-primary/30 bg-primary/5 hover:bg-primary/10 rounded-lg px-2.5 py-1 cursor-pointer transition-colors shrink-0">
                                <span class="material-symbols-outlined text-[13px]">attach_file</span>
                                Choose file
                                <input type="file" class="hidden" @change="taskUploadFormFor(task).files = Array.from($event.target.files)" />
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

                    <div v-if="canActionChecklist" class="mt-2 pl-7 flex flex-col gap-1">
                        <textarea
                            v-model="taskNotesFormFor(task).notes"
                            @blur="saveTaskNotes(isp, task)"
                            rows="2"
                            placeholder="Add notes for this task (optional)"
                            class="w-full text-xs bg-surface-container-lowest border border-outline/30 rounded-lg px-3 py-2 resize-none focus:outline-none focus:ring-1 focus:ring-primary/50"
                        />
                        <span v-if="taskNotesFormFor(task).processing" class="text-[10px] font-semibold text-on-surface-variant">Saving…</span>
                        <span v-else-if="taskNotesFormFor(task).recentlySuccessful" class="text-[10px] font-semibold text-emerald-700">Saved</span>
                    </div>
                    <p v-else-if="task.notes" class="mt-2 pl-7 text-xs text-on-surface-variant/70 italic">{{ task.notes }}</p>
                </div>
            </div>

            <a
                v-if="networkSiteAssignmentId && canViewNetworkModule"
                :href="`/network/${networkSiteAssignmentId}`"
                class="flex items-center justify-center gap-1.5 px-4 py-2 text-[11px] font-bold text-primary hover:underline border-t border-outline/10"
            >
                <span class="material-symbols-outlined text-[14px]">open_in_new</span>
                Manage action items in Network module
            </a>
        </div>

        <p v-if="isps.length === 0" class="text-xs text-on-surface-variant/60 text-center py-3">No ISPs added yet.</p>

        <form
            v-if="!disabled"
            @submit.prevent="addIsp"
            class="flex items-center gap-2 rounded-xl border border-dashed border-outline/30 hover:border-primary/40 transition-colors px-3 py-2"
        >
            <span class="material-symbols-outlined text-[18px] text-on-surface-variant shrink-0">add_circle</span>
            <input
                v-model="addForm.isp_name"
                type="text"
                placeholder="ISP name"
                @focus="showAddNotes = true"
                class="flex-1 text-sm bg-transparent border-0 focus:outline-none min-w-0"
            />
            <input
                v-if="showAddNotes || addForm.notes"
                v-model="addForm.notes"
                type="text"
                placeholder="Notes (optional)"
                class="flex-1 text-sm bg-transparent border-0 focus:outline-none min-w-0 hidden sm:block"
            />
            <button
                type="submit"
                :disabled="addForm.processing || !addForm.isp_name.trim()"
                class="bg-primary text-white text-xs font-semibold px-3 py-1.5 rounded-lg disabled:opacity-40 shrink-0"
            >Add ISP</button>
        </form>
    </div>
</template>
