<script setup>
import { useForm, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import StageFieldInput from '@/Components/Projects/StageFieldInput.vue'
import PlansDrawingReadOnly from '@/Components/Projects/PlansDrawingReadOnly.vue'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'
import { formatDate } from '@/utils/pipeline.js'

const props = defineProps({
    project: Object,
    // This project's own tower's Site Planning assignment (tower-aware, resolved server-side
    // in ProjectController::show) — project.pipeline.site_planning_assignment only ever
    // resolves the untowered default, which is null once a site has declared towers.
    sitePlanningAssignment: { type: Object, default: null },
    stage: Object,
    fieldDefinitions: { type: Array, default: () => [] },
    documentRequirements: { type: Array, default: () => [] },
    requiredDocumentRequirements: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    can: Object,
    readonly: { type: Boolean, default: false },
    networkSiteAssignmentId: { type: [String, Number], default: null },
})

const fields = computed(() => props.fieldDefinitions)

const groupedFields = computed(() => {
    const groups = new Map()

    for (const field of fields.value) {
        if (!groups.has(field.group)) {
            groups.set(field.group, [])
        }
        groups.get(field.group).push(field)
    }

    return Array.from(groups, ([title, groupFields]) => ({ title, fields: groupFields }))
})

function initialValueFor(field) {
    return props.stage.field_values?.[field.key] ?? ''
}

function notesKeyFor(field) {
    return `${field.key}_notes`
}

// Only fields this user may edit go into the form — the server rejects the whole PATCH if any
// submitted key is owned by another department, so carrying read-only fields would 403
// a user's legitimate edits to their own fields.
const submittableFields = computed(() => fields.value.filter(f => f.editable !== false))

const fieldForm = useForm({
    field_values: Object.fromEntries([
        ...submittableFields.value
            .filter(f => !['document_status', 'document_upload', 'isp_task_list', 'computed', 'stage_task_list'].includes(f.type))
            .map(f => [f.key, initialValueFor(f)]),
        ...submittableFields.value
            .filter(f => f.notes)
            .map(f => [notesKeyFor(f), props.stage.field_values?.[notesKeyFor(f)] ?? '']),
    ]),
})

function saveField() {
    fieldForm.patch(`/projects/${props.project.id}/stages/${props.stage.id}`, { preserveScroll: true })
}

// Monitoring's Project Status can be auto-updated server-side by an Infrastructure task status
// change made elsewhere on this page (StageTaskListField) — resync fieldForm's snapshot so the
// dropdown reflects that without requiring a manual edit/reload.
watch(
    () => props.stage.field_values?.project_status,
    (value) => {
        if ('project_status' in fieldForm.field_values) {
            fieldForm.field_values.project_status = value ?? ''
        }
    }
)

// computed fields (e.g. Execution's Duration) are server-derived, and fields owned by another
// department never live in fieldForm — read both straight from the stage prop so they still
// display (read-only) and reflect server-side updates.
function isFormBacked(field) {
    return field.type !== 'computed' && field.editable !== false
}

function valueFor(field) {
    return isFormBacked(field) ? fieldForm.field_values[field.key] : (props.stage.field_values?.[field.key] ?? '')
}

function updateValue(field, value) {
    if (isFormBacked(field)) {
        fieldForm.field_values[field.key] = value
    }
}

function notesValueFor(field) {
    return isFormBacked(field) ? fieldForm.field_values[notesKeyFor(field)] : (props.stage.field_values?.[notesKeyFor(field)] ?? '')
}

function updateNotes(field, value) {
    if (isFormBacked(field)) {
        fieldForm.field_values[notesKeyFor(field)] = value
    }
}

function fieldIsFilled(field) {
    if (field.type === 'document_status') {
        return (props.stage.documents ?? []).some(d => d.category === field.document_category && !!d.pivot?.status)
    }

    if (field.type === 'document_upload') {
        return (props.stage.documents ?? []).some(d => d.category === field.document_category)
    }

    if (field.type === 'isp_task_list') {
        const isps = props.stage.isps ?? []
        return isps.length > 0 && isps.every(isp => (isp.tasks ?? []).every(task => task.status === 'Done'))
    }

    if (field.type === 'computed') {
        return !!(props.stage.field_values?.[field.key])
    }

    if (field.type === 'stage_task_list') {
        const stageTasks = (props.stage.stage_tasks ?? []).filter(task => task.field_key === field.key)
        return stageTasks.length > 0 && stageTasks.every(task => task.status === 'Done')
    }

    return !!fieldForm.field_values[field.key]
}

const missingRequiredFields = computed(() =>
    fields.value.filter(f => !fieldIsFilled(f)).map(f => f.label)
)

// A date field can be configured to require itself on/after another field's date (e.g.
// Mobilization Schedule vs. Kick-off Meeting — mirrors the backend's
// `after_or_equal_field` check in UpdateProjectStageRequest, so "Complete & Advance" is disabled
// before the user even tries to save, not just after a rejected request.
const dateOrderViolations = computed(() =>
    fields.value.filter((f) => {
        if (!f.after_or_equal_field) { return false }

        const value = fieldForm.field_values[f.key]
        const targetValue = fieldForm.field_values[f.after_or_equal_field]

        return !!value && !!targetValue && value < targetValue
    })
)

const inlineDocumentCategories = computed(() =>
    fields.value.filter(f => ['document_status', 'document_upload'].includes(f.type)).map(f => f.document_category)
)

const uploadedCategories = computed(() => (props.stage.documents ?? []).map(d => d.category))
const fallbackDocumentRequirements = computed(() =>
    props.documentRequirements.filter(name => !inlineDocumentCategories.value.includes(name))
)
const missingDocumentRequirements = computed(() =>
    props.requiredDocumentRequirements.filter(name => !uploadedCategories.value.includes(name))
)

function completeStage() {
    router.post(`/projects/${props.project.id}/stages/${props.stage.id}/complete`, {}, { preserveScroll: true })
}

function reopenStage() {
    router.post(`/projects/${props.project.id}/stages/${props.stage.id}/reopen`, {}, { preserveScroll: true })
}

const uploadForm = useForm({ document_category: '', files: [] })

function submitUpload() {
    uploadForm.post(`/projects/${props.project.id}/stages/${props.stage.id}/documents`, {
        preserveScroll: true,
        onSuccess: () => uploadForm.reset(),
    })
}

function documentUrl(document) {
    return `/projects/${props.project.id}/stages/${props.stage.id}/documents/${document.id}`
}

// Collapsed by default — the full read-only Plans/Drawing task list is long and this card is
// just a reference to work already done during Site Survey, not something a PM needs open by
// default on every visit to Planning.
const isPlansDrawingExpanded = ref(false)
</script>

<template>
    <div class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden">
        <div class="px-5 py-3 border-b border-outline/10 flex items-center justify-between">
            <p class="text-sm font-bold text-on-surface">{{ stage.stage }} Details</p>
            <div class="flex items-center gap-2">
                <span v-if="fieldForm.processing" class="text-[10px] font-semibold text-on-surface-variant flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px] animate-spin">progress_activity</span>
                    Saving…
                </span>
                <span v-else-if="fieldForm.recentlySuccessful" class="text-[10px] font-semibold text-emerald-700 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px]">check</span>
                    Saved
                </span>
                <span
                    v-if="stage.stage === 'Monitoring' && stage.monitoring_progress_percent !== null"
                    class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary"
                >{{ stage.monitoring_progress_percent }}% complete</span>
                <span
                    class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                    :class="{
                        'text-on-surface-variant bg-surface-container': stage.status === 'Pending',
                        'text-amber-800 bg-amber-100': stage.status === 'In Progress',
                        'text-emerald-800 bg-emerald-100': stage.status === 'Completed',
                    }"
                >{{ stage.status }}</span>
            </div>
        </div>

        <div class="flex flex-col gap-4 p-5">
            <div
                v-if="stage.stage === 'Planning' && sitePlanningAssignment"
                class="bg-surface-container-low rounded-lg border border-outline/10 p-4 flex flex-col gap-3"
            >
                <button
                    type="button"
                    @click="isPlansDrawingExpanded = !isPlansDrawingExpanded"
                    class="flex items-center justify-between flex-wrap gap-2 text-left"
                >
                    <span class="flex items-center gap-1.5">
                        <span
                            class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform"
                            :class="{ 'rotate-180': isPlansDrawingExpanded }"
                        >expand_more</span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Plans/Drawing</span>
                    </span>
                    <span class="flex items-center gap-2">
                        <span
                            v-if="sitePlanningAssignment.design_plan_approved_at"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700"
                        >
                            <span class="material-symbols-outlined text-[12px]">check_circle</span>
                            Approved
                        </span>
                        <span
                            v-else-if="sitePlanningAssignment.design_plan_completed_at"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700"
                        >
                            <span class="material-symbols-outlined text-[12px]">hourglass_top</span>
                            Pending Infra Manager Approval
                        </span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary">
                            {{ sitePlanningAssignment.plans_drawing_progress_percent ?? 0 }}% done
                        </span>
                    </span>
                </button>

                <template v-if="isPlansDrawingExpanded">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Type</p>
                            <p class="text-sm text-on-surface">{{ sitePlanningAssignment.design_plan_type ?? '—' }}</p>
                        </div>
                    </div>

                    <PlansDrawingReadOnly :tasks="sitePlanningAssignment.site_planning_tasks ?? []" />

                    <p v-if="sitePlanningAssignment.design_plan_approved_at" class="text-xs text-on-surface-variant/60">
                        Approved by {{ sitePlanningAssignment.approved_by?.full_name ?? 'the Infrastructure Manager' }}
                        on {{ formatDate(sitePlanningAssignment.design_plan_approved_at) }}.
                    </p>
                    <p class="text-xs text-on-surface-variant/60">Completed during Site Survey — read-only here.</p>
                </template>
            </div>

            <div v-for="group in groupedFields" :key="group.title" class="bg-surface-container-low rounded-lg border border-outline/10 p-4">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">{{ group.title }}</p>

                <div class="grid grid-cols-2 gap-4">
                    <StageFieldInput
                        v-for="field in group.fields"
                        :key="field.key"
                        :field="field"
                        :model-value="valueFor(field)"
                        @update:model-value="value => updateValue(field, value)"
                        :notes-value="notesValueFor(field)"
                        @update:notes-value="value => updateNotes(field, value)"
                        :hide-label="field.label === group.title"
                        :disabled="readonly || !can?.manageStage?.[stage.stage] || field.editable === false || field.type === 'computed'"
                        :error="fieldForm.errors[`field_values.${field.key}`]"
                        :notes-error="fieldForm.errors[`field_values.${notesKeyFor(field)}`]"
                        :documents="stage.documents ?? []"
                        :isps="stage.isps ?? []"
                        :stage-tasks="stage.stage_tasks ?? []"
                        :users="users"
                        :project-id="project.id"
                        :stage-id="stage.id"
                        :stage-name="stage.stage"
                        :can-manage-network-feasibility="can?.manageNetworkFeasibility"
                        :network-site-assignment-id="networkSiteAssignmentId"
                        @change="saveField"
                    />
                </div>
            </div>
        </div>

        <div v-if="fallbackDocumentRequirements.length" class="px-5 py-4 border-t border-outline/10">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-2">Documents</p>

            <div class="flex flex-wrap gap-1.5 mb-3">
                <span
                    v-for="name in fallbackDocumentRequirements"
                    :key="name"
                    class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                    :class="uploadedCategories.includes(name)
                        ? 'bg-emerald-100 text-emerald-800'
                        : requiredDocumentRequirements.includes(name)
                            ? 'bg-amber-100 text-amber-800'
                            : 'bg-surface-container text-on-surface-variant'"
                >{{ name }} — {{ uploadedCategories.includes(name) ? 'Uploaded' : (requiredDocumentRequirements.includes(name) ? 'Missing' : 'Optional') }}</span>
            </div>

            <div v-if="(stage.documents ?? []).filter(d => !inlineDocumentCategories.includes(d.category)).length" class="flex flex-col divide-y divide-outline/10 mb-3">
                <div v-for="document in stage.documents.filter(d => !inlineDocumentCategories.includes(d.category))" :key="document.id" class="py-2">
                    <DocumentRow
                        :document="document"
                        :replace-url="!readonly && can?.manageStage?.[stage.stage] ? `${documentUrl(document)}/replace` : null"
                        :delete-url="!readonly && can?.manageStage?.[stage.stage] ? documentUrl(document) : null"
                        :can-manage="!readonly && !!can?.manageStage?.[stage.stage]"
                    >
                        <template #trailing>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant">{{ document.category }}</span>
                        </template>
                    </DocumentRow>
                </div>
            </div>

            <form
                v-if="!readonly && can?.manageStage?.[stage.stage]"
                @submit.prevent="submitUpload"
                class="flex flex-col sm:flex-row gap-2"
            >
                <select v-model="uploadForm.document_category" class="text-sm bg-surface-container-lowest border border-outline/40 rounded-lg px-3 py-1.5">
                    <option value="">— Category —</option>
                    <option v-for="name in fallbackDocumentRequirements" :key="name" :value="name">{{ name }}</option>
                </select>
                <input
                    type="file"
                    multiple
                    @change="uploadForm.files = Array.from($event.target.files)"
                    class="text-sm flex-1"
                />
                <button
                    type="submit"
                    :disabled="uploadForm.processing"
                    class="bg-primary text-white text-xs font-semibold px-3 py-1.5 rounded-lg disabled:opacity-60"
                >Upload</button>
            </form>
            <p v-if="uploadForm.errors.document_category" class="text-xs text-error mt-1">{{ uploadForm.errors.document_category }}</p>
            <p v-if="uploadForm.errors['files.0']" class="text-xs text-error mt-1">{{ uploadForm.errors['files.0'] }}</p>
        </div>

        <div v-if="!readonly && can?.completeStage?.[stage.stage] && stage.status !== 'Completed'" class="px-5 py-4 border-t border-outline/10">
            <p v-if="missingRequiredFields.length" class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2 mb-3">
                Complete all fields before advancing this stage.
            </p>
            <p v-else-if="missingDocumentRequirements.length" class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2 mb-3">
                Upload all required documents before advancing this stage.
            </p>
            <p v-else-if="dateOrderViolations.length" class="text-xs text-error bg-error/5 rounded-lg px-3 py-2 mb-3">
                {{ dateOrderViolations[0].label }} must be on or after {{ fields.find(f => f.key === dateOrderViolations[0].after_or_equal_field)?.label }}.
            </p>
            <button
                @click="completeStage"
                :disabled="missingRequiredFields.length > 0 || missingDocumentRequirements.length > 0 || dateOrderViolations.length > 0"
                class="w-full bg-primary text-white text-sm font-semibold py-2.5 rounded-lg hover:brightness-110 disabled:opacity-60 transition"
            >
                Complete {{ stage.stage }} &amp; Advance
            </button>
        </div>

        <div v-else-if="readonly && stage.status === 'Completed' && can?.reopenStage?.[stage.stage]" class="px-5 py-4 border-t border-outline/10">
            <button
                @click="reopenStage"
                class="w-full bg-surface-container-high text-on-surface text-sm font-semibold py-2.5 rounded-lg hover:bg-surface-container transition"
            >
                Reopen {{ stage.stage }}
            </button>
        </div>
    </div>
</template>
