<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { statusStyle, formatDate, getInitials, ageFrom } from '@/utils/pipeline.js'

defineOptions({ layout: AppLayout })

defineProps({
    assignments: Object,
})

function goToPage(url) {
    if (url) {
        router.get(url, {}, { preserveState: true })
    }
}
</script>

<template>
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-blue-600 text-[26px]">lan</span>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-on-surface">Network</h1>
            <p class="text-sm text-on-surface-variant mt-1">Sites currently or previously under network review.</p>
        </div>
    </div>

    <div v-if="assignments.data.length === 0" class="bg-surface-bright rounded-2xl border border-outline/10 py-24 text-center">
        <span class="material-symbols-outlined text-[48px] block mx-auto mb-3 text-on-surface-variant/30">lan</span>
        <p class="text-base font-semibold text-on-surface">No sites assigned yet</p>
        <p class="text-sm text-on-surface-variant mt-1">Sites appear here once a pipeline reaches Site Survey.</p>
    </div>

    <div v-else class="flex flex-col gap-3">
        <a
            v-for="assignment in assignments.data"
            :key="assignment.id"
            :href="`/network/${assignment.id}`"
            class="group flex items-center gap-5 bg-surface-bright rounded-2xl border border-outline/10 px-6 py-5 hover:border-primary/30 hover:shadow-sm transition-all"
        >
            <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0 text-sm font-bold text-blue-700">
                {{ getInitials(assignment.pipeline?.name) }}
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-base font-semibold text-on-surface truncate">{{ assignment.pipeline?.name }}</p>
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

            <div class="hidden md:flex flex-col items-start gap-1 w-32 shrink-0">
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Assigned</span>
                <span class="text-sm text-on-surface">{{ formatDate(assignment.assigned_at) }}</span>
                <span class="text-[10px] text-on-surface-variant/70">{{ ageFrom(assignment.assigned_at) }}</span>
            </div>

            <div class="flex flex-col items-start gap-1 w-40 shrink-0">
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Feasibility</span>
                <span
                    v-if="assignment.site_survey?.network_feasibility_completed_at"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700"
                >
                    <span class="material-symbols-outlined text-[13px]">check_circle</span>
                    Feasibility Completed
                </span>
                <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-surface-container text-on-surface-variant">
                    Feasibility Pending
                </span>
                <span v-if="assignment.site_survey?.network_feasibility_completed_at" class="text-[10px] text-on-surface-variant/70">
                    {{ ageFrom(assignment.site_survey.network_feasibility_completed_at) }}
                </span>
            </div>

            <!-- JO checklist progress — a separate, much later lifecycle stage than feasibility
                 above: only exists once a Project has been created and tasks seeded. -->
            <div v-if="assignment.jo_progress" class="hidden lg:flex flex-col items-start gap-1 w-32 shrink-0">
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Task Checklist</span>
                <span
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold"
                    :class="assignment.jo_progress.done === assignment.jo_progress.total
                        ? 'bg-emerald-50 text-emerald-700'
                        : assignment.jo_progress.done > 0
                            ? 'bg-amber-50 text-amber-700'
                            : 'bg-surface-container text-on-surface-variant'"
                >{{ assignment.jo_progress.done }}/{{ assignment.jo_progress.total }} done</span>
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
</template>
