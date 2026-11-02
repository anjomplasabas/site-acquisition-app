<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { formatDate, formatDateTime, statusStyle, getInitials } from '@/utils/pipeline.js'
import ScheduleForm from '@/Components/SitePlanning/ScheduleForm.vue'
import InspectionForm from '@/Components/SitePlanning/InspectionForm.vue'
import PlansDrawingChecklist from '@/Components/SitePlanning/PlansDrawingChecklist.vue'
import AssignDesignerForm from '@/Components/SitePlanning/AssignDesignerForm.vue'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    assignment: Object,
    surveyors: Array,
    designers: Array,
    designPlanTypes: Array,
    siteProfileTypes: Array,
    activityLogs: Array,
    can: Object,
    documentTypes: Array,
    missingDocumentRequirements: Array,
    matchingProject: Object,
    siblingTowerAssignments: Array,
})

const pipeline = computed(() => props.assignment.pipeline)

const declareTowersForm = useForm({
    towers: [{ name: '', total_units: '', total_floors: '' }],
})

function addTowerRow() {
    declareTowersForm.towers.push({ name: '', total_units: '', total_floors: '' })
}

function removeTowerRow(index) {
    declareTowersForm.towers.splice(index, 1)
}

function submitDeclareTowers() {
    declareTowersForm.post(`/site-planning/${props.assignment.id}/towers`, {
        preserveScroll: true,
    })
}

// Signal for "which phase is this assignment in" — Type is already chosen at scheduling time
// (ScheduleForm), so submitting the inspection form is what seeds the checklist immediately.
const hasStartedPlansDrawing = computed(() => (props.assignment.site_planning_tasks ?? []).length > 0)

// ── Change Type — Infra Manager-gated reclassification ────────────────
const isChangingType = ref(false)
const changeTypeForm = useForm({ design_plan_type: props.assignment.design_plan_type ?? '' })

function submitTypeChange() {
    changeTypeForm.post(`/site-planning/${props.assignment.id}/design-plan-type`, {
        preserveScroll: true,
        onSuccess: () => { isChangingType.value = false },
    })
}

const canCompleteDesignPlan = computed(() =>
    props.assignment.plans_drawing_progress_percent === 100
    && !!props.assignment.design_plan_type
    && (props.assignment.design_plan_documents ?? []).length > 0
)

// Three states once a checklist exists: not yet submitted → editable + Complete button;
// submitted, awaiting the Infrastructure Manager's sign-off → read-only + Approve button;
// approved → read-only, done.
const isPendingApproval = computed(() => props.assignment.design_plan_completed_at !== null && props.assignment.design_plan_approved_at === null)
const isApproved = computed(() => props.assignment.design_plan_approved_at !== null)

const projectTypeForm = useForm({
    type: props.assignment.pipeline?.site_profile?.type ?? '',
    type_other: props.assignment.pipeline?.site_profile?.type_other ?? '',
})
const isEditingProjectType = ref(false)

function saveProjectType() {
    projectTypeForm.patch(`/site-planning/${props.assignment.id}/project-type`, {
        preserveScroll: true,
        onSuccess: () => { isEditingProjectType.value = false },
    })
}

const completeDesignPlanForm = useForm({})

function completeDesignPlan() {
    if (!confirm('Submit the design plan for approval? You will not be able to make further changes.')) { return }

    completeDesignPlanForm.patch(`/site-planning/${props.assignment.id}/design-plan/complete`, { preserveScroll: true })
}

const approveDesignPlanForm = useForm({})

function approveDesignPlan() {
    if (!confirm('Approve this design plan? This finalizes the survey for this site.')) { return }

    approveDesignPlanForm.patch(`/site-planning/${props.assignment.id}/design-plan/approve`, { preserveScroll: true })
}

const requestRevisionForm = useForm({ remarks: '' })
const isRevisionFormOpen = ref(false)

function requestRevision() {
    requestRevisionForm.patch(`/site-planning/${props.assignment.id}/design-plan/request-revision`, {
        preserveScroll: true,
        onSuccess: () => {
            requestRevisionForm.reset()
            isRevisionFormOpen.value = false
        },
    })
}

