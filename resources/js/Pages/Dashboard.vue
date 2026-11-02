<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { agingBadge, formatDateTime, getInitials, statusStyle } from '@/utils/pipeline.js'

defineOptions({ layout: AppLayout })

defineProps({
    stats: Object,
    activeProjects: Array,
    recentPipelines: Array,
    statusDistribution: Array,
    attentionItems: Array,
    agingOverview: Array,
})

function statusAgeLabel(value) {
    return agingBadge(value)?.label ?? '0d'
}

function progressBarColor(pct) {
    if (pct === 100) return 'bg-emerald-500'
    if (pct >= 60) return 'bg-primary'
    return 'bg-amber-400'
}
</script>

<template>
    <Head title="Dashboard" />

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="font-headline text-2xl sm:text-3xl font-bold text-on-surface tracking-tight">Operations Dashboard</h1>
            <p class="text-on-surface-variant text-sm mt-1">Acquisition pipeline and project execution at a glance.</p>
        </div>
        <div class="flex items-center gap-2">
            <Link
                href="/projects"
                class="flex items-center gap-2 bg-surface-container-high text-on-surface font-bold text-sm px-4 py-2.5 rounded-lg hover:bg-surface-container transition-all border border-outline/20"
            >
                <span class="material-symbols-outlined text-[17px]">folder_special</span>
                Projects
            </Link>
            <Link
                href="/pipelines"
                class="flex items-center gap-2 bg-primary text-white font-bold text-sm px-4 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all"
            >
                <span class="material-symbols-outlined text-[17px]">account_tree</span>
                Pipelines
            </Link>
        </div>
    </div>

    <!-- KPI Cards: two labelled groups -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <!-- Acquisition Pipeline group -->
        <div class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden">
            <div class="px-4 py-2.5 bg-surface-container-low border-b border-outline/10 flex items-center gap-2">
                <span class="material-symbols-outlined text-[14px] text-on-surface-variant">account_tree</span>
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Acquisition Pipeline</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-outline/10">
                <div class="px-5 py-5">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">In Acquisition</p>
                    <p class="font-headline text-3xl font-bold text-blue-600">{{ (stats?.in_acquisition ?? 0).toLocaleString() }}</p>
                    <p class="text-[10px] text-on-surface-variant mt-1">Active leads</p>
                </div>
                <Link href="/site-planning?design_plan_status=for_approval" class="px-5 py-5 hover:bg-surface-container-low transition-colors block">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Design Plan for Approval</p>
                    <p class="font-headline text-3xl font-bold text-purple-600">{{ (stats?.design_plan_for_approval ?? 0).toLocaleString() }}</p>
                    <p class="text-[10px] text-on-surface-variant mt-1">Completed, awaiting approval</p>
                </Link>
                <div class="px-5 py-5">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Pending Approval</p>
                    <p class="font-headline text-3xl font-bold text-amber-600">{{ (stats?.pending_approval ?? 0).toLocaleString() }}</p>
                    <p class="text-[10px] text-on-surface-variant mt-1">Awaiting sign-off</p>
                </div>
                <div class="px-5 py-5">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Ready for Service</p>
                    <p class="font-headline text-3xl font-bold text-emerald-600">{{ (stats?.ready_for_service ?? 0).toLocaleString() }}</p>
                    <p class="text-[10px] text-on-surface-variant mt-1">Acquired sites</p>
                </div>
            </div>
        </div>

        <!-- Active Projects group -->
        <div class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden">
            <div class="px-4 py-2.5 bg-surface-container-low border-b border-outline/10 flex items-center gap-2">
                <span class="material-symbols-outlined text-[14px] text-on-surface-variant">folder_special</span>
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Project Execution</span>
            </div>
            <div class="px-5 py-5">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-1">Active Projects</p>
                <p class="font-headline text-3xl font-bold text-on-surface">{{ (stats?.active_projects ?? 0).toLocaleString() }}</p>
                <p class="text-[10px] text-on-surface-variant mt-1">In execution</p>
            </div>
        </div>
    </div>

    <!-- Main content: 3-col on large, stacked otherwise -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LEFT: Active Projects (spans 2 cols) -->
        <div class="lg:col-span-2 flex flex-col gap-6">

            <!-- Active Projects health grid -->
            <div class="bg-surface-bright rounded-xl border border-outline/10">
                <div class="flex items-center justify-between px-6 py-4 border-b border-outline/10">
                    <div>
                        <h2 class="font-headline font-bold text-base text-on-surface">Active Projects</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Stage progress for ongoing deployments.</p>
                    </div>
                    <Link href="/projects" class="text-xs font-bold text-primary hover:underline">View all</Link>
                </div>

                <div v-if="!activeProjects?.length" class="px-6 py-12 text-center text-on-surface-variant">
                    <span class="material-symbols-outlined text-[40px] opacity-30 block mx-auto mb-2">folder_special</span>
                    <p class="text-sm font-medium">No active projects yet</p>
                    <p class="text-xs mt-1">Projects are created when a pipeline is approved.</p>
                </div>

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-outline/10">
                    <Link
                        v-for="project in activeProjects"
                        :key="project.id"
                        :href="`/projects/${project.id}`"
                        class="bg-surface-bright p-5 hover:bg-surface-container-low transition-colors flex flex-col gap-3"
                    >
                        <!-- Project name + pipeline ID -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-on-surface truncate leading-tight">{{ project.name }}</p>
                                <p class="text-[11px] font-mono text-on-surface-variant mt-0.5">{{ project.pipeline_id }}</p>
                            </div>
                            <span v-if="project.progress_pct === 100"
                                class="shrink-0 inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="material-symbols-outlined text-[11px]">check_circle</span>
                                Complete
                            </span>
                            <span v-else
                                class="shrink-0 inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20">
                                {{ project.current_stage }}
                            </span>
                        </div>

                        <!-- Progress bar -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10px] font-bold text-on-surface-variant">
                                    {{ project.done_stages }}/{{ project.total_stages }} stages
                                </span>
                                <span class="text-[11px] font-bold" :class="project.progress_pct === 100 ? 'text-emerald-700' : 'text-on-surface'">{{ project.progress_pct }}%</span>
                            </div>
                            <div class="h-1.5 bg-outline/15 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="progressBarColor(project.progress_pct)"
                                    :style="{ width: `${project.progress_pct}%` }"
                                />
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>

        <!-- RIGHT: Pipeline Funnel + Needs Attention -->
        <div class="flex flex-col gap-6">

            <!-- Pipeline Status Distribution -->
            <div class="bg-surface-bright rounded-xl border border-outline/10">
                <div class="px-6 py-4 border-b border-outline/10">
                    <h2 class="font-headline font-bold text-base text-on-surface">Pipeline Funnel</h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Leads by acquisition stage.</p>
                </div>
                <div class="p-5 flex flex-col gap-3">
                    <div v-if="!statusDistribution?.length" class="py-10 text-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-[40px] opacity-30">bar_chart</span>
                        <p class="text-sm font-medium mt-2">No data yet</p>
                    </div>
                    <div v-for="item in statusDistribution" :key="item.status" class="flex flex-col gap-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-on-surface min-w-0 truncate">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(item.status).dot"></span>
                                {{ item.status }}
                            </span>
                            <span class="text-xs font-bold text-on-surface-variant shrink-0">{{ item.count }}</span>
                        </div>
                        <div class="h-1.5 bg-outline/15 rounded-full overflow-hidden">
                            <div class="h-full bg-primary rounded-full" :style="{ width: item.percentage + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Needs Attention -->
            <div class="bg-surface-bright rounded-xl border border-outline/10">
                <div class="px-6 py-4 border-b border-outline/10">
                    <h2 class="font-headline font-bold text-base text-on-surface">Needs Attention</h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Leads stalled for 7+ days.</p>
                </div>
                <div class="divide-y divide-outline/10">
                    <div v-if="!attentionItems?.length" class="px-6 py-10 text-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-[40px] opacity-30">check_circle</span>
                        <p class="text-sm font-medium mt-2">No stale leads</p>
                    </div>
                    <Link
                        v-for="pipeline in attentionItems"
                        :key="pipeline.id"
                        :href="`/pipelines/${pipeline.id}`"
                        class="px-5 py-3 flex items-start justify-between gap-3 hover:bg-surface-container-low transition-colors"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-on-surface truncate">{{ pipeline.name }}</p>
                            <p class="text-xs text-on-surface-variant mt-0.5 truncate">
                                {{ pipeline.pipeline_id }} · {{ pipeline.assigned_saq ?? 'Unassigned' }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                :class="agingBadge(pipeline.status_changed_at)?.classes"
                            >
                                {{ statusAgeLabel(pipeline.status_changed_at) }}
                            </span>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mt-0.5">{{ pipeline.status }}</p>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </div>

    <!-- Site Aging & Ownership -->
    <div class="bg-surface-bright rounded-xl border border-outline/10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-outline/10">
            <div>
                <h2 class="font-headline font-bold text-base text-on-surface">Site Aging & Ownership</h2>
                <p class="text-xs text-on-surface-variant mt-0.5">Inactivity, current stage age, and responsible owner.</p>
            </div>
            <Link href="/pipelines?tab=pending" class="text-xs font-bold text-primary hover:underline">Pending actions</Link>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px]">
                <thead>
                    <tr class="border-b border-outline/10">
                        <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Status</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site Age</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Status Age</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Current Owner</th>
                        <th class="px-6 py-3 text-right text-[10px] font-bold uppercase tracking-widest text-on-surface-variant"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!agingOverview?.length">
                        <td colspan="6" class="px-6 py-12 text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[40px] block mx-auto mb-2 opacity-30">hourglass_empty</span>
                            <p class="text-sm font-medium">No active site aging data</p>
                        </td>
                    </tr>
                    <tr
                        v-for="pipeline in agingOverview"
                        :key="pipeline.id"
                        class="border-b border-outline/5 hover:bg-surface-container-low transition-colors"
                    >
                        <td class="px-6 py-4">
                            <p class="font-semibold text-sm text-on-surface truncate">{{ pipeline.name }}</p>
                            <p class="text-xs text-on-surface-variant mt-0.5 font-mono">{{ pipeline.pipeline_id }}</p>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold px-2.5 py-1 rounded-full" :class="statusStyle(pipeline.status).badge">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(pipeline.status).dot"></span>
                                {{ pipeline.status }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <span
                                v-if="pipeline.lead_age"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                :class="pipeline.lead_age.classes"
                            >
                                <span class="material-symbols-outlined text-[12px]">schedule</span>
                                {{ pipeline.lead_age.label }} no activity
                            </span>
                            <span v-else class="text-sm text-on-surface-variant">—</span>
                        </td>
                        <td class="px-4 py-4">
                            <span
                                v-if="pipeline.stage_context"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                :class="pipeline.stage_context.classes"
                                :title="formatDateTime(pipeline.stage_context.stage_since)"
                            >
                                <span class="material-symbols-outlined text-[12px]">hourglass_top</span>
                                {{ pipeline.stage_context.stage_age_days }}d in stage
                            </span>
                            <span v-else class="text-sm text-on-surface-variant">—</span>
                        </td>
                        <td class="px-4 py-4">
                            <p class="text-sm font-semibold text-on-surface">{{ pipeline.current_owner }}</p>
                            <p v-if="pipeline.stage_context?.owner_type" class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mt-0.5">
                                {{ pipeline.stage_context.owner_type.replace(/_/g, ' ') }}
                            </p>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="`/pipelines/${pipeline.id}`"
                                class="inline-flex items-center justify-center p-1.5 text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded transition-all"
                                title="View">
                                <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Pipeline Activity -->
    <div class="bg-surface-bright rounded-xl border border-outline/10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-outline/10">
            <div>
                <h2 class="font-headline font-bold text-base text-on-surface">Recent Pipeline Activity</h2>
                <p class="text-xs text-on-surface-variant mt-0.5">Sorted by latest status change.</p>
            </div>
            <Link href="/pipelines" class="text-xs font-bold text-primary hover:underline">View all</Link>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px]">
                <thead>
                    <tr class="border-b border-outline/10">
                        <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Assigned SAQ</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Status</th>
                        <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Status Age</th>
                        <th class="px-6 py-3 text-right text-[10px] font-bold uppercase tracking-widest text-on-surface-variant"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!recentPipelines?.length">
                        <td colspan="5" class="px-6 py-12 text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[40px] block mx-auto mb-2 opacity-30">account_tree</span>
                            <p class="text-sm font-medium">No pipeline activity yet</p>
                        </td>
                    </tr>
                    <tr
                        v-for="pipeline in recentPipelines"
                        :key="pipeline.id"
                        class="border-b border-outline/5 hover:bg-surface-container-low transition-colors"
                    >
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-surface-variant rounded-lg flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-on-surface-variant text-[16px]">domain</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-sm text-on-surface truncate">{{ pipeline.name }}</p>
                                    <p class="text-xs text-on-surface-variant mt-0.5 truncate">
                                        {{ pipeline.pipeline_id }}
                                        <span v-if="pipeline.lead_profile"> · {{ pipeline.lead_profile }}</span>
                                        <span v-if="pipeline.location"> · {{ pipeline.location }}</span>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 bg-primary/10 rounded-full flex items-center justify-center text-[10px] font-bold text-primary shrink-0">
                                    {{ getInitials(pipeline.assigned_saq) }}
                                </div>
                                <span class="text-sm font-medium text-on-surface truncate">{{ pipeline.assigned_saq ?? 'Unassigned' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold px-2.5 py-1 rounded-full" :class="statusStyle(pipeline.status).badge">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(pipeline.status).dot"></span>
                                {{ pipeline.status }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                :class="agingBadge(pipeline.status_changed_at)?.classes ?? 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                                :title="formatDateTime(pipeline.status_changed_at)"
                            >
                                <span class="material-symbols-outlined text-[12px]">schedule</span>
                                {{ statusAgeLabel(pipeline.status_changed_at) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="`/pipelines/${pipeline.id}`"
                                class="inline-flex items-center justify-center p-1.5 text-on-surface-variant hover:text-primary hover:bg-primary/5 rounded transition-all"
                                title="View">
                                <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
