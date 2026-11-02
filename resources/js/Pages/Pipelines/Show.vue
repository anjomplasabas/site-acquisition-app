<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { formatDate, formatDateTime, getInitials } from '@/utils/pipeline.js'
import SurveyPanel from '@/Components/Pipelines/SurveyPanel.vue'
import CityProvinceFields from '@/Components/Pipelines/CityProvinceFields.vue'
import SitePlanningReferenceCard from '@/Components/Pipelines/SitePlanningReferenceCard.vue'
import CommercialsPanel from '@/Components/Pipelines/CommercialsPanel.vue'
import ApprovalPanel from '@/Components/Pipelines/ApprovalPanel.vue'
import PipelineStickyHeader from '@/Components/Pipelines/PipelineStickyHeader.vue'
import DocumentPreviewModal from '@/Components/Pipelines/DocumentPreviewModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    pipeline: Object,
    saqSpecialists: Array,
    siteProfileTypes: Array,
    can: Object,
    flash: Object,
    hasApprovalConfig: Boolean,
    documentTypes: Array,
    allDocumentTypes: Array,
    missingDocumentRequirements: Array,
})

const page = usePage()
const authUser = page.props.auth?.user
const authUserRoleId = authUser?.role_id

const commercialsDocumentTypes = computed(() => (props.allDocumentTypes ?? []).filter(type => type.stage === 'Commercials'))

// ─── Banners ──────────────────────────────────────────────────────────────────
const showErrorBanner = ref(!!page.props.errors?.required_documents)

watch(() => page.props.errors?.required_documents, (error) => {
    showErrorBanner.value = !!error
})

const pipelineRefreshOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['pipeline', 'can', 'flash', 'documentTypes', 'missingDocumentRequirements'],
    // Set explicitly (not just via the `errors.required_documents` watcher below) so the
    // banner reappears even when a repeat submission fails with the identical message —
    // Vue's watcher only fires on a value *change*, so an unchanged error string across two
    // consecutive failed attempts would otherwise never re-trigger it once dismissed.
    onError: (errors) => {
        showErrorBanner.value = !!errors.required_documents
    },
}

const moveToPendingForm = useForm({})
function moveToSiteSurveyPending() {
    moveToPendingForm.post(`/pipelines/${props.pipeline.id}/move-to-site-survey-pending`, pipelineRefreshOptions)
}

// ─── All documents (for Documents History) ────────────────────────────────────
const allDocuments = computed(() => {
    const surveyDocs = props.pipeline.site_survey?.documents ?? []
    const pipelineDocs = props.pipeline.documents ?? []
    const seen = new Set()
    return [...surveyDocs, ...pipelineDocs].filter(doc => {
        if (seen.has(doc.id)) { return false }
        seen.add(doc.id)
        return true
    })
})

const uploadedDocumentCategories = computed(() => new Set((props.pipeline.documents ?? []).map(doc => doc.category).filter(Boolean)))

const visitLogs = computed(() =>
    (props.pipeline.remark_logs ?? []).filter(log => (log.type ?? 'visit') === 'visit')
)

// ─── Document viewer (for Documents History in the left column) ───────────────
const viewingDoc = ref(null)

function documentUrl(doc) {
    return doc.url
}

function openDocument(doc) {
    if (doc.type === 'photo') {
        viewingDoc.value = doc
    } else {
        if (!documentUrl(doc)) { return }

        window.open(documentUrl(doc), '_blank')
    }
}

function documentIcon(doc) {
    return doc.type === 'photo' ? 'image' : 'description'
}

// ─── Add remark ───────────────────────────────────────────────────────────────
const addRemarkForm = useForm({ content: '' })

function submitAddRemark() {
    addRemarkForm.post(`/pipelines/${props.pipeline.id}/add-remark`, {
        ...pipelineRefreshOptions,
        onSuccess: () => { addRemarkForm.reset() },
    })
}

// ─── Edit pipeline details ────────────────────────────────────────────────────
const isEditingDetails = ref(false)

// Registered By is intentionally not part of the form — it is stamped with the creating
// user and read-only thereafter.
const editDetailsForm = useForm({
    name: props.pipeline.name ?? '',
    endorsement_date: props.pipeline.endorsement_date
        ? new Date(props.pipeline.endorsement_date).toISOString().slice(0, 10)
        : '',
})

function submitEditDetails() {
    editDetailsForm.patch(`/pipelines/${props.pipeline.id}`, {
        ...pipelineRefreshOptions,
        onSuccess: () => { isEditingDetails.value = false },
    })
}

