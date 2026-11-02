<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { formatDate } from '@/utils/pipeline.js'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

const props = defineProps({
    pipeline: Object,
    can: Object,
    authUserRoleId: Number,
    documentTypes: Array,
})

const isRevisingCommercials = ref(false)
const showSurveyDetails = ref(false)
const showSitePlanningDetails = ref(false)
const showNetworkFeasibilityDetails = ref(false)
const showCommercialDocuments = ref(false)

const reviseCommercialsForm = useForm({ remarks: '', document_category: '', files: [] })
const fileInput = ref(null)
const pipelineRefreshOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['pipeline', 'can', 'flash', 'hasApprovalConfig', 'documentTypes'],
}

const decisionForms = ref(
    Object.fromEntries(
        (props.pipeline.approvals ?? []).map(a => [a.id, useForm({ decision: '', remarks: '' })])
    )
)

const sortedApprovals = computed(() =>
    [...(props.pipeline.approvals ?? [])].sort((a, b) => a.step - b.step)
)

const networkFeasibilities = computed(() => props.pipeline.site_survey?.network_feasibilities ?? [])

// Groups the Plans/Drawing checklist by section (Initial Draft, In Progress Design Plan - Fiber,
// Final Checking, etc.) in first-seen order — tasks arrive pre-sorted by sort_order, so this
// mirrors the section order shown on the Site Planning page's checklist itself.
const sitePlanningSections = computed(() => {
    const tasks = props.pipeline.site_planning_assignment?.site_planning_tasks ?? []
    const sections = []
    const bySection = new Map()

    for (const task of tasks) {
        const key = task.section ?? 'Other'
        if (!bySection.has(key)) {
            const group = { section: key, tasks: [] }
            bySection.set(key, group)
            sections.push(group)
        }
        bySection.get(key).tasks.push(task)
    }

    return sections.map(group => {
        const totalWeight = group.tasks.reduce((sum, task) => sum + Number(task.weight ?? 0), 0)
        const doneTasks = group.tasks.filter(task => task.status === 'Done')
        const doneWeight = doneTasks.reduce((sum, task) => sum + Number(task.weight ?? 0), 0)

        return {
            section: group.section,
            doneCount: doneTasks.length,
            totalCount: group.tasks.length,
            weightPercent: Math.round(totalWeight * 100) / 100,
            percentDone: totalWeight > 0 ? Math.round((doneWeight / totalWeight) * 100) : 0,
        }
    })
})

const commercialDocuments = computed(() =>
    (props.pipeline.documents ?? []).filter(doc => doc.uploaded_status === 'Commercials')
)

const activeApprovalStep = computed(() =>
    sortedApprovals.value.find(a => a.status === 'pending')?.step ?? null
)

function approvalStepState(approval) {
    if (approval.status === 'approved') return 'approved'
    if (approval.status === 'rejected') return 'rejected'
    if (approval.step === activeApprovalStep.value) return 'active'
    return 'waiting'
}

function providerStatusColor(status) {
    if (status === 'Feasible') return 'text-emerald-600 bg-emerald-50'
    if (status === 'Non Feasible') return 'text-red-600 bg-red-50'
    return 'text-amber-600 bg-amber-50'
}

function submitReviseCommercials() {
    reviseCommercialsForm.patch(`/pipelines/${props.pipeline.id}/set-commercials`, {
        ...pipelineRefreshOptions,
        forceFormData: true,
        onSuccess: () => {
            isRevisingCommercials.value = false
            reviseCommercialsForm.files = []
            reviseCommercialsForm.document_category = ''
            if (fileInput.value) fileInput.value.value = ''
        },
    })
}

function submitDecision(approval) {
    decisionForms.value[approval.id].post(
        `/pipelines/${props.pipeline.id}/approvals/${approval.id}/decide`,
        pipelineRefreshOptions
    )
}

function commercialDocumentUrl(document) {
    return `/pipelines/${props.pipeline.id}/commercial-documents/${document.id}`
}
</script>

