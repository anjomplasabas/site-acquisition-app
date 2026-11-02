<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, useForm } from '@inertiajs/vue3'
import { reactive, ref, watch } from 'vue'
import { statusStyle, getInitials, ageFrom } from '@/utils/pipeline.js'

defineOptions({ layout: AppLayout })

const props = defineProps({
    assignments: Object,
    designers: Array,
    surveyors: Array,
    pipelineStatuses: Array,
    filters: Object,
    can: Object,
    statusFilter: String,
})

const filterForm = reactive({
    search: props.filters?.search ?? '',
    pipeline_status: props.filters?.pipeline_status ?? '',
    surveyor_id: props.filters?.surveyor_id ?? '',
    design_plan_assignee_id: props.filters?.design_plan_assignee_id ?? '',
    survey_status: props.filters?.survey_status ?? '',
    design_plan_status: props.filters?.design_plan_status ?? '',
})

const hasActiveFilters = () => Object.values(filterForm).some(value => value !== '')

let searchTimeout = null

watch(() => filterForm.search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 350)
})

watch(() => [
    filterForm.pipeline_status,
    filterForm.surveyor_id,
    filterForm.design_plan_assignee_id,
    filterForm.survey_status,
    filterForm.design_plan_status,
], applyFilters)

function applyFilters() {
    router.get('/site-planning', {
        status: props.statusFilter === 'site_survey' ? 'site_survey' : undefined,
        search: filterForm.search || undefined,
        pipeline_status: filterForm.pipeline_status || undefined,
        surveyor_id: filterForm.surveyor_id || undefined,
        design_plan_assignee_id: filterForm.design_plan_assignee_id || undefined,
        survey_status: filterForm.survey_status || undefined,
        design_plan_status: filterForm.design_plan_status || undefined,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    filterForm.search = ''
    filterForm.pipeline_status = ''
    filterForm.surveyor_id = ''
    filterForm.design_plan_assignee_id = ''
    filterForm.survey_status = ''
    filterForm.design_plan_status = ''
}

function goToPage(url) {
    if (url) {
        router.get(url, {}, { preserveState: true })
    }
}

function setStatusFilter(filter) {
    router.get('/site-planning', filter === 'site_survey' ? { status: 'site_survey' } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function isPendingApproval(assignment) {
    return !!assignment.design_plan_completed_at && !assignment.design_plan_approved_at
}

function isApproved(assignment) {
    return !!assignment.design_plan_approved_at
}

function canAssignDesigner(assignment) {
    return !!props.can?.assignDesignPlan
        && assignment.site_planning_tasks_exists
        && !isPendingApproval(assignment)
        && !isApproved(assignment)
}

const assignDesignerModalOpen = ref(false)
const selectedAssignment = ref(null)
const assignDesignerForm = useForm({
    design_plan_assignee_id: '',
})
const indexRefreshOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['assignments'],
}

function openAssignDesignerModal(assignment) {
    selectedAssignment.value = assignment
    assignDesignerForm.clearErrors()
    assignDesignerForm.design_plan_assignee_id = assignment.design_plan_assignee?.id ?? ''
    assignDesignerModalOpen.value = true
}

function resetAssignDesignerModal() {
    assignDesignerModalOpen.value = false
    selectedAssignment.value = null
    assignDesignerForm.reset()
    assignDesignerForm.clearErrors()
}

function closeAssignDesignerModal() {
    if (!assignDesignerForm.processing) {
        resetAssignDesignerModal()
    }
}

function assignDesigner() {
    if (!selectedAssignment.value) {
        return
    }

    assignDesignerForm.patch(`/site-planning/${selectedAssignment.value.id}/design-plan/assign`, {
        ...indexRefreshOptions,
        onSuccess: resetAssignDesignerModal,
    })
}
</script>

<template>
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-orange-50 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-orange-600 text-[26px]">engineering</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-on-surface">Site Planning</h1>
                <p class="text-sm text-on-surface-variant mt-1">Sites awaiting surveyor assignment or currently under site survey.</p>
            </div>
        </div>
        <a
            href="/site-planning/reports/site-survey"
            class="inline-flex items-center gap-1.5 shrink-0 rounded-lg border border-outline/40 px-3 py-2 text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors"
        >
            <span class="material-symbols-outlined text-[16px]">download</span>
            Site Survey Report
        </a>
    </div>

    <div class="flex items-center gap-2">
        <div class="inline-flex rounded-lg border border-outline/30 bg-surface-container-low p-1">
            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
                :class="statusFilter !== 'site_survey' ? 'bg-surface-bright text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                @click="setStatusFilter('all')"
            >
                All Sites
            </button>
            <button
                type="button"
                class="rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
                :class="statusFilter === 'site_survey' ? 'bg-surface-bright text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                @click="setStatusFilter('site_survey')"
            >
                Currently in Site Survey
            </button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-56 max-w-sm">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
            <input
                v-model="filterForm.search"
                type="text"
                placeholder="Search by site name…"
                class="w-full bg-surface-container-low border border-outline/40 rounded-lg pl-9 pr-4 py-2 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
            />
        </div>

        <div class="flex items-center gap-2 bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2">
            <span class="text-xs font-bold text-on-surface-variant">Pipeline Status:</span>
            <select v-model="filterForm.pipeline_status" class="bg-transparent border-none text-xs font-semibold p-0 focus:ring-0 text-on-surface outline-none cursor-pointer">
                <option value="">All</option>
                <option v-for="status in pipelineStatuses" :key="status" :value="status">{{ status }}</option>
            </select>
        </div>

        <div class="flex items-center gap-2 bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2">
            <span class="text-xs font-bold text-on-surface-variant">Surveyor:</span>
            <select v-model="filterForm.surveyor_id" class="bg-transparent border-none text-xs font-semibold p-0 focus:ring-0 text-on-surface outline-none cursor-pointer">
                <option value="">All</option>
                <option v-for="surveyor in surveyors" :key="surveyor.id" :value="surveyor.id">{{ surveyor.full_name }}</option>
            </select>
        </div>

        <div class="flex items-center gap-2 bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2">
            <span class="text-xs font-bold text-on-surface-variant">Design Plan Assignee:</span>
            <select v-model="filterForm.design_plan_assignee_id" class="bg-transparent border-none text-xs font-semibold p-0 focus:ring-0 text-on-surface outline-none cursor-pointer">
                <option value="">All</option>
                <option v-for="designer in designers" :key="designer.id" :value="designer.id">{{ designer.full_name }}</option>
            </select>
        </div>

        <div class="flex items-center gap-2 bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2">
            <span class="text-xs font-bold text-on-surface-variant">Survey Status:</span>
            <select v-model="filterForm.survey_status" class="bg-transparent border-none text-xs font-semibold p-0 focus:ring-0 text-on-surface outline-none cursor-pointer">
                <option value="">All</option>
                <option value="completed">Survey Completed</option>
                <option value="pending">Survey Pending</option>
            </select>
        </div>

        <div class="flex items-center gap-2 bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2">
            <span class="text-xs font-bold text-on-surface-variant">Design Plan:</span>
            <select v-model="filterForm.design_plan_status" class="bg-transparent border-none text-xs font-semibold p-0 focus:ring-0 text-on-surface outline-none cursor-pointer">
                <option value="">All</option>
                <option value="for_approval">For Approval</option>
            </select>
        </div>

        <div class="ml-auto flex items-center gap-3">
            <button v-if="hasActiveFilters()" @click="resetFilters" class="text-xs font-bold text-primary hover:underline transition-colors">
                Reset
            </button>
        </div>
    </div>

    <div v-if="assignments.data.length === 0" class="bg-surface-bright rounded-2xl border border-outline/10 py-24 text-center">
        <span class="material-symbols-outlined text-[48px] block mx-auto mb-3 text-on-surface-variant/30">engineering</span>
        <p class="text-base font-semibold text-on-surface">{{ statusFilter === 'site_survey' ? 'No sites currently in Site Survey' : 'No sites yet' }}</p>
        <p class="text-sm text-on-surface-variant mt-1">Sites appear here once a pipeline reaches Site Survey Pending.</p>
    </div>

    <div v-else class="flex flex-col gap-3">
        <a
            v-for="assignment in assignments.data"
            :key="assignment.id"
            :href="`/site-planning/${assignment.id}`"
            class="group flex items-center gap-5 bg-surface-bright rounded-2xl border border-outline/10 px-6 py-5 hover:border-primary/30 hover:shadow-sm transition-all"
        >
            <div class="w-11 h-11 rounded-xl bg-orange-50 flex items-center justify-center shrink-0 text-sm font-bold text-orange-700">
                {{ getInitials(assignment.pipeline?.name) }}
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-base font-semibold text-on-surface truncate flex items-center gap-1.5">
                    {{ assignment.pipeline?.name }}
                    <span v-if="assignment.tower" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-50 text-orange-700 shrink-0">
                        <span class="material-symbols-outlined text-[12px]">domain</span>
                        {{ assignment.tower.name }}
                    </span>
                </p>
                <p v-if="assignment.pipeline?.site_profile" class="text-xs text-on-surface-variant mt-0.5 truncate">
                    {{ assignment.pipeline.site_profile.name }}
                </p>
            </div>

            <div class="hidden sm:flex flex-col items-start gap-1 w-40 shrink-0">
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Pipeline Status</span>
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold"
                    :class="statusStyle(assignment.pipeline?.status).badge"
                >
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(assignment.pipeline?.status).dot"></span>
                    {{ assignment.pipeline?.status }}
                </span>
            </div>

            <div class="hidden md:flex flex-col items-start gap-1 w-36 shrink-0">
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Surveyor</span>
                <span class="text-sm text-on-surface">{{ assignment.site_survey?.surveyor?.full_name ?? 'Unassigned' }}</span>
            </div>

            <div class="hidden lg:flex flex-col items-start gap-1.5 w-44 shrink-0">
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Design Plan Assignee</span>
                <div class="flex items-center gap-2 w-full">
                    <span class="text-sm text-on-surface truncate">{{ assignment.design_plan_assignee?.full_name ?? 'Unassigned' }}</span>
                    <span
                        v-if="assignment.design_plan_assignee"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary shrink-0"
                    >Assigned</span>
                    <button
                        v-if="canAssignDesigner(assignment)"
                        type="button"
                        class="p-1 text-on-surface-variant hover:text-primary hover:bg-surface-container rounded transition-all shrink-0"
                        title="Assign Design Plan Assignee"
                        @click.prevent.stop="openAssignDesignerModal(assignment)"
                    >
                        <span class="material-symbols-outlined text-[16px]">engineering</span>
                    </button>
                </div>
            </div>

            <div class="flex flex-col items-start gap-1 w-40 shrink-0">
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Survey</span>
                <span
                    v-if="assignment.site_survey?.survey_completed_at"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700"
                >
                    <span class="material-symbols-outlined text-[13px]">check_circle</span>
                    Survey Completed
                </span>
                <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-surface-container text-on-surface-variant">
                    Survey Pending
                </span>
                <span v-if="assignment.site_survey?.survey_completed_at" class="text-[10px] text-on-surface-variant/70">
                    {{ ageFrom(assignment.site_survey.survey_completed_at) }}
                </span>
            </div>

            <span class="material-symbols-outlined text-on-surface-variant/40 group-hover:text-primary transition-colors shrink-0">chevron_right</span>
        </a>
    </div>

    <div v-if="assignments.last_page > 1" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-on-surface-variant">
            Showing {{ assignments.from ?? 0 }}–{{ assignments.to ?? 0 }} of {{ assignments.total.toLocaleString() }} sites
        </p>
        <div class="flex items-center gap-1">
            <a
                v-for="link in assignments.links"
                :key="link.label"
                :href="link.url ?? '#'"
                @click.prevent="link.url && goToPage(link.url)"
                class="px-3 py-1.5 text-xs rounded-lg transition-colors"
                :class="link.active ? 'bg-primary text-white font-bold' : link.url ? 'text-on-surface-variant hover:bg-surface-variant' : 'text-on-surface-variant/30 cursor-default'"
                v-html="link.label"
            />
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="assignDesignerModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="closeAssignDesignerModal"
        >
            <div class="w-full max-w-md rounded-xl border border-outline/10 bg-surface-bright shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-outline/10 px-6 py-5">
                    <div>
                        <h2 class="font-headline text-lg font-bold text-on-surface">Assign Design Plan Assignee</h2>
                        <p class="mt-1 text-xs text-on-surface-variant">Choose a user who can work the Plans/Drawing checklist.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded p-1 text-on-surface-variant hover:bg-surface-container hover:text-on-surface"
                        title="Close"
                        @click="closeAssignDesignerModal"
                    >
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                <form v-if="selectedAssignment" class="space-y-5 px-6 py-5" @submit.prevent="assignDesigner">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline ID</p>
                            <p class="mt-1 font-mono font-semibold text-primary">{{ selectedAssignment.pipeline?.pipeline_id }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Current Assignee</p>
                            <p class="mt-1 font-semibold text-on-surface">{{ selectedAssignment.design_plan_assignee?.full_name ?? 'Unassigned' }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline/site name</p>
                            <p class="mt-1 font-semibold text-on-surface">{{ selectedAssignment.pipeline?.name }}</p>
                            <p v-if="selectedAssignment.pipeline?.site_profile" class="text-xs text-on-surface-variant">{{ selectedAssignment.pipeline.site_profile.name }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="assign-design-plan-assignee" class="mb-1.5 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                            Design Plan Assignee
                        </label>
                        <select
                            id="assign-design-plan-assignee"
                            v-model="assignDesignerForm.design_plan_assignee_id"
                            class="w-full rounded-lg border border-outline/40 bg-surface-container-low px-3 py-2 text-sm text-on-surface outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary"
                            :class="{ 'border-error focus:border-error focus:ring-error': assignDesignerForm.errors.design_plan_assignee_id }"
                        >
                            <option value="">Select a designer</option>
                            <option v-for="designer in designers" :key="designer.id" :value="designer.id">{{ designer.full_name }}</option>
                        </select>
                        <p v-if="assignDesignerForm.errors.design_plan_assignee_id" class="mt-1 text-xs font-medium text-error">
                            {{ assignDesignerForm.errors.design_plan_assignee_id }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-outline/10 pt-4">
                        <button
                            type="button"
                            class="rounded-lg border border-outline/40 px-4 py-2 text-sm font-semibold text-on-surface-variant transition-colors hover:bg-surface-container"
                            @click="closeAssignDesignerModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="assignDesignerForm.processing || !assignDesignerForm.design_plan_assignee_id"
                        >
                            {{ assignDesignerForm.processing ? 'Saving…' : 'Save/Assign' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