const assignSaqForm = useForm({
    assigned_saq_id: props.pipeline.assigned_saq?.id ?? '',
    remarks: '',
})

// Reassigning after Prospecting is the Super Admin resigned-staff flow — the server
// requires a reason, logged to the pipeline's remark history.
const isLateSaqReassignment = computed(() => !['Prospect', 'Prospecting'].includes(props.pipeline.status))

function submitAssignSaq() {
    assignSaqForm.patch(`/pipelines/${props.pipeline.id}/assign-saq`, {
        ...pipelineRefreshOptions,
    })
}

// ─── Edit site profile ────────────────────────────────────────────────────────
const isEditingSiteProfile = ref(false)

// Dormitory/site-wide metrics requested by Sales Corp & Infra — edited and displayed
// generically from this list so each stays in sync with the Create Pipeline form.
const siteMetricFields = [
    { key: 'total_building_count', label: 'Total No. of Towers / Buildings', type: 'number' },
    { key: 'total_floors_per_tower', label: 'Total Floor per Tower', type: 'number' },
    { key: 'total_commercial_unit_count', label: 'Total Commercial Units / Residential Floor', type: 'number' },
    { key: 'residential_floor_starts_at', label: 'Residential Unit Starting Floor', type: 'number' },
    { key: 'floors_not_in_plan', label: 'Floor Not in Plan', type: 'text' },
    { key: 'turnover_rate', label: 'Turnover Rate (%)', type: 'number' },
    { key: 'occupancy_rate', label: 'Occupancy Rate (%)', type: 'number' },
    { key: 'whz_location', label: 'WHZ Location', type: 'text' },
    { key: 'total_whz_allocation', label: 'Total WHZ Allocation', type: 'number' },
    { key: 'wifi_area_location', label: 'Free WiFi Area/Location', type: 'text' },
]

const editSiteProfileForm = useForm({
    name: props.pipeline.site_profile?.name ?? '',
    type: props.pipeline.site_profile?.type ?? '',
    type_other: props.pipeline.site_profile?.type_other ?? '',
    developer: props.pipeline.site_profile?.developer ?? '',
    total_unit_count: props.pipeline.site_profile?.total_unit_count ?? '',
    ...Object.fromEntries(siteMetricFields.map(field => [field.key, props.pipeline.site_profile?.[field.key] ?? ''])),
    street: props.pipeline.site_profile?.address?.street ?? '',
    barangay: props.pipeline.site_profile?.address?.barangay ?? '',
    city: props.pipeline.site_profile?.address?.city ?? '',
    province: props.pipeline.site_profile?.address?.province ?? '',
    contact_name: props.pipeline.site_profile?.contact?.name ?? '',
    contact_designation: props.pipeline.site_profile?.contact?.designation ?? '',
    contact_email: props.pipeline.site_profile?.contact?.email ?? '',
    contact_mobile_number: props.pipeline.site_profile?.contact?.mobile_number ?? '',
})

function submitEditSiteProfile() {
    editSiteProfileForm.patch(`/site-profiles/${props.pipeline.site_profile?.id}`, {
        ...pipelineRefreshOptions,
        onSuccess: () => { isEditingSiteProfile.value = false },
    })
}

// ─── Pipeline lifecycle actions ───────────────────────────────────────────────
const startAcquisitionFileInput = ref(null)
const startAcquisitionForm = useForm({
    content: '',
    document_category: '',
    files: [],
})
const markActiveForm = useForm({})
const markInactiveForm = useForm({})
// Marking Inactive is consequential — require an explicit confirmation first.
const isConfirmingInactive = ref(false)
const markCompletedForm = useForm({})

function submitStartAcquisition() {
    startAcquisitionForm.post(`/pipelines/${props.pipeline.id}/start-acquisition`, {
        ...pipelineRefreshOptions,
        forceFormData: true,
        onSuccess: () => {
            startAcquisitionForm.files = []

            if (startAcquisitionFileInput.value) {
                startAcquisitionFileInput.value.value = ''
            }
        },
    })
}

function markActive() {
    markActiveForm.post(`/pipelines/${props.pipeline.id}/mark-active`, pipelineRefreshOptions)
}

function markCompleted() {
    markCompletedForm.post(`/pipelines/${props.pipeline.id}/mark-completed`, pipelineRefreshOptions)
}

function markInactive() {
    markInactiveForm.post(`/pipelines/${props.pipeline.id}/mark-inactive`, pipelineRefreshOptions)
}
</script>

