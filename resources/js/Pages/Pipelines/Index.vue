<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import { statusStyle, formatDate, getInitials } from '@/utils/pipeline.js'

defineOptions({ layout: AppLayout })

const props = defineProps({
    pipelines: Object,
    pendingActions: Array,
    stats: Object,
    filters: Object,
    statuses: Array,
    saqSpecialists: Array,
    can: Object,
})

const isSuperAdmin = computed(() => usePage().props.auth?.isSuperAdmin ?? false)

const form = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    tab: props.filters.tab ?? 'all',
})

const assignSaqModalOpen = ref(false)
const selectedPipeline = ref(null)
const assignSaqForm = useForm({
    assigned_saq_id: '',
})
const indexRefreshOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['pipelines', 'stats', 'flash'],
}

let searchTimeout = null

watch(() => form.search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 350)
})

watch(() => form.status, applyFilters)

function applyFilters() {
    router.get('/pipelines', {
        search: form.search || undefined,
        status: form.status || undefined,
        tab: form.tab === 'pending' ? 'pending' : undefined,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    form.search = ''
    form.status = ''
}

function switchTab(tab) {
    form.tab = tab
    applyFilters()
}

function goToPage(url) {
    if (url) {
        router.get(url, {}, { preserveState: true })
    }
}

function openAssignSaqModal(pipeline) {
    selectedPipeline.value = pipeline
    assignSaqForm.clearErrors()
    assignSaqForm.assigned_saq_id = pipeline.assigned_saq?.id ?? ''
    assignSaqModalOpen.value = true
}

function resetAssignSaqModal() {
    assignSaqModalOpen.value = false
    selectedPipeline.value = null
    assignSaqForm.reset()
    assignSaqForm.clearErrors()
}

function closeAssignSaqModal() {
    if (!assignSaqForm.processing) {
        resetAssignSaqModal()
    }
}

function assignSaq() {
    if (!selectedPipeline.value) {
        return
    }

    assignSaqForm.patch(`/pipelines/${selectedPipeline.value.id}/assign-saq`, {
        ...indexRefreshOptions,
        onSuccess: resetAssignSaqModal,
    })
}

const statCards = [
    { key: 'total',            label: 'Total Leads',       icon: 'account_tree',    accent: 'text-on-surface' },
    { key: 'in_acquisition',   label: 'In Acquisition',    icon: 'play_circle',     accent: 'text-blue-600' },
    { key: 'pending_approval', label: 'Pending Approval',  icon: 'pending_actions', accent: 'text-amber-600' },
    { key: 'active_sites',     label: 'Completed Leads',         icon: 'location_on',     accent: 'text-green-600' },
]
</script>

<template>
    <Head title="Pipelines" />
    <!-- Page header -->
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-headline text-2xl sm:text-3xl font-extrabold text-on-surface tracking-tight">Pipelines</h1>
            <p class="text-on-surface-variant text-sm mt-1 max-w-xl">
                Overview of all active and historical site acquisition records.
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a v-if="isSuperAdmin" href="/pipelines/import" class="flex items-center gap-2 border border-outline/40 text-on-surface-variant font-semibold text-sm px-4 sm:px-5 py-2.5 rounded-lg hover:bg-surface-container active:scale-[0.99] transition-all">
                <span class="material-symbols-outlined text-[18px]">upload_file</span>
                <span class="hidden sm:inline">Import</span>
            </a>
            <a v-if="can?.create" href="/pipelines/create" class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-4 sm:px-5 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all shadow-sm">
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span class="hidden sm:inline">Add New Site</span>
                <span class="sm:hidden">New</span>
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-outline/10 rounded-xl overflow-hidden border border-outline/10">
        <div v-for="card in statCards" :key="card.key" class="bg-surface-bright p-6">
            <div class="flex items-center justify-between mb-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ card.label }}</p>
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant/40">{{ card.icon }}</span>
            </div>
            <p class="font-headline text-3xl font-bold" :class="card.accent">
                {{ stats[card.key].toLocaleString() }}
            </p>
        </div>
    </div>

    <!-- Filters -->
    <div class="border-b border-outline/15">
        <div class="flex gap-0">
            <button
                v-for="tab in [{ key: 'all', label: 'All Leads', icon: 'account_tree' }, { key: 'pending', label: 'Pending Actions', icon: 'pending_actions' }]"
                :key="tab.key"
                type="button"
                @click="switchTab(tab.key)"
                class="flex items-center gap-2 px-5 py-3 text-sm font-semibold border-b-2 transition-colors"
                :class="form.tab === tab.key ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface'"
            >
                <span class="material-symbols-outlined text-[18px]">{{ tab.icon }}</span>
                {{ tab.label }}
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
        <!-- Search -->
        <div class="relative flex-1 min-w-56 max-w-sm">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
            <input
                v-model="form.search"
                type="text"
                placeholder="Search by name or ID…"
                class="w-full bg-surface-container-low border border-outline/40 rounded-lg pl-9 pr-4 py-2 text-sm text-on-surface placeholder:text-on-surface-variant/60 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
            />
        </div>

        <!-- Status filter -->
        <div class="flex items-center gap-2 bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2">
            <span class="text-xs font-bold text-on-surface-variant">Status:</span>
            <select
                v-model="form.status"
                class="bg-transparent border-none text-xs font-semibold p-0 focus:ring-0 text-on-surface outline-none cursor-pointer"
            >
                <option value="">All Statuses</option>
                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
            </select>
        </div>

        <div class="ml-auto flex items-center gap-3">
            <button
                v-if="form.search || form.status"
                @click="resetFilters"
                class="text-xs font-bold text-primary hover:underline transition-colors"
            >
                Reset
            </button>
        </div>
    </div>

    <!-- Pending actions -->
    <div v-if="form.tab === 'pending'" class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left">
                <thead>
                    <tr class="border-b border-outline/10 bg-surface-container-low">
                        <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site</th>
                        <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Stage</th>
                        <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pending Action</th>
                        <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Owner</th>
                        <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline/5">
                    <tr v-if="pendingActions.length === 0">
                        <td colspan="5" class="px-6 py-16 text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[40px] block mx-auto mb-2 opacity-30">task_alt</span>
                            <p class="text-sm font-medium">No pending actions</p>
                        </td>
                    </tr>
                    <tr v-for="row in pendingActions" :key="`${row.pipeline.id}-${row.action_key}`" class="hover:bg-surface-container-low transition-colors">
                        <!-- Whole site cell links to the pipeline; the Action column keeps its own controls. -->
                        <td class="px-6 py-4">
                            <Link :href="`/pipelines/${row.pipeline.id}`" class="block group">
                                <p class="text-sm font-semibold text-on-surface group-hover:text-primary group-hover:underline transition-colors">{{ row.pipeline.name }}</p>
                                <p class="text-[10px] font-mono text-primary mt-0.5">{{ row.pipeline.pipeline_id }}</p>
                            </Link>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold" :class="statusStyle(row.stage).badge">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(row.stage).dot"></span>
                                    {{ row.stage }}
                                </span>
                                <span
                                    v-if="row.pipeline.stage_context"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold border"
                                    :class="row.pipeline.stage_context.classes"
                                    :title="`${row.pipeline.stage_context.stage_age_label} in ${row.stage}`"
                                >
                                    <span class="material-symbols-outlined text-[11px]">hourglass_top</span>
                                    {{ row.pipeline.stage_context.stage_age_days }}d in stage
                                </span>
                                <span
                                    v-if="row.pipeline.aging_badge"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold border"
                                    :class="row.pipeline.aging_badge.classes"
                                    :title="`No activity for ${row.pipeline.aging_badge.label}`"
                                >
                                    <span class="material-symbols-outlined text-[11px]">schedule</span>
                                    {{ row.pipeline.aging_badge.label }} no activity
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-on-surface">{{ row.action_label }}</td>
                        <td class="px-6 py-4">
                            <p class="text-sm text-on-surface">{{ row.owner_label ?? row.stage_context?.owner_label }}</p>
                            <p v-if="row.owner_type" class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mt-0.5">
                                {{ row.owner_type.replace(/_/g, ' ') }}
                            </p>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a :href="`/pipelines/${row.pipeline.id}`" class="p-1.5 text-on-surface-variant hover:text-on-surface hover:bg-surface-container rounded transition-all" title="View">
                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Table -->
    <div v-else class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left">
            <thead>
                <tr class="border-b border-outline/10 bg-surface-container-low">
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline ID</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Project Name</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Registered By</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Assigned SAQ</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Assigned Surveyor</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Survey Visit Date</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Endorsement Date</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Status</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline/5">
                <tr v-if="pipelines.data.length === 0">
                    <td colspan="9" class="px-6 py-16 text-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-[40px] block mx-auto mb-2 opacity-30">account_tree</span>
                        <p class="text-sm font-medium">No leads found</p>
                        <p v-if="form.search || form.status" class="text-xs mt-1">Try adjusting your filters.</p>
                    </td>
                </tr>
                <tr
                    v-for="pipeline in pipelines.data"
                    :key="pipeline.id"
                    class="hover:bg-surface-container-low transition-colors"
                >
                    <td class="px-6 py-4 text-sm font-mono font-semibold text-primary">
                        <Link :href="`/pipelines/${pipeline.id}`" class="hover:underline">{{ pipeline.pipeline_id }}</Link>
                    </td>
                    <td class="px-6 py-4">
                        <Link :href="`/pipelines/${pipeline.id}`" class="text-sm font-semibold text-on-surface hover:text-primary hover:underline">{{ pipeline.name }}</Link>
                        <p v-if="pipeline.site_profile" class="text-[10px] text-on-surface-variant mt-0.5">
                            {{ pipeline.site_profile.name }}
                        </p>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center shrink-0 text-[10px] font-bold text-primary">
                                {{ getInitials(pipeline.project_manager?.full_name) }}
                            </div>
                            <span class="text-sm text-on-surface">{{ pipeline.project_manager?.full_name ?? '—' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-full bg-blue-50 flex items-center justify-center shrink-0 text-[10px] font-bold text-blue-700">
                                {{ getInitials(pipeline.assigned_saq?.full_name) }}
                            </div>
                            <span class="text-sm text-on-surface">{{ pipeline.assigned_saq?.full_name ?? 'Unassigned' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-full bg-emerald-50 flex items-center justify-center shrink-0 text-[10px] font-bold text-emerald-700">
                                {{ getInitials(pipeline.site_survey?.surveyor?.full_name) }}
                            </div>
                            <span class="text-sm text-on-surface">{{ pipeline.site_survey?.surveyor?.full_name ?? 'Unassigned' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-on-surface-variant">
                        {{ pipeline.site_survey?.survey_date ? formatDate(pipeline.site_survey.survey_date) : 'Unset' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-on-surface-variant">
                        {{ formatDate(pipeline.endorsement_date) }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold"
                                :class="statusStyle(pipeline.status).badge"
                            >
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(pipeline.status).dot"></span>
                                {{ pipeline.status }}
                            </span>
                            <span
                                v-if="pipeline.stage_context"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold border"
                                :class="pipeline.stage_context.classes"
                                :title="`${pipeline.stage_context.stage_age_label} in ${pipeline.status}`"
                            >
                                <span class="material-symbols-outlined text-[11px]">hourglass_top</span>
                                {{ pipeline.stage_context.stage_age_days }}d in stage
                            </span>
                            <span
                                v-if="pipeline.aging_badge"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold border"
                                :class="pipeline.aging_badge.classes"
                                :title="`No activity for ${pipeline.aging_badge.label}`"
                            >
                                <span class="material-symbols-outlined text-[11px]">schedule</span>
                                {{ pipeline.aging_badge.label }} no activity
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <button
                                v-if="can?.assignSaq && ['Prospect', 'Prospecting'].includes(pipeline.status)"
                                type="button"
                                class="p-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container rounded transition-all"
                                title="Assign SAQ Specialist"
                                @click="openAssignSaqModal(pipeline)"
                            >
                                <span class="material-symbols-outlined text-[18px]">person_add</span>
                            </button>
                            <a :href="`/pipelines/${pipeline.id}`" class="p-1.5 text-on-surface-variant hover:text-on-surface hover:bg-surface-container rounded transition-all" title="View">
                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                            </a>
                            <a :href="`/pipelines/${pipeline.id}`" class="p-1.5 text-on-surface-variant hover:text-on-surface hover:bg-surface-container rounded transition-all" title="Edit details">
                                <span class="material-symbols-outlined text-[18px]">edit_square</span>
                            </a>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        </div>

        <!-- Pagination -->
        <div v-if="pipelines.last_page > 1 || pipelines.data.length > 0" class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-outline/10 bg-surface-container-low">
            <p class="text-xs text-on-surface-variant">
                Showing {{ pipelines.from ?? 0 }}–{{ pipelines.to ?? 0 }} of {{ pipelines.total.toLocaleString() }} leads
            </p>
            <div class="flex items-center gap-1">
                <a
                    v-for="link in pipelines.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    @click.prevent="link.url && goToPage(link.url)"
                    class="px-3 py-1.5 text-xs rounded-lg transition-colors"
                    :class="link.active ? 'bg-primary text-white font-bold' : link.url ? 'text-on-surface-variant hover:bg-surface-variant' : 'text-on-surface-variant/30 cursor-default'"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="assignSaqModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="closeAssignSaqModal"
        >
            <div class="w-full max-w-md rounded-xl border border-outline/10 bg-surface-bright shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-outline/10 px-6 py-5">
                    <div>
                        <h2 class="font-headline text-lg font-bold text-on-surface">Assign SAQ Specialist</h2>
                        <p class="mt-1 text-xs text-on-surface-variant">Choose a user with Prospecting stage access.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded p-1 text-on-surface-variant hover:bg-surface-container hover:text-on-surface"
                        title="Close"
                        @click="closeAssignSaqModal"
                    >
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                <form v-if="selectedPipeline" class="space-y-5 px-6 py-5" @submit.prevent="assignSaq">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline ID</p>
                            <p class="mt-1 font-mono font-semibold text-primary">{{ selectedPipeline.pipeline_id }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Current SAQ</p>
                            <p class="mt-1 font-semibold text-on-surface">{{ selectedPipeline.assigned_saq?.full_name ?? 'Unassigned' }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline/site name</p>
                            <p class="mt-1 font-semibold text-on-surface">{{ selectedPipeline.name }}</p>
                            <p v-if="selectedPipeline.site_profile" class="text-xs text-on-surface-variant">{{ selectedPipeline.site_profile.name }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="assign-saq-specialist" class="mb-1.5 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                            SAQ Specialist
                        </label>
                        <select
                            id="assign-saq-specialist"
                            v-model="assignSaqForm.assigned_saq_id"
                            class="w-full rounded-lg border border-outline/40 bg-surface-container-low px-3 py-2 text-sm text-on-surface outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary"
                            :class="{ 'border-error focus:border-error focus:ring-error': assignSaqForm.errors.assigned_saq_id }"
                        >
                            <option value="">Select SAQ Specialist</option>
                            <option v-for="saq in saqSpecialists" :key="saq.id" :value="saq.id">{{ saq.full_name }}</option>
                        </select>
                        <p v-if="assignSaqForm.errors.assigned_saq_id" class="mt-1 text-xs font-medium text-error">
                            {{ assignSaqForm.errors.assigned_saq_id }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-outline/10 pt-4">
                        <button
                            type="button"
                            class="rounded-lg border border-outline/40 px-4 py-2 text-sm font-semibold text-on-surface-variant transition-colors hover:bg-surface-container"
                            @click="closeAssignSaqModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="assignSaqForm.processing || !assignSaqForm.assigned_saq_id"
                        >
                            {{ assignSaqForm.processing ? 'Saving…' : 'Save/Assign' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

</template>