// ─── Design Plan Photos & Documents — separate from per-task attachments ───
const designPlanRemarksForm = useForm({ design_plan_remarks: props.assignment.design_plan_remarks ?? '' })

function saveDesignPlanRemarks() {
    designPlanRemarksForm.patch(`/site-planning/${props.assignment.id}/design-plan/remarks`, { preserveScroll: true })
}

const designPlanUploadForm = useForm({ files: [] })

function submitDesignPlanUpload(event) {
    const files = Array.from(event.target.files ?? [])
    if (!files.length) { return }

    designPlanUploadForm.transform(() => ({ files })).post(`/site-planning/${props.assignment.id}/design-plan/documents`, {
        preserveScroll: true,
        onFinish: () => { event.target.value = '' },
    })
}

function designPlanDocumentUrl(document, action) {
    return `/site-planning/${props.assignment.id}/design-plan/documents/${document.id}${action ? `/${action}` : ''}`
}
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <a
                href="/site-planning"
                class="w-10 h-10 rounded-xl flex items-center justify-center text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors shrink-0"
            >
                <span class="material-symbols-outlined text-[22px]">arrow_back</span>
            </a>
            <div class="w-12 h-12 rounded-2xl bg-orange-50 flex items-center justify-center shrink-0 text-sm font-bold text-orange-700">
                {{ getInitials(pipeline.name) }}
            </div>
            <div>
                <h1 class="text-xl font-bold text-on-surface">{{ pipeline.name }}</h1>
                <p class="text-sm text-on-surface-variant mt-0.5 flex items-center gap-2">
                    {{ pipeline.pipeline_id }}
                    <span v-if="assignment.tower" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-50 text-orange-700">
                        <span class="material-symbols-outlined text-[12px]">domain</span>
                        {{ assignment.tower.name }}
                    </span>
                </p>
            </div>
        </div>
        <span
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-xs font-bold shrink-0"
            :class="statusStyle(pipeline.status).badge"
        >
            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(pipeline.status).dot"></span>
            {{ pipeline.status }}
        </span>
    </div>

    <!-- Towers — sibling tower links on a multi-tower site, plus a link to this tower's own
         Project once one exists. -->
    <div v-if="siblingTowerAssignments?.length || matchingProject" class="bg-surface-bright rounded-2xl border border-outline/10 p-5 flex flex-wrap items-center gap-3">
        <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Towers on this site</span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-primary/10 text-primary">
            {{ assignment.tower?.name ?? 'Main' }} (this page)
        </span>
        <a
            v-for="sibling in siblingTowerAssignments"
            :key="sibling.id"
            :href="`/site-planning/${sibling.id}`"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-surface-container text-on-surface-variant hover:bg-surface-container-low transition-colors"
        >
            {{ sibling.tower?.name ?? 'Main' }}
        </a>
        <a
            v-if="matchingProject"
            :href="`/projects/${matchingProject.id}`"
            class="ml-auto inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
        >
            View Project
            <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
        </a>
    </div>

    <!-- Declare Towers — one-time action letting the Surveyor split this site into its actual
         physical towers once confirmed on-site, per Tina's request (2B). -->
    <div v-if="can?.declareTowers" class="bg-surface-bright rounded-2xl border border-outline/10 p-7 flex flex-col gap-5">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">domain</span>
            <div>
                <p class="text-sm font-bold text-on-surface">Does this site have multiple towers/buildings?</p>
                <p class="text-xs text-on-surface-variant mt-0.5">Declare each tower once confirmed on-site — Survey and Project progress will then be tracked per tower.</p>
            </div>
        </div>

        <form @submit.prevent="submitDeclareTowers" class="flex flex-col gap-3">
            <div v-for="(tower, index) in declareTowersForm.towers" :key="index" class="grid grid-cols-1 sm:grid-cols-[2fr_1fr_1fr_auto] gap-2 items-start">
                <div class="flex flex-col gap-1">
                    <input
                        v-model="tower.name"
                        type="text"
                        :placeholder="`Tower ${index + 1} name`"
                        class="w-full bg-surface-container-low border rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                        :class="declareTowersForm.errors[`towers.${index}.name`] ? 'border-error' : 'border-outline/40'"
                    />
                    <p v-if="declareTowersForm.errors[`towers.${index}.name`]" class="text-xs text-error">{{ declareTowersForm.errors[`towers.${index}.name`] }}</p>
                </div>
                <input
                    v-model="tower.total_units"
                    type="number" min="0" placeholder="Total units per tower"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                />
                <input
                    v-model="tower.total_floors"
                    type="number" min="0" placeholder="Total floors per tower"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                />
                <button
                    type="button"
                    :disabled="declareTowersForm.towers.length <= 1"
                    @click="removeTowerRow(index)"
                    class="p-2 text-on-surface-variant hover:text-error transition-colors rounded disabled:opacity-30 disabled:cursor-not-allowed"
                >
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                </button>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" @click="addTowerRow" class="flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                    <span class="material-symbols-outlined text-[14px]">add_circle</span>
                    Add another tower
                </button>
                <button
                    type="submit"
                    :disabled="declareTowersForm.processing"
                    class="ml-auto bg-primary text-white font-semibold text-sm px-5 py-2 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                >
                    {{ declareTowersForm.processing ? 'Saving…' : 'Declare Towers' }}
                </button>
            </div>
        </form>
    </div>

    <!-- Site Profile (read-only) -->
    <div class="bg-surface-bright rounded-2xl border border-outline/10 p-7">
        <div class="flex items-center gap-2.5 mb-6">
            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">apartment</span>
            <p class="text-sm font-bold text-on-surface">Site Profile</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-10 gap-y-5">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Site Name</p>
                <p class="text-sm font-semibold text-on-surface">{{ pipeline.site_profile?.name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Type</p>
                <div v-if="isEditingProjectType" class="flex flex-col gap-1.5">
                    <select
                        v-model="projectTypeForm.type"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-1.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none"
                    >
                        <option v-if="projectTypeForm.type && !siteProfileTypes.includes(projectTypeForm.type)" :value="projectTypeForm.type">{{ projectTypeForm.type }}</option>
                        <option v-for="type in siteProfileTypes" :key="type" :value="type">{{ type }}</option>
                    </select>
                    <input
                        v-if="projectTypeForm.type === 'Others'"
                        v-model="projectTypeForm.type_other"
                        type="text"
                        maxlength="255"
                        placeholder="Specify the type"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-1.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none"
                    />
                    <p v-if="projectTypeForm.errors.type || projectTypeForm.errors.type_other" class="text-xs text-error">{{ projectTypeForm.errors.type ?? projectTypeForm.errors.type_other }}</p>
                    <div class="flex gap-2">
                        <button type="button" :disabled="projectTypeForm.processing" @click="saveProjectType" class="text-xs font-bold text-primary hover:underline disabled:opacity-60">Save</button>
                        <button type="button" @click="isEditingProjectType = false" class="text-xs font-semibold text-on-surface-variant hover:underline">Cancel</button>
                    </div>
                </div>
                <div v-else class="flex items-center gap-2">
                    <p class="text-sm font-semibold text-on-surface">{{ pipeline.site_profile?.type === 'Others' && pipeline.site_profile?.type_other ? `Others (${pipeline.site_profile.type_other})` : (pipeline.site_profile?.type ?? '—') }}</p>
                    <button v-if="can?.updateProjectType" type="button" @click="isEditingProjectType = true" class="text-on-surface-variant hover:text-primary" title="Change type">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                    </button>
                </div>
            </div>
            <div class="sm:col-span-2" v-if="pipeline.site_profile?.address">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Address</p>
                <p class="text-sm text-on-surface">
                    {{ [pipeline.site_profile.address.street, pipeline.site_profile.address.barangay, pipeline.site_profile.address.city, pipeline.site_profile.address.province].filter(Boolean).join(', ') || '—' }}
                </p>
            </div>
        </div>

        <div v-if="pipeline.site_profile?.contact" class="mt-6 pt-6 border-t border-outline/10">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Contact Person</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-10 gap-y-4">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Name</p>
                    <p class="text-sm font-semibold text-on-surface">{{ pipeline.site_profile.contact.name }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Contact</p>
                    <p v-if="pipeline.site_profile.contact.mobile_number" class="text-sm text-on-surface">{{ pipeline.site_profile.contact.mobile_number }}</p>
                    <p v-if="pipeline.site_profile.contact.email" class="text-xs text-on-surface-variant mt-0.5">{{ pipeline.site_profile.contact.email }}</p>
                </div>
            </div>
        </div>
    </div>

    <ScheduleForm
        v-if="['Site Survey Pending', 'Site Survey'].includes(pipeline.status) && can?.assignSiteSurveyor"
        :assignment="assignment"
        :surveyors="surveyors"
        :design-plan-types="designPlanTypes"
    />

    <InspectionForm
        v-if="pipeline.status === 'Site Survey' && !hasStartedPlansDrawing"
        :assignment="assignment"
        :can-fill-survey="can?.fillSurvey"
        :can-submit-inspection="can?.submitInspection"
        :document-types="documentTypes"
        :missing-document-requirements="missingDocumentRequirements"
    />

    <!-- Plans/Drawing (Infra Designer's checklist) — active while the pipeline is still in Site
         Survey, shown read-only once it has moved on (Commercials/Approval/Project). -->
    <div v-if="hasStartedPlansDrawing" class="bg-surface-bright rounded-2xl border border-outline/10 p-7 flex flex-col gap-6">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">design_services</span>
                <p class="text-sm font-bold text-on-surface">Plans/Drawing</p>
            </div>
            <span
                v-if="isApproved"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700"
            >
                <span class="material-symbols-outlined text-[13px]">check_circle</span>
                Approved
            </span>
            <span
                v-else-if="isPendingApproval"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700"
            >
                <span class="material-symbols-outlined text-[13px]">hourglass_top</span>
                Pending Infrastructure Manager Approval
            </span>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-5 sm:gap-8 p-4 rounded-xl bg-surface-container-low">
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-2">Design Plan Assignee</p>
                <AssignDesignerForm
                    v-if="can?.assignDesignPlan && !isPendingApproval && !isApproved"
                    :assignment="assignment"
                    :designers="designers"
                />
                <div v-else-if="assignment.design_plan_assignee" class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                        <span class="text-[10px] font-bold text-primary">{{ getInitials(assignment.design_plan_assignee.full_name) }}</span>
                    </div>
                    <span class="text-sm font-semibold text-on-surface truncate">{{ assignment.design_plan_assignee.full_name }}</span>
                </div>
                <p v-else class="text-sm text-on-surface-variant/60 italic">Unassigned</p>
            </div>

            <div class="hidden sm:block w-px self-stretch bg-outline/10"></div>

            <div class="shrink-0">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-2">Type</p>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-surface-container text-on-surface-variant">
                    {{ assignment.design_plan_type ?? '—' }}
                    <button
                        v-if="can?.approveDesignPlan && hasStartedPlansDrawing"
                        type="button"
                        @click="isChangingType = !isChangingType"
                        class="text-primary hover:underline font-semibold normal-case"
                    >Change</button>
                </span>

                <!-- Reclassification is deliberate: confirm with an explicit warning about what the rebuild keeps and resets. -->
                <div v-if="isChangingType" class="mt-2 w-64 rounded-lg border border-amber-200 bg-amber-50 p-3 flex flex-col gap-2">
                    <p class="text-xs text-amber-800">
                        Changing the type rebuilds the Plans/Drawing checklist and, if this site's project has reached
                        Monitoring, its Infrastructure matrix. Progress on tasks that exist in both lists is kept —
                        other tasks reset, so completion % may drop.
                    </p>
                    <select
                        v-model="changeTypeForm.design_plan_type"
                        class="w-full text-sm bg-surface-container-lowest border border-outline/40 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-primary"
                    >
                        <option v-for="type in designPlanTypes" :key="type" :value="type">{{ type }}</option>
                    </select>
                    <p v-if="changeTypeForm.errors.design_plan_type" class="text-xs text-error">{{ changeTypeForm.errors.design_plan_type }}</p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="submitTypeChange"
                            :disabled="changeTypeForm.processing || !changeTypeForm.design_plan_type || changeTypeForm.design_plan_type === assignment.design_plan_type"
                            class="bg-primary text-white text-xs font-semibold px-3 py-1.5 rounded-lg disabled:opacity-40"
                        >{{ changeTypeForm.processing ? 'Rebuilding…' : 'Confirm change' }}</button>
                        <button type="button" @click="isChangingType = false" class="text-xs font-semibold text-on-surface-variant px-2 py-1.5">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <PlansDrawingChecklist
            :tasks="assignment.site_planning_tasks"
            :assignment-id="assignment.id"
            :disabled="!can?.manageDesignPlan || isPendingApproval || isApproved"
        />

        <div class="border-t border-outline/10 pt-4 flex flex-col gap-3">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                Design Plan — Photos & Documents <span class="text-error normal-case font-normal tracking-normal">(at least one file required)</span>
            </p>

            <div v-if="assignment.design_plan_documents?.length" class="flex flex-col gap-1.5">
                <DocumentRow
                    v-for="document in assignment.design_plan_documents"
                    :key="document.id"
                    :document="document"
                    :replace-url="designPlanDocumentUrl(document, 'replace')"
                    :delete-url="designPlanDocumentUrl(document)"
                    :can-manage="!!can?.manageDesignPlan && !isPendingApproval && !isApproved"
                    :refresh-options="{ only: ['assignment', 'flash'] }"
                />
            </div>
            <p v-else class="text-xs text-on-surface-variant/60 italic">No files attached yet.</p>

            <label
                v-if="can?.manageDesignPlan && !isPendingApproval && !isApproved"
                class="inline-flex items-center gap-1.5 text-xs font-bold text-primary cursor-pointer w-fit"
            >
                <span class="material-symbols-outlined text-[16px]">attach_file</span>
                Attach files/photos
                <input type="file" multiple class="sr-only" @change="submitDesignPlanUpload" />
            </label>
            <p v-if="designPlanUploadForm.errors['files.0']" class="text-xs text-error">{{ designPlanUploadForm.errors['files.0'] }}</p>

            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Remarks/Notes <span class="text-on-surface-variant/50 normal-case font-normal tracking-normal">(optional)</span></label>
                <textarea
                    v-model="designPlanRemarksForm.design_plan_remarks"
                    :disabled="!can?.manageDesignPlan || isPendingApproval || isApproved"
                    rows="2"
                    class="w-full bg-surface-container-low border rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none disabled:opacity-60"
                    :class="designPlanRemarksForm.errors.design_plan_remarks ? 'border-error' : 'border-outline/40'"
                    @blur="can?.manageDesignPlan && !isPendingApproval && !isApproved && saveDesignPlanRemarks()"
                />
                <p v-if="designPlanRemarksForm.errors.design_plan_remarks" class="text-xs text-error">{{ designPlanRemarksForm.errors.design_plan_remarks }}</p>
            </div>
        </div>

        <div v-if="assignment.design_plan_revision_remarks && !isPendingApproval && !isApproved" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-widest text-amber-800 mb-1">Revision requested by the Infrastructure Manager</p>
            <p class="text-sm text-amber-900 whitespace-pre-line">{{ assignment.design_plan_revision_remarks }}</p>
        </div>

        <div v-if="!isPendingApproval && !isApproved && can?.completeDesignPlan" class="flex flex-col items-end gap-1.5 pt-2 border-t border-outline/10">
            <button
                type="button"
                :disabled="!canCompleteDesignPlan || completeDesignPlanForm.processing"
                @click="completeDesignPlan"
                class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-sm"
            >
                <span v-if="completeDesignPlanForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                <span v-else class="material-symbols-outlined text-[16px]">task_alt</span>
                Complete Design Plan
            </button>
            <p v-if="!canCompleteDesignPlan" class="text-xs text-on-surface-variant/70">
                Finish every task (100% weighted) and attach at least one Photos & Documents file before completing.
            </p>
        </div>

        <div v-else-if="isPendingApproval" class="flex flex-col items-end gap-1.5 pt-2 border-t border-outline/10">
            <p class="text-xs text-on-surface-variant/70">
                Submitted by {{ assignment.completed_by?.full_name ?? 'the Infra Designer' }}
                <template v-if="assignment.design_plan_completed_at">on {{ formatDate(assignment.design_plan_completed_at) }}</template>.
            </p>
            <button
                v-if="can?.approveDesignPlan"
                type="button"
                :disabled="approveDesignPlanForm.processing"
                @click="approveDesignPlan"
                class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-sm"
            >
                <span v-if="approveDesignPlanForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                <span v-else class="material-symbols-outlined text-[16px]">verified</span>
                Approve Design Plan
            </button>
            <button
                v-if="can?.approveDesignPlan && !isRevisionFormOpen"
                type="button"
                @click="isRevisionFormOpen = true"
                class="flex items-center gap-2 border border-outline/40 text-on-surface-variant font-semibold text-sm px-5 py-2.5 rounded-lg hover:bg-surface-container transition-colors"
            >
                <span class="material-symbols-outlined text-[16px]">edit_note</span>
                Request Revision
            </button>
            <form v-if="can?.approveDesignPlan && isRevisionFormOpen" @submit.prevent="requestRevision" class="w-full flex flex-col gap-2">
                <textarea
                    v-model="requestRevisionForm.remarks"
                    rows="3"
                    placeholder="What needs to be revised? (required)"
                    class="w-full bg-surface-container-low border rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none"
                    :class="requestRevisionForm.errors.remarks ? 'border-error' : 'border-outline/40'"
                />
                <p v-if="requestRevisionForm.errors.remarks" class="text-xs text-error">{{ requestRevisionForm.errors.remarks }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="isRevisionFormOpen = false" class="text-sm font-semibold text-on-surface-variant px-4 py-2 rounded-lg hover:bg-surface-container">Cancel</button>
                    <button type="submit" :disabled="requestRevisionForm.processing || !requestRevisionForm.remarks.trim()" class="bg-primary text-white font-semibold text-sm px-5 py-2 rounded-lg disabled:opacity-60">Send Back to Designer</button>
                </div>
            </form>
        </div>

        <div v-else-if="isApproved" class="flex flex-col items-end gap-1.5 pt-2 border-t border-outline/10">
            <p class="text-xs text-on-surface-variant/70">
                Approved by {{ assignment.approved_by?.full_name ?? 'the Infrastructure Manager' }}
                <template v-if="assignment.design_plan_approved_at">on {{ formatDate(assignment.design_plan_approved_at) }}</template>.
            </p>
        </div>
    </div>

    <!-- Survey Findings (read-only, once past Site Survey) -->
    <div v-else-if="pipeline.site_survey" class="bg-surface-bright rounded-2xl border border-outline/10 p-7">
        <div class="flex items-center gap-2.5 mb-6">
            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">fact_check</span>
            <p class="text-sm font-bold text-on-surface">Survey Findings</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-10 gap-y-5">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Surveyor</p>
                <p class="text-sm text-on-surface">{{ pipeline.site_survey.surveyor?.full_name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Survey Date</p>
                <p class="text-sm text-on-surface">{{ pipeline.site_survey.survey_date ? formatDate(pipeline.site_survey.survey_date) : '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Completed</p>
                <p class="text-sm text-on-surface">{{ pipeline.site_survey.survey_completed_at ? formatDate(pipeline.site_survey.survey_completed_at) : '—' }}</p>
            </div>
        </div>

        <div v-if="pipeline.site_survey.providers?.length" class="pt-6 mt-6 border-t border-outline/10">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">ISP Providers Noted at Survey</p>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="provider in pipeline.site_survey.providers"
                    :key="provider.id"
                    class="text-xs font-semibold px-3 py-1.5 rounded-full bg-surface-container text-on-surface-variant"
                >{{ provider.provider_name }}</span>
            </div>
        </div>
    </div>

    <!-- Activity History -->
    <div class="bg-surface-bright rounded-2xl border border-outline/10 p-7">
        <div class="flex items-center gap-2.5 mb-6">
            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">history</span>
            <p class="text-sm font-bold text-on-surface">Activity History</p>
        </div>

        <div v-if="activityLogs.length" class="flex flex-col divide-y divide-outline/10">
            <div v-for="log in activityLogs" :key="log.id" class="py-4 flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-on-surface">{{ log.description }}</p>
                    <p class="text-xs text-on-surface-variant mt-1">{{ log.user?.full_name ?? 'System' }}</p>
                </div>
                <p class="text-xs text-on-surface-variant shrink-0">{{ formatDateTime(log.created_at) }}</p>
            </div>
        </div>
        <p v-else class="text-sm text-on-surface-variant/60">No activity recorded yet.</p>
    </div>
</template>