<template>
    <Head :title="pipeline.name" />

    <PipelineStickyHeader
        :pipeline="pipeline"
        :error="showErrorBanner ? page.props.errors?.required_documents : ''"
        @dismiss-error="showErrorBanner = false"
    />
    <!-- Main content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left: Pipeline details -->
        <div class="lg:col-span-2 flex flex-col gap-6">

            <!-- Core info -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site Details</p>
                    <button
                        v-if="!isEditingDetails && can?.update"
                        @click="isEditingDetails = true"
                        class="flex items-center gap-1 text-xs font-bold text-primary hover:underline transition-colors"
                    >
                        <span class="material-symbols-outlined text-[14px]">edit</span>
                        Edit
                    </button>
                </div>

                <!-- Edit form -->
                <form v-if="isEditingDetails" @submit.prevent="submitEditDetails" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Pipeline Name</label>
                        <input
                            v-model="editDetailsForm.name"
                            type="text"
                            class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                            :class="editDetailsForm.errors.name ? 'border-error' : 'border-outline/40'"
                        />
                        <p v-if="editDetailsForm.errors.name" class="text-xs text-error flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">error</span>
                            {{ editDetailsForm.errors.name }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Registered By</label>
                        <div class="w-full flex items-center gap-2 text-sm font-medium text-on-surface-variant bg-surface-container border border-outline/20 rounded-lg px-4 py-2.5">
                            <span class="material-symbols-outlined text-[15px] text-on-surface-variant/60">lock</span>
                            {{ pipeline.project_manager?.full_name ?? '—' }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Site Profile</label>
                        <!-- The site is fixed at creation — re-pointing a pipeline to another site would rewrite the deal's identity. -->
                        <div class="w-full flex items-center gap-2 text-sm font-medium text-on-surface-variant bg-surface-container border border-outline/20 rounded-lg px-4 py-2.5">
                            <span class="material-symbols-outlined text-[15px] text-on-surface-variant/60">lock</span>
                            {{ pipeline.site_profile?.name ?? '—' }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Endorsement Date</label>
                        <input
                            v-model="editDetailsForm.endorsement_date"
                            type="date"
                            min="2019-01-01"
                            :max="new Date().toISOString().slice(0, 10)"
                            class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer"
                            :class="editDetailsForm.errors.endorsement_date ? 'border-error' : 'border-outline/40'"
                        />
                        <p v-if="editDetailsForm.errors.endorsement_date" class="text-xs text-error flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">error</span>
                            {{ editDetailsForm.errors.endorsement_date }}
                        </p>
                    </div>

                    <div class="flex gap-3">
                        <button
                            type="submit"
                            :disabled="editDetailsForm.processing"
                            class="flex-1 flex items-center justify-center gap-2 bg-primary text-white font-semibold text-sm px-4 py-2.5 rounded-lg hover:brightness-110 transition-all disabled:opacity-60 shadow-sm"
                        >
                            <span v-if="editDetailsForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                            <span v-else class="material-symbols-outlined text-[16px]">save</span>
                            Save
                        </button>
                        <button type="button" @click="isEditingDetails = false" class="flex-1 flex items-center justify-center border border-outline/40 text-on-surface-variant font-semibold text-sm px-4 py-2.5 rounded-lg hover:bg-surface-container transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>

                <!-- Read-only view -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Registered By</p>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center shrink-0 text-[10px] font-bold text-primary">
                                {{ getInitials(pipeline.project_manager?.full_name) }}
                            </div>
                            <span class="text-sm font-semibold text-on-surface">{{ pipeline.project_manager?.full_name ?? '—' }}</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Assigned SAQ</p>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-blue-50 flex items-center justify-center shrink-0 text-[10px] font-bold text-blue-700">
                                {{ getInitials(pipeline.assigned_saq?.full_name) }}
                            </div>
                            <span class="text-sm font-semibold text-on-surface">{{ pipeline.assigned_saq?.full_name ?? 'Unassigned' }}</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Current Stage Owner</p>
                        <p class="text-sm font-semibold text-on-surface">{{ pipeline.stage_context?.owner_label ?? 'No pending owner' }}</p>
                        <p v-if="pipeline.stage_context?.stage_age_label" class="text-xs text-on-surface-variant mt-0.5">
                            {{ pipeline.stage_context.stage_age_label }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Endorsement Date</p>
                        <p class="text-sm font-semibold text-on-surface">{{ formatDate(pipeline.endorsement_date) }}</p>
                    </div>
                    <form
                        v-if="can?.assignSaq"
                        class="sm:col-span-2 flex flex-col gap-2 border-t border-outline/10 pt-4"
                        @submit.prevent="submitAssignSaq"
                    >
                        <label for="show-assign-saq-specialist" class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                            Assign SAQ Specialist
                        </label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="relative flex-1">
                                <select
                                    id="show-assign-saq-specialist"
                                    v-model="assignSaqForm.assigned_saq_id"
                                    class="w-full appearance-none bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-10"
                                    :class="assignSaqForm.errors.assigned_saq_id ? 'border-error' : 'border-outline/40'"
                                >
                                    <option value="">Select SAQ Specialist</option>
                                    <option v-for="saq in saqSpecialists" :key="saq.id" :value="saq.id">{{ saq.full_name }}</option>
                                </select>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
                            </div>
                            <button
                                type="submit"
                                :disabled="assignSaqForm.processing || !assignSaqForm.assigned_saq_id"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span v-if="assignSaqForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                                <span v-else class="material-symbols-outlined text-[16px]">person_add</span>
                                Assign
                            </button>
                        </div>
                        <textarea
                            v-if="isLateSaqReassignment"
                            v-model="assignSaqForm.remarks"
                            rows="2"
                            placeholder="Reason for reassignment (required) — e.g. previous SAQ Specialist resigned"
                            class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                            :class="assignSaqForm.errors.remarks ? 'border-error' : 'border-outline/40'"
                        ></textarea>
                        <p v-if="assignSaqForm.errors.remarks" class="text-xs text-error flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">error</span>
                            {{ assignSaqForm.errors.remarks }}
                        </p>
                        <p v-if="assignSaqForm.errors.assigned_saq_id" class="text-xs text-error flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">error</span>
                            {{ assignSaqForm.errors.assigned_saq_id }}
                        </p>
                    </form>
                </div>
            </div>

            <!-- Site Profile -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site Profile</p>
                        <a
                            v-if="pipeline.site_profile?.id"
                            :href="`/site-management/${pipeline.site_profile.id}`"
                            class="text-[10px] font-bold text-primary hover:underline"
                        >
                            Site Details
                        </a>
                    </div>
                    <button
                        v-if="!isEditingSiteProfile && can?.update && pipeline.status !== 'Completed'"
                        @click="isEditingSiteProfile = true"
                        class="flex items-center gap-1 text-xs font-bold text-primary hover:underline transition-colors"
                    >
                        <span class="material-symbols-outlined text-[14px]">edit</span>
                        Edit
                    </button>
                </div>

                <!-- Edit form -->
                <form v-if="isEditingSiteProfile" @submit.prevent="submitEditSiteProfile" class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Site Name</label>
                            <input
                                v-model="editSiteProfileForm.name"
                                type="text"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="editSiteProfileForm.errors.name ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="editSiteProfileForm.errors.name" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors.name }}
                            </p>
                        </div>


                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Type</label>
                            <div class="relative">
                                <select
                                    v-model="editSiteProfileForm.type"
                                    class="w-full appearance-none bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-10"
                                    :class="editSiteProfileForm.errors.type ? 'border-error' : 'border-outline/40'"
                                >
                                    <option value="" disabled>Select type</option>
                                    <option v-if="editSiteProfileForm.type && !siteProfileTypes.includes(editSiteProfileForm.type)" :value="editSiteProfileForm.type">{{ editSiteProfileForm.type }}</option>
                                    <option v-for="t in siteProfileTypes" :key="t" :value="t">{{ t }}</option>
                                </select>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
                            </div>
                            <input
                                v-if="editSiteProfileForm.type === 'Others'"
                                v-model="editSiteProfileForm.type_other"
                                type="text"
                                maxlength="255"
                                placeholder="Specify the type"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="editSiteProfileForm.errors.type_other ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="editSiteProfileForm.errors.type_other" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors.type_other }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Developer</label>
                            <input
                                v-model="editSiteProfileForm.developer"
                                type="text"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="editSiteProfileForm.errors.developer ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="editSiteProfileForm.errors.developer" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors.developer }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total Unit per Tower</label>
                            <input
                                v-model="editSiteProfileForm.total_unit_count"
                                type="number"
                                min="0"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="editSiteProfileForm.errors.total_unit_count ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="editSiteProfileForm.errors.total_unit_count" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors.total_unit_count }}
                            </p>
                        </div>

                        <div v-for="field in siteMetricFields" :key="field.key" class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">{{ field.label }}</label>
                            <input
                                v-model="editSiteProfileForm[field.key]"
                                :type="field.type"
                                :min="field.type === 'number' ? 0 : undefined"
                                :step="field.key.endsWith('_rate') ? '0.01' : undefined"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="editSiteProfileForm.errors[field.key] ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="editSiteProfileForm.errors[field.key]" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors[field.key] }}
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-outline/10 pt-4">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Address</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5 sm:col-span-2">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Street</label>
                                <input
                                    v-model="editSiteProfileForm.street"
                                    type="text"
                                    class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="editSiteProfileForm.errors.street ? 'border-error' : 'border-outline/40'"
                                />
                                <p v-if="editSiteProfileForm.errors.street" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors.street }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Barangay</label>
                                <input
                                    v-model="editSiteProfileForm.barangay"
                                    type="text"
                                    class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="editSiteProfileForm.errors.barangay ? 'border-error' : 'border-outline/40'"
                                />
                                <p v-if="editSiteProfileForm.errors.barangay" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors.barangay }}
                                </p>
                            </div>
                            <CityProvinceFields
                                v-model:city="editSiteProfileForm.city"
                                v-model:province="editSiteProfileForm.province"
                                :city-error="editSiteProfileForm.errors.city"
                                :province-error="editSiteProfileForm.errors.province"
                                dense
                            />
                        </div>
                    </div>

                    <div class="border-t border-outline/10 pt-4">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Contact Person</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Name</label>
                                <input
                                    v-model="editSiteProfileForm.contact_name"
                                    type="text"
                                    class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="editSiteProfileForm.errors.contact_name ? 'border-error' : 'border-outline/40'"
                                />
                                <p v-if="editSiteProfileForm.errors.contact_name" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors.contact_name }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Designation</label>
                                <input
                                    v-model="editSiteProfileForm.contact_designation"
                                    type="text"
                                    class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="editSiteProfileForm.errors.contact_designation ? 'border-error' : 'border-outline/40'"
                                />
                                <p v-if="editSiteProfileForm.errors.contact_designation" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors.contact_designation }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Mobile Number</label>
                                <input
                                    v-model="editSiteProfileForm.contact_mobile_number"
                                    type="text"
                                    class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="editSiteProfileForm.errors.contact_mobile_number ? 'border-error' : 'border-outline/40'"
                                />
                                <p v-if="editSiteProfileForm.errors.contact_mobile_number" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors.contact_mobile_number }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Email</label>
                                <input
                                    v-model="editSiteProfileForm.contact_email"
                                    type="email"
                                    class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="editSiteProfileForm.errors.contact_email ? 'border-error' : 'border-outline/40'"
                                />
                                <p v-if="editSiteProfileForm.errors.contact_email" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ editSiteProfileForm.errors.contact_email }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button
                            type="submit"
                            :disabled="editSiteProfileForm.processing"
                            class="flex-1 flex items-center justify-center gap-2 bg-primary text-white font-semibold text-sm px-4 py-2.5 rounded-lg hover:brightness-110 transition-all disabled:opacity-60 shadow-sm"
                        >
                            <span v-if="editSiteProfileForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                            <span v-else class="material-symbols-outlined text-[16px]">save</span>
                            Save
                        </button>
                        <button type="button" @click="isEditingSiteProfile = false" class="flex-1 flex items-center justify-center border border-outline/40 text-on-surface-variant font-semibold text-sm px-4 py-2.5 rounded-lg hover:bg-surface-container transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>

                <!-- Read-only view -->
                <template v-else>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Site Name</p>
                            <p class="text-sm font-semibold text-on-surface break-words">{{ pipeline.site_profile?.name ?? '—' }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Type</p>
                            <p class="text-sm font-semibold text-on-surface break-words">{{ pipeline.site_profile?.type === 'Others' && pipeline.site_profile?.type_other ? `Others (${pipeline.site_profile.type_other})` : (pipeline.site_profile?.type ?? '—') }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Developer</p>
                            <p class="text-sm font-semibold text-on-surface break-words">{{ pipeline.site_profile?.developer ?? '—' }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Total Unit per Tower</p>
                            <p class="text-sm font-semibold text-on-surface break-words">
                                {{ pipeline.site_profile?.total_unit_count ? pipeline.site_profile.total_unit_count.toLocaleString() : '—' }}
                            </p>
                        </div>
                        <div v-for="field in siteMetricFields" :key="field.key" class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">{{ field.label }}</p>
                            <p class="text-sm font-semibold text-on-surface break-words">{{ pipeline.site_profile?.[field.key] ?? '—' }}</p>
                        </div>
                        <div class="col-span-2 min-w-0" v-if="pipeline.site_profile?.address">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Address</p>
                            <p class="text-sm text-on-surface break-words">
                                {{ [pipeline.site_profile.address.street, pipeline.site_profile.address.barangay, pipeline.site_profile.address.city, pipeline.site_profile.address.province].filter(Boolean).join(', ') || '—' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="pipeline.site_profile?.contact" class="mt-5 pt-5 border-t border-outline/10">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Contact Person</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Name</p>
                                <p class="text-sm font-semibold text-on-surface break-words">{{ pipeline.site_profile.contact.name }}</p>
                                <p v-if="pipeline.site_profile.contact.designation" class="text-xs text-on-surface-variant mt-0.5 break-words">{{ pipeline.site_profile.contact.designation }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Contact</p>
                                <p v-if="pipeline.site_profile.contact.mobile_number" class="text-sm text-on-surface break-words">{{ pipeline.site_profile.contact.mobile_number }}</p>
                                <p v-if="pipeline.site_profile.contact.email" class="text-xs text-on-surface-variant mt-0.5 break-words">{{ pipeline.site_profile.contact.email }}</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <SitePlanningReferenceCard
                v-if="pipeline.site_survey && !can?.editSurveyFindings"
                :pipeline="pipeline"
            />

            <!-- Status History -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Status History</p>
                <div v-if="pipeline.status_logs?.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-outline/10">
                                <th class="text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant pb-2 pr-4">Status</th>
                                <th class="text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant pb-2 pr-4">Changed By</th>
                                <th class="text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant pb-2">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline/10">
                            <tr v-for="log in pipeline.status_logs" :key="log.id">
                                <td class="py-2.5 pr-4">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-on-surface">
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary shrink-0"></span>
                                        {{ log.status }}
                                    </span>
                                </td>
                                <td class="py-2.5 pr-4 text-xs text-on-surface-variant">{{ log.changed_by?.full_name ?? '—' }}</td>
                                <td class="py-2.5 text-xs text-on-surface-variant">{{ formatDateTime(log.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-xs text-on-surface-variant">No status changes recorded yet.</p>
            </div>

            <SurveyPanel
                v-if="can?.editSurveyFindings"
                :pipeline="pipeline"
                :document-types="documentTypes"
                :missing-document-requirements="missingDocumentRequirements"
            />

            <!-- Activity History -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Activity History</p>

                <!-- Add Activity Form -->
                <form @submit.prevent="submitAddRemark" class="flex flex-col gap-2 mb-5">
                    <textarea
                        v-model="addRemarkForm.content"
                        rows="3"
                        placeholder="Add an activity note..."
                        class="w-full text-sm bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-1 focus:ring-primary resize-none"
                        :class="addRemarkForm.errors.content ? 'border-error' : 'border-outline/40'"
                    ></textarea>
                    <p v-if="addRemarkForm.errors.content" class="text-xs text-error flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span>
                        {{ addRemarkForm.errors.content }}
                    </p>
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="addRemarkForm.processing || !addRemarkForm.content.trim()"
                            class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-4 py-2 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                        >
                            <span v-if="addRemarkForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                            <span v-else class="material-symbols-outlined text-[16px]">add_comment</span>
                            Add Visit
                        </button>
                    </div>
                </form>

                <!-- History -->
                <div v-if="visitLogs.length" class="flex flex-col divide-y divide-outline/10">
                    <div v-for="log in visitLogs" :key="log.id" class="py-3 first:pt-0 last:pb-0">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                {{ log.status }}
                            </span>
                            <span class="text-[10px] text-on-surface-variant shrink-0">{{ formatDateTime(log.created_at) }}</span>
                        </div>
                        <p class="text-sm text-on-surface leading-relaxed">{{ log.content }}</p>
                        <p class="text-xs text-on-surface-variant mt-1">— {{ log.added_by?.full_name ?? '—' }}</p>
                    </div>
                </div>
                <p v-else class="text-xs text-on-surface-variant/60 text-sm">No visits recorded yet.</p>
            </div>

            <!-- Documents History -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Documents History</p>
                <div v-if="allDocuments.length" class="flex flex-col divide-y divide-outline/10">
                    <button
                        v-for="doc in allDocuments"
                        :key="doc.id"
                        type="button"
                        @click="openDocument(doc)"
                        class="py-3 first:pt-0 last:pb-0 flex items-start gap-3 w-full text-left hover:bg-surface-container-low rounded-lg px-2 -mx-2 transition-colors group"
                    >
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0 mt-0.5 group-hover:bg-primary/20 transition-colors">
                            <span class="material-symbols-outlined text-[16px] text-primary">{{ documentIcon(doc) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-on-surface truncate group-hover:text-primary transition-colors">{{ doc.name }}</p>
                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ doc.uploaded_status ?? '—' }}</span>
                                <span v-if="doc.version > 1" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary">v{{ doc.version }}</span>
                                <span v-if="doc.category" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary">{{ doc.category }}</span>
                                <span class="text-[10px] text-on-surface-variant">· {{ doc.uploaded_by?.full_name ?? '—' }}</span>
                                <span class="text-[10px] text-on-surface-variant">· {{ formatDateTime(doc.pivot?.created_at) }}</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[14px] text-on-surface-variant/40 group-hover:text-primary shrink-0 mt-1 transition-colors">
                            {{ doc.type === 'photo' ? 'open_in_full' : 'open_in_new' }}
                        </span>
                    </button>
                </div>
                <p v-else class="text-xs text-on-surface-variant">No documents uploaded yet.</p>
            </div>

        </div>

        <!-- Right: Action panel -->
        <div class="lg:col-span-1 flex flex-col gap-4 lg:sticky lg:top-24 lg:self-start lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto lg:pr-1">

            <!-- Prospect: Start Prospecting -->
            <div
                v-if="pipeline.status === 'Prospect' && can?.startAcquisition"
                class="bg-surface-bright rounded-xl border border-outline/10 p-6"
            >
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-primary text-[20px]">rocket_launch</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Start Prospecting</p>
                        <p class="text-xs text-on-surface-variant">Record the site status before active prospecting starts.</p>
                    </div>
                </div>
                <form @submit.prevent="submitStartAcquisition" class="flex flex-col gap-3">
                    <textarea
                        v-model="startAcquisitionForm.content"
                        rows="3"
                        placeholder="Current site status before prospecting..."
                        class="w-full text-sm bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-1 focus:ring-primary resize-none"
                        :class="startAcquisitionForm.errors.content ? 'border-error' : 'border-outline/40'"
                    ></textarea>
                    <p v-if="startAcquisitionForm.errors.content" class="text-xs text-error flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span>
                        {{ startAcquisitionForm.errors.content }}
                    </p>
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Documents</label>
                        <div v-if="documentTypes.length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="type in documentTypes"
                                :key="type.id"
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full border"
                                :class="uploadedDocumentCategories.has(type.name)
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                    : missingDocumentRequirements.includes(type.name)
                                        ? 'bg-amber-50 text-amber-700 border-amber-200'
                                        : 'bg-surface-container text-on-surface-variant border-outline/20'"
                            >
                                {{ type.name }} {{ uploadedDocumentCategories.has(type.name) ? 'Uploaded' : (missingDocumentRequirements.includes(type.name) ? 'Required' : 'Optional') }}
                            </span>
                        </div>
                        <div v-if="documentTypes.length" class="relative">
                            <select
                                v-model="startAcquisitionForm.document_category"
                                class="w-full appearance-none bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-xs text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-9"
                            >
                                <option value="">No category</option>
                                <option v-for="type in documentTypes" :key="type.id" :value="type.name">{{ type.name }}</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[16px]">expand_more</span>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer border border-dashed border-outline/40 rounded-lg px-3 py-3 hover:bg-surface-container-low transition-colors">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant">upload_file</span>
                            <span class="text-xs text-on-surface-variant">
                                {{ startAcquisitionForm.files.length ? `${startAcquisitionForm.files.length} file(s) selected` : 'Upload supporting documents' }}
                            </span>
                            <input ref="startAcquisitionFileInput" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" class="sr-only" @change="startAcquisitionForm.files = Array.from($event.target.files)" />
                        </label>
                        <p v-if="startAcquisitionForm.errors['files.0']" class="text-xs text-error flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">error</span>
                            {{ startAcquisitionForm.errors['files.0'] }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="startAcquisitionForm.processing || !startAcquisitionForm.content.trim()"
                        class="w-full py-2.5 text-sm font-bold bg-primary text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                    >
                        Start Prospecting
                    </button>
                </form>
            </div>

            <!-- RFD: Mark Completed -->
            <div
                v-if="pipeline.status === 'RFD' && can?.markActive"
                class="bg-surface-bright rounded-xl border border-outline/10 p-6"
            >
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-green-600 text-[20px]">check_circle</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Mark as Completed</p>
                        <p class="text-xs text-on-surface-variant">Deployment is done and the site is ready to close.</p>
                    </div>
                </div>
                <form @submit.prevent="markActive">
                    <button
                        type="submit"
                        :disabled="markActiveForm.processing"
                        class="w-full py-2.5 text-sm font-bold bg-green-600 text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                    >
                        Mark as Completed
                    </button>
                </form>
            </div>

            <!-- RFS: Mark Completed -->
            <div
                v-if="pipeline.status === 'RFS' && can?.markCompleted"
                class="bg-surface-bright rounded-xl border border-outline/10 p-6"
            >
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-9 h-9 rounded-lg bg-purple-50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-purple-600 text-[20px]">task_alt</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Mark as Completed</p>
                        <p class="text-xs text-on-surface-variant">Close this site's lifecycle.</p>
                    </div>
                </div>
                <form @submit.prevent="markCompleted">
                    <button
                        type="submit"
                        :disabled="markCompletedForm.processing"
                        class="w-full py-2.5 text-sm font-bold bg-purple-600 text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                    >
                        Mark as Completed
                    </button>
                </form>
            </div>

            <!-- Mark Inactive (available for all active acquisition stages and RFS) -->
            <div
                v-if="can?.markInactive && !['Prospect', 'Inactive', 'Completed', 'Approval'].includes(pipeline.status)"
                class="bg-surface-bright rounded-xl border border-outline/10 p-6"
            >
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-red-500 text-[20px]">cancel</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Mark as Inactive</p>
                        <p class="text-xs text-on-surface-variant">Cancel or abandon this pipeline.</p>
                    </div>
                </div>
                <div v-if="isConfirmingInactive" class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 flex flex-col gap-2">
                    <p class="text-xs text-amber-800">
                        Mark this pipeline as Inactive? It will be removed from active views and approvers.
                        You can reactivate it later via Re-attempt.
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="markInactive(); isConfirmingInactive = false"
                            :disabled="markInactiveForm.processing"
                            class="bg-red-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg disabled:opacity-60"
                        >Yes, mark as Inactive</button>
                        <button type="button" @click="isConfirmingInactive = false" class="text-xs font-semibold text-on-surface-variant px-2 py-1.5">Cancel</button>
                    </div>
                </div>
                <form @submit.prevent="isConfirmingInactive = true">
                    <button
                        v-if="!isConfirmingInactive"
                        type="submit"
                        :disabled="markInactiveForm.processing"
                        class="w-full py-2.5 text-sm font-bold bg-red-500 text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                    >
                        Mark as Inactive
                    </button>
                </form>
            </div>

            <!-- Stage-specific panels -->
            <div
                v-if="pipeline.status === 'Prospecting' && can?.manageSiteVisit"
                class="bg-surface-bright rounded-xl border border-outline/10 p-6"
            >
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-primary text-[20px]">calendar_month</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-on-surface">Move to Site Survey Pending</p>
                        <p class="text-xs text-on-surface-variant">Send this site to infrastructure scheduling.</p>
                    </div>
                </div>
                <form @submit.prevent="moveToSiteSurveyPending">
                    <button type="submit" :disabled="moveToPendingForm.processing" class="w-full flex items-center justify-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-sm">
                        <span v-if="moveToPendingForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                        <span v-else class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        Move to Site Survey Pending
                    </button>
                </form>
            </div>

            <CommercialsPanel
                v-else-if="pipeline.status === 'Commercials' && can?.handleCommercials"
                :pipeline="pipeline"
                :has-approval-config="hasApprovalConfig"
                :document-types="documentTypes"
                :missing-document-requirements="missingDocumentRequirements"
            />

            <ApprovalPanel
                v-else-if="pipeline.status === 'Approval'"
                :pipeline="pipeline"
                :can="can"
                :auth-user-role-id="authUserRoleId"
                :document-types="commercialsDocumentTypes"
            />

            <!-- RFD: approved state -->
            <div
                v-else-if="pipeline.status === 'RFD'"
                class="bg-surface-bright rounded-xl border border-emerald-200 p-6 text-center"
            >
                <div class="w-14 h-14 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-emerald-500 text-[32px]">rocket_launch</span>
                </div>
                <p class="text-base font-bold text-on-surface mb-1">Ready for Deployment</p>
                <p class="text-xs text-on-surface-variant">All approvals cleared. Awaiting service readiness.</p>
                <Link
                    v-if="pipeline.project?.id"
                    :href="`/projects/${pipeline.project.id}`"
                    class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-colors"
                >
                    <span class="material-symbols-outlined text-[16px]">account_tree</span>
                    View Project
                </Link>
            </div>

            <!-- Fallback -->
            <div
                v-else
                class="bg-surface-bright rounded-xl border border-outline/10 p-6 text-center text-on-surface-variant"
            >
                <span class="material-symbols-outlined text-[36px] opacity-30 block mb-2">hourglass_empty</span>
                <p class="text-sm font-medium">No action required at this stage.</p>
            </div>

        </div>
    </div>

    <DocumentPreviewModal :document="viewingDoc" @close="viewingDoc = null" />
</template>
