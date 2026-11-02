<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    projects: Object,
    filters: Object,
    statuses: Array,
})

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

let timer = null
watch([search, status], ([s, st]) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get('/projects', { search: s, status: st }, { preserveState: true, replace: true })
    }, 300)
})

function statusStyle(s) {
    return {
        Active: 'bg-emerald-100 text-emerald-800',
        'On Hold': 'bg-amber-100 text-amber-800',
        Completed: 'bg-primary/10 text-primary',
        Cancelled: 'bg-error/10 text-error',
    }[s] ?? 'bg-surface-container text-on-surface-variant'
}

function progress(project) {
    const done = project.stages.filter(s => s.status === 'Completed').length
    return { done, total: project.stages.length }
}
</script>

<template>
    <Head title="Projects" />

    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">Projects</h1>
            <p class="text-on-surface-variant text-sm mt-1">Track post-approval deployment progress.</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-3">
        <div class="relative flex-1 max-w-sm">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span>
            <input
                v-model="search"
                type="text"
                placeholder="Search pipeline or site..."
                class="w-full pl-9 pr-4 py-2 text-sm bg-surface-bright border border-outline/30 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary/50"
            />
        </div>
        <select
            v-model="status"
            class="text-sm bg-surface-bright border border-outline/30 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-primary/50"
        >
            <option value="">All Statuses</option>
            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
        </select>
    </div>

    <!-- Table -->
    <div class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden">
        <div v-if="!projects.data.length" class="px-6 py-16 text-center text-sm text-on-surface-variant">
            No projects found.
        </div>

        <table v-else class="w-full text-left">
            <thead>
                <tr class="border-b border-outline/10 bg-surface-container-low">
                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline / Site</th>
                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">PM</th>
                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Progress</th>
                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Status</th>
                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline/5">
                <tr
                    v-for="project in projects.data"
                    :key="project.id"
                    @click="router.visit(`/projects/${project.id}`)"
                    class="hover:bg-surface-container-low/40 transition-colors cursor-pointer"
                >
                    <td class="px-5 py-3">
                        <p class="text-sm font-semibold text-on-surface flex items-center gap-1.5">
                            {{ project.pipeline.name }}
                            <span v-if="project.tower" class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-orange-50 text-orange-700">
                                {{ project.tower.name }}
                            </span>
                        </p>
                        <p class="text-xs text-on-surface-variant mt-0.5">
                            {{ [project.pipeline.site_profile?.name, project.pipeline.site_profile?.address?.city].filter(Boolean).join(' · ') }}
                        </p>
                        <p class="text-[10px] text-on-surface-variant font-mono mt-0.5">{{ project.pipeline.pipeline_id }}</p>
                    </td>
                    <td class="px-5 py-3 text-sm text-on-surface-variant">
                        {{ project.pipeline.project_manager?.full_name ?? '—' }}
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-24 h-1.5 bg-surface-container-high rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-primary rounded-full transition-all"
                                    :style="{ width: progress(project).total ? `${(progress(project).done / progress(project).total) * 100}%` : '0%' }"
                                />
                            </div>
                            <span class="text-xs text-on-surface-variant">{{ progress(project).done }}/{{ progress(project).total }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="statusStyle(project.status)">
                            {{ project.status }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right" @click.stop>
                        <Link :href="`/projects/${project.id}`" class="text-xs font-semibold text-primary hover:underline">
                            View
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="projects.last_page > 1" class="px-5 py-3 border-t border-outline/10 flex items-center justify-between text-xs text-on-surface-variant">
            <span>Showing {{ projects.from }}–{{ projects.to }} of {{ projects.total }}</span>
            <div class="flex gap-1">
                <Link
                    v-for="link in projects.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'px-2.5 py-1 rounded',
                        link.active ? 'bg-primary text-white font-semibold' : 'hover:bg-surface-container',
                        !link.url ? 'opacity-40 pointer-events-none' : '',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>
</template>