<template>
    <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-indigo-600 text-[20px]">approval</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-on-surface">Approval Process</p>
                <p class="text-xs text-on-surface-variant">
                    {{ sortedApprovals.filter(a => a.status === 'approved').length }}/{{ sortedApprovals.length }} approved
                </p>
            </div>
            <button v-if="!isRevisingCommercials && can?.handleCommercials" @click="isRevisingCommercials = true"
                class="flex items-center gap-1 text-xs font-bold text-primary hover:underline transition-colors shrink-0">
                <span class="material-symbols-outlined text-[14px]">edit</span>
                Revise
            </button>
        </div>

        <!-- Revise commercials form -->
        <form v-if="isRevisingCommercials" @submit.prevent="submitReviseCommercials" class="flex flex-col gap-3 mb-5 p-4 bg-indigo-50/50 rounded-lg border border-indigo-100">
            <p class="text-xs font-bold text-indigo-700">Revising commercials will reset all approval steps back to pending.</p>

            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-bold uppercase tracking-widest text-indigo-700/70">Remarks</label>
                <textarea v-model="reviseCommercialsForm.remarks" rows="3" placeholder="Add any remarks..."
                    class="w-full text-sm bg-white border border-indigo-200 rounded-lg px-3 py-2.5 placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-none"></textarea>
            </div>

            <div class="flex flex-col gap-2">
                <div v-if="pipeline.documents?.length" class="flex flex-col gap-1.5">
                    <div v-for="doc in pipeline.documents" :key="doc.id" class="bg-white rounded-lg px-3 py-2 border border-indigo-100">
                        <DocumentRow
                            :document="doc"
                            :replace-url="`${commercialDocumentUrl(doc)}/replace`"
                            :delete-url="commercialDocumentUrl(doc)"
                            :can-manage="true"
                            :refresh-options="pipelineRefreshOptions"
                        />
                    </div>
                </div>

                <div class="relative">
                    <select
                        v-model="reviseCommercialsForm.document_category"
                        class="w-full appearance-none bg-white border border-indigo-200 rounded-lg px-3 py-2.5 text-xs text-on-surface focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 outline-none transition-all cursor-pointer pr-10"
                    >
                        <option value="">No category</option>
                        <option v-for="type in documentTypes" :key="type.id" :value="type.name">{{ type.name }}</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-indigo-400 text-[16px]">expand_more</span>
                </div>

                <label class="flex items-center gap-2 cursor-pointer border border-dashed border-indigo-200 rounded-lg px-3 py-2.5 hover:bg-indigo-50 transition-colors bg-white">
                    <span class="material-symbols-outlined text-[16px] text-indigo-400">upload_file</span>
                    <span class="text-xs text-indigo-600">
                        {{ reviseCommercialsForm.files.length ? `${reviseCommercialsForm.files.length} file(s) selected` : 'Upload new files' }}
                    </span>
                    <input ref="fileInput" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" class="sr-only" @change="reviseCommercialsForm.files = Array.from($event.target.files)" />
                </label>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="reviseCommercialsForm.processing"
                    class="flex-1 flex items-center justify-center gap-2 bg-primary text-white font-semibold text-sm px-4 py-2 rounded-lg hover:brightness-110 transition-all disabled:opacity-60 shadow-sm">
                    <span v-if="reviseCommercialsForm.processing" class="material-symbols-outlined text-[14px] animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-[14px]">save</span>
                    Save & Reset
                </button>
                <button type="button" @click="isRevisingCommercials = false" class="flex-1 flex items-center justify-center border border-outline/40 text-on-surface-variant font-semibold text-sm px-4 py-2 rounded-lg hover:bg-surface-container transition-colors">
                    Cancel
                </button>
            </div>
        </form>

        <!-- Survey snapshot accordion -->
        <div v-if="pipeline.site_survey" class="mb-5 border border-outline/10 rounded-lg overflow-hidden">
            <button type="button" @click="showSurveyDetails = !showSurveyDetails"
                class="w-full flex items-center justify-between px-4 py-3 bg-surface-container-low text-left hover:bg-surface-container transition-colors">
                <span class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Survey Summary</span>
                <span class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform" :class="showSurveyDetails ? 'rotate-180' : ''">expand_more</span>
            </button>
            <div v-if="showSurveyDetails" class="px-4 py-3 flex flex-col gap-3 bg-surface-bright">
                <div class="grid grid-cols-2 gap-2">
                    <div class="col-span-2">
                        <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-0.5">Survey Date</p>
                        <p class="text-sm font-semibold text-on-surface">{{ formatDate(pipeline.site_survey?.survey_date) }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-0.5">Floors</p>
                        <p class="text-sm font-semibold text-on-surface">{{ pipeline.site_survey?.floors_surveyed ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-0.5">Actual Units</p>
                        <p class="text-sm font-semibold text-on-surface">{{ pipeline.site_survey?.actual_unit_count ?? '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-0.5">Roof Access</p>
                        <div class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[14px]" :class="pipeline.site_survey?.has_roof_access ? 'text-emerald-500' : 'text-red-500'">
                                {{ pipeline.site_survey?.has_roof_access ? 'check_circle' : 'cancel' }}
                            </span>
                            <span class="text-sm font-semibold text-on-surface">
                                {{ pipeline.site_survey?.has_roof_access === null ? '—' : (pipeline.site_survey?.has_roof_access ? 'Yes' : 'No') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="pipeline.site_survey?.providers?.length">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">ISP Providers</p>
                    <div class="flex flex-col gap-1.5">
                        <div v-for="p in pipeline.site_survey.providers" :key="p.id" class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[13px] text-on-surface-variant shrink-0">cell_tower</span>
                            <span class="text-xs font-semibold text-on-surface">{{ p.provider_name }}</span>
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full ml-auto" :class="providerStatusColor(p.status)">{{ p.status }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="pipeline.site_survey?.equipment_notes">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-0.5">Equipment Notes</p>
                    <p class="text-xs text-on-surface">{{ pipeline.site_survey.equipment_notes }}</p>
                </div>

                <div v-if="pipeline.site_survey?.documents?.length">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Survey Files</p>
                    <div class="flex flex-col gap-1">
                        <DocumentRow v-for="doc in pipeline.site_survey.documents" :key="doc.id" :document="doc" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Site planning snapshot accordion -->
        <div v-if="pipeline.site_planning_assignment" class="mb-5 border border-outline/10 rounded-lg overflow-hidden">
            <button type="button" @click="showSitePlanningDetails = !showSitePlanningDetails"
                class="w-full flex items-center justify-between px-4 py-3 bg-surface-container-low text-left hover:bg-surface-container transition-colors">
                <span class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Site Planning Summary</span>
                <span class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform" :class="showSitePlanningDetails ? 'rotate-180' : ''">expand_more</span>
            </button>
            <div v-if="showSitePlanningDetails" class="px-4 py-3 flex flex-col gap-3 bg-surface-bright">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-0.5">Design Plan Type</p>
                        <p class="text-sm font-semibold text-on-surface">{{ pipeline.site_planning_assignment.design_plan_type ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-0.5">Assigned Designer</p>
                        <p class="text-sm font-semibold text-on-surface">{{ pipeline.site_planning_assignment.design_plan_assignee?.full_name ?? 'Unassigned' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-0.5">Status</p>
                        <p class="text-sm font-semibold text-on-surface">
                            <template v-if="pipeline.site_planning_assignment.design_plan_approved_at">
                                Approved by {{ pipeline.site_planning_assignment.approved_by?.full_name ?? 'the Infrastructure Manager' }} on {{ formatDate(pipeline.site_planning_assignment.design_plan_approved_at) }}
                            </template>
                            <template v-else-if="pipeline.site_planning_assignment.design_plan_completed_at">
                                Pending approval — submitted by {{ pipeline.site_planning_assignment.completed_by?.full_name ?? 'the Infra Designer' }} on {{ formatDate(pipeline.site_planning_assignment.design_plan_completed_at) }}
                            </template>
                            <template v-else>In progress</template>
                        </p>
                    </div>
                </div>

                <div v-if="sitePlanningSections.length" class="flex flex-col gap-2.5">
                    <div class="flex items-center justify-between">
                        <p class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant/60">Plans/Drawing Progress</p>
                        <p class="text-xs font-bold text-on-surface">
                            {{ pipeline.site_planning_assignment.plans_drawing_progress_percent ?? 0 }}% overall
                        </p>
                    </div>

                    <div v-for="group in sitePlanningSections" :key="group.section" class="flex flex-col gap-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface truncate">{{ group.section }}</span>
                            <span class="text-[10px] text-on-surface-variant shrink-0">{{ group.doneCount }}/{{ group.totalCount }} · {{ group.weightPercent }}% weight</span>
                        </div>
                        <div class="h-1.5 w-full bg-outline/10 rounded-full overflow-hidden">
                            <div class="h-full bg-primary rounded-full transition-all" :style="{ width: `${group.percentDone}%` }" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Network feasibility snapshot accordion -->
        <div v-if="networkFeasibilities.length" class="mb-5 border border-outline/10 rounded-lg overflow-hidden">
            <button type="button" @click="showNetworkFeasibilityDetails = !showNetworkFeasibilityDetails"
                class="w-full flex items-center justify-between px-4 py-3 bg-surface-container-low text-left hover:bg-surface-container transition-colors">
                <span class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Network Feasibility Summary</span>
                <span class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform" :class="showNetworkFeasibilityDetails ? 'rotate-180' : ''">expand_more</span>
            </button>
            <div v-if="showNetworkFeasibilityDetails" class="px-4 py-3 flex flex-col gap-3 bg-surface-bright">
                <p v-if="pipeline.site_survey?.network_feasibility_completed_at" class="text-xs text-on-surface-variant">
                    Completed by {{ pipeline.site_survey.network_feasibility_completed_by?.full_name ?? '—' }} on {{ formatDate(pipeline.site_survey.network_feasibility_completed_at) }}
                </p>
                <p v-else class="text-xs text-on-surface-variant italic">Feasibility check still in progress.</p>

                <div class="flex flex-col gap-1.5">
                    <div v-for="entry in networkFeasibilities" :key="entry.id" class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[13px] text-on-surface-variant shrink-0 mt-0.5">cell_tower</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-on-surface">{{ entry.isp_name }}</span>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full shrink-0" :class="providerStatusColor(entry.status)">{{ entry.status }}</span>
                            </div>
                            <p v-if="entry.notes" class="text-xs text-on-surface-variant mt-0.5">{{ entry.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Commercials documents accordion -->
        <div v-if="commercialDocuments.length" class="mb-5 border border-outline/10 rounded-lg overflow-hidden">
            <button type="button" @click="showCommercialDocuments = !showCommercialDocuments"
                class="w-full flex items-center justify-between px-4 py-3 bg-surface-container-low text-left hover:bg-surface-container transition-colors">
                <span class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Commercial Documents ({{ commercialDocuments.length }})</span>
                <span class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform" :class="showCommercialDocuments ? 'rotate-180' : ''">expand_more</span>
            </button>
            <div v-if="showCommercialDocuments" class="px-4 py-3 flex flex-col gap-1.5 bg-surface-bright">
                <DocumentRow v-for="doc in commercialDocuments" :key="doc.id" :document="doc">
                    <template v-if="doc.category" #trailing>
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary shrink-0">{{ doc.category }}</span>
                    </template>
                </DocumentRow>
            </div>
        </div>

        <!-- Approval steps -->
        <div class="flex flex-col gap-3">
            <div
                v-for="approval in sortedApprovals"
                :key="approval.id"
                class="rounded-lg border p-4 transition-all"
                :class="{
                    'border-emerald-200 bg-emerald-50/50': approvalStepState(approval) === 'approved',
                    'border-red-200 bg-red-50/50': approvalStepState(approval) === 'rejected',
                    'border-indigo-200 bg-indigo-50/40': approvalStepState(approval) === 'active',
                    'border-outline/10 bg-surface-container-low/50 opacity-60': approvalStepState(approval) === 'waiting',
                }"
            >
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0"
                            :class="{
                                'bg-emerald-500 text-white': approvalStepState(approval) === 'approved',
                                'bg-red-500 text-white': approvalStepState(approval) === 'rejected',
                                'bg-indigo-500 text-white': approvalStepState(approval) === 'active',
                                'bg-outline/30 text-on-surface-variant': approvalStepState(approval) === 'waiting',
                            }"
                        >
                            <span v-if="approvalStepState(approval) === 'approved'" class="material-symbols-outlined text-[12px]">check</span>
                            <span v-else-if="approvalStepState(approval) === 'rejected'" class="material-symbols-outlined text-[12px]">close</span>
                            <span v-else>{{ approval.step }}</span>
                        </span>
                        <div>
                            <p class="text-xs font-bold text-on-surface">Step {{ approval.step }}</p>
                            <p class="text-[10px] text-on-surface-variant">{{ approval.role?.name?.replace(/_/g, ' ') ?? '—' }}</p>
                        </div>
                    </div>
                    <span class="text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full"
                        :class="{
                            'bg-emerald-100 text-emerald-700': approvalStepState(approval) === 'approved',
                            'bg-red-100 text-red-700': approvalStepState(approval) === 'rejected',
                            'bg-indigo-100 text-indigo-700': approvalStepState(approval) === 'active',
                            'bg-outline/10 text-on-surface-variant': approvalStepState(approval) === 'waiting',
                        }"
                    >
                        {{ approvalStepState(approval) === 'waiting' ? 'Locked' : approval.status }}
                    </span>
                </div>

                <p v-if="approval.remarks && approval.status !== 'pending'" class="text-xs text-on-surface-variant italic mb-2 mt-1 pl-7">
                    "{{ approval.remarks }}"
                </p>
                <p v-if="approval.decided_at" class="text-[10px] text-on-surface-variant/60 pl-7">
                    {{ formatDate(approval.decided_at) }}
                    <span v-if="approval.decided_by?.full_name"> · by {{ approval.decided_by.full_name }}</span>
                </p>

                <!-- Action form: current user has the required role -->
                <div v-if="approvalStepState(approval) === 'active' && approval.role_id === authUserRoleId" class="mt-4 pt-4 border-t border-indigo-100">
                    <form @submit.prevent="submitDecision(approval)" class="flex flex-col gap-3">
                        <textarea v-model="decisionForms[approval.id].remarks" rows="2" placeholder="Remarks (optional)…"
                            class="w-full bg-white border border-outline/40 rounded-lg px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none" />
                        <div class="flex gap-2">
                            <button type="submit" @click="decisionForms[approval.id].decision = 'approved'" :disabled="decisionForms[approval.id].processing"
                                class="flex-1 flex items-center justify-center gap-1.5 bg-emerald-500 text-white font-semibold text-xs px-3 py-2 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60">
                                <span class="material-symbols-outlined text-[14px]">thumb_up</span>
                                Approve
                            </button>
                            <button type="submit" @click="decisionForms[approval.id].decision = 'rejected'" :disabled="decisionForms[approval.id].processing"
                                class="flex-1 flex items-center justify-center gap-1.5 bg-red-500 text-white font-semibold text-xs px-3 py-2 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60">
                                <span class="material-symbols-outlined text-[14px]">thumb_down</span>
                                Reject
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Active step but user doesn't have the required role -->
                <p v-else-if="approvalStepState(approval) === 'active' && approval.role_id !== authUserRoleId"
                    class="text-[10px] text-indigo-600 font-medium mt-3 pl-7 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px]">schedule</span>
                    Awaiting decision from {{ approval.role?.name?.replace(/_/g, ' ') ?? 'approver' }}
                </p>
            </div>
        </div>
    </div>
</template>
