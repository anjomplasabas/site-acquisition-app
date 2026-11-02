<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    logs: Object,
    filters: Object,
    users: Array,
})

const search = ref(props.filters?.search ?? '')
const userId = ref(props.filters?.user_id ?? '')
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')
const expandedRows = ref(new Set())

watch([search, userId, dateFrom, dateTo], () => {
    router.get('/audit-logs', {
        search: search.value,
        user_id: userId.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    }, { preserveState: true, replace: true })
})

function clearFilters() {
    search.value = ''
    userId.value = ''
    dateFrom.value = ''
    dateTo.value = ''
}

function toggleRow(id) {
    const updated = new Set(expandedRows.value)
    updated.has(id) ? updated.delete(id) : updated.add(id)
    expandedRows.value = updated
}

function hasMetadata(log) {
    return log.metadata && Object.keys(log.metadata).length > 0
}

function metadataEntries(metadata) {
    if (!metadata) return []
    const entries = []
    for (const [key, value] of Object.entries(metadata)) {
        if (key === 'fields' && value && typeof value === 'object' && !Array.isArray(value)) {
            for (const [k, v] of Object.entries(value)) {
                entries.push({ key: k, value: v })
            }
        } else {
            entries.push({ key, value })
        }
    }
    return entries
}

function formatMetaScalar(value) {
    if (value === null || value === undefined) return ''
    if (typeof value === 'boolean') return value ? 'Yes' : 'No'
    if (typeof value !== 'object') return String(value)

    const readable = value.provider_name ?? value.name ?? value.label ?? value.title
    return readable !== undefined ? String(readable) : JSON.stringify(value)
}

function formatMetaValue(value) {
    if (value === null || value === undefined) return '—'
    if (Array.isArray(value)) return value.length ? value.map(formatMetaScalar).join(', ') : '—'
    if (typeof value === 'boolean') return value ? 'Yes' : 'No'
    if (typeof value === 'object') return formatMetaScalar(value)
    return String(value) || '—'
}

function formatDate(dateString) {
    if (!dateString) return '—'
    return new Date(dateString).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function formatDateShort(dateString) {
    if (!dateString) return '—'
    return new Date(dateString).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const hasActiveFilters = () =>
    search.value || userId.value || dateFrom.value || dateTo.value
</script>

<template>
    <Head title="Audit Logs" />

    <!-- Page header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">Audit Logs</h1>
            <p class="text-on-surface-variant text-sm mt-1">Full record of all user actions across the system.</p>
        </div>
        <p class="text-xs text-on-surface-variant">{{ logs.total }} entries</p>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-40">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <span class="material-symbols-outlined text-[18px]">search</span>
            </div>
            <input
                v-model="search"
                type="text"
                placeholder="Search description or action..."
                class="w-full bg-surface-bright border border-outline/40 rounded-lg py-2.5 pl-10 pr-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
            />
        </div>
        <select
            v-model="userId"
            class="bg-surface-bright border border-outline/40 rounded-lg py-2.5 px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
        >
            <option value="">All Users</option>
            <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.full_name }}
            </option>
        </select>
        <input
            v-model="dateFrom"
            type="date"
            class="bg-surface-bright border border-outline/40 rounded-lg py-2.5 px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
        />
        <input
            v-model="dateTo"
            type="date"
            class="bg-surface-bright border border-outline/40 rounded-lg py-2.5 px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
        />
        <button
            v-if="hasActiveFilters()"
            @click="clearFilters"
            class="flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-on-surface transition-colors"
        >
            <span class="material-symbols-outlined text-[16px]">close</span>
            Clear
        </button>
    </div>

    <!-- ── Mobile card list (< md) ─────────────────────────────────────── -->
    <div class="md:hidden flex flex-col gap-2">
        <p v-if="logs.data.length === 0" class="text-center text-sm text-on-surface-variant py-12">
            No audit log entries found.
        </p>

        <template v-for="log in logs.data" :key="log.id">
            <div
                class="bg-surface-bright rounded-xl border transition-colors"
                :class="expandedRows.has(log.id) ? 'border-primary/20' : 'border-outline/10'"
            >
                <!-- Card body -->
                <div class="px-4 py-3.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <!-- User + timestamp row -->
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <span v-if="log.actor_name" class="text-sm font-semibold text-on-surface truncate">
                                    {{ log.actor_name }}
                                </span>
                                <span v-else class="text-xs text-on-surface-variant italic">System</span>
                                <span class="text-[11px] text-on-surface-variant font-mono whitespace-nowrap shrink-0">
                                    {{ formatDateShort(log.created_at) }}
                                </span>
                            </div>
                            <!-- Action badge -->
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-surface-variant text-[10px] font-mono text-on-surface-variant mb-2">
                                {{ log.action }}
                            </span>
                            <!-- Description -->
                            <p class="text-sm text-on-surface leading-snug">{{ log.description }}</p>
                            <!-- IP -->
                            <p v-if="log.ip_address" class="text-[11px] text-on-surface-variant font-mono mt-1.5">
                                {{ log.ip_address }}
                            </p>
                        </div>
                        <!-- Expand button -->
                        <button
                            v-if="hasMetadata(log)"
                            @click="toggleRow(log.id)"
                            class="shrink-0 p-1.5 rounded-lg text-on-surface-variant hover:text-primary hover:bg-primary/5 transition-colors mt-0.5"
                        >
                            <span
                                class="material-symbols-outlined text-[20px] block transition-transform duration-200"
                                :class="expandedRows.has(log.id) ? 'rotate-180' : ''"
                            >
                                expand_more
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Metadata panel (mobile) -->
                <div v-if="expandedRows.has(log.id) && hasMetadata(log)" class="border-t border-outline/10">
                    <div class="flex items-center gap-2 px-4 py-2 bg-surface-variant/30">
                        <span class="material-symbols-outlined text-[14px] text-on-surface-variant">data_object</span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Metadata</span>
                    </div>
                    <div class="grid grid-cols-2 gap-px bg-outline/5">
                        <div
                            v-for="entry in metadataEntries(log.metadata)"
                            :key="entry.key"
                            class="flex flex-col gap-1 px-4 py-3 bg-surface-bright"
                        >
                            <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant truncate">
                                {{ entry.key.replace(/_/g, ' ') }}
                            </span>
                            <span class="text-xs text-on-surface font-mono break-all">
                                {{ formatMetaValue(entry.value) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Mobile pagination -->
        <div v-if="logs.last_page > 1" class="flex items-center justify-between pt-2">
            <p class="text-xs text-on-surface-variant">
                {{ logs.from }}–{{ logs.to }} of {{ logs.total }}
            </p>
            <div class="flex items-center gap-1">
                <a
                    v-for="link in logs.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    @click.prevent="link.url && router.get(link.url)"
                    class="px-3 py-1.5 text-xs rounded-lg transition-colors"
                    :class="link.active ? 'bg-primary text-white font-bold' : link.url ? 'text-on-surface-variant hover:bg-surface-variant' : 'text-on-surface-variant/30 cursor-default'"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>

    <!-- ── Desktop table (≥ md) ────────────────────────────────────────── -->
    <div class="hidden md:block bg-surface-bright rounded-xl border border-outline/10 overflow-x-auto">
        <table class="w-full table-fixed min-w-[960px]">
            <thead>
                <tr class="border-b border-outline/10">
                    <th class="px-6 py-3 w-[13%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Timestamp</th>
                    <th class="px-4 py-3 w-[20%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">User</th>
                    <th class="px-4 py-3 w-[15%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Action</th>
                    <th class="px-4 py-3 w-[32%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Description</th>
                    <th class="px-4 py-3 w-[14%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">IP Address</th>
                    <th class="px-4 py-3 w-[6%]"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="logs.data.length === 0">
                    <td colspan="6" class="px-6 py-12 text-center text-sm text-on-surface-variant">No audit log entries found.</td>
                </tr>

                <template v-for="log in logs.data" :key="log.id">
                    <!-- Main row -->
                    <tr
                        class="border-b transition-colors"
                        :class="expandedRows.has(log.id) ? 'border-outline/10 bg-surface-container-low/40' : 'border-outline/5 hover:bg-surface-container-low'"
                    >
                        <td class="px-6 py-3.5 text-xs text-on-surface-variant font-mono whitespace-nowrap truncate">
                            {{ formatDate(log.created_at) }}
                        </td>
                        <td class="px-4 py-3.5 min-w-0">
                            <div v-if="log.actor_name" class="min-w-0">
                                <p class="text-sm font-semibold text-on-surface truncate" :title="log.actor_name">{{ log.actor_name }}</p>
                                <p class="text-xs text-on-surface-variant truncate" :title="log.actor_email">{{ log.actor_email }}</p>
                            </div>
                            <span v-else class="text-xs text-on-surface-variant italic">System</span>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-surface-variant text-[10px] font-mono text-on-surface-variant truncate max-w-full">
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-sm text-on-surface truncate" :title="log.description">
                            {{ log.description }}
                        </td>
                        <td class="px-4 py-3.5 text-xs text-on-surface-variant font-mono truncate" :title="log.ip_address">
                            {{ log.ip_address ?? '—' }}
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <button
                                v-if="hasMetadata(log)"
                                @click="toggleRow(log.id)"
                                class="p-1 rounded-md text-on-surface-variant hover:text-primary hover:bg-primary/5 transition-colors"
                                :title="expandedRows.has(log.id) ? 'Hide metadata' : 'View metadata'"
                            >
                                <span
                                    class="material-symbols-outlined text-[18px] block transition-transform duration-200"
                                    :class="expandedRows.has(log.id) ? 'rotate-180' : ''"
                                >
                                    expand_more
                                </span>
                            </button>
                        </td>
                    </tr>

                    <!-- Metadata expansion row -->
                    <tr v-if="expandedRows.has(log.id) && hasMetadata(log)" :key="`${log.id}-meta`" class="border-b border-outline/5">
                        <td colspan="6" class="px-6 pt-0 pb-4 bg-surface-container-low/40">
                            <div class="border border-outline/10 rounded-lg bg-surface-bright overflow-hidden">
                                <div class="flex items-center gap-2 px-4 py-2 bg-surface-variant/30 border-b border-outline/10">
                                    <span class="material-symbols-outlined text-[14px] text-on-surface-variant">data_object</span>
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Metadata</span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-px bg-outline/5">
                                    <div
                                        v-for="entry in metadataEntries(log.metadata)"
                                        :key="entry.key"
                                        class="flex flex-col gap-1 px-4 py-3 bg-surface-bright"
                                    >
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant truncate">
                                            {{ entry.key.replace(/_/g, ' ') }}
                                        </span>
                                        <span class="text-xs text-on-surface font-mono break-all">
                                            {{ formatMetaValue(entry.value) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <!-- Desktop pagination -->
        <div v-if="logs.last_page > 1" class="flex items-center justify-between px-6 py-4 border-t border-outline/10">
            <p class="text-xs text-on-surface-variant">
                Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }} entries
            </p>
            <div class="flex items-center gap-1">
                <a
                    v-for="link in logs.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    @click.prevent="link.url && router.get(link.url)"
                    class="px-3 py-1.5 text-xs rounded-lg transition-colors"
                    :class="link.active ? 'bg-primary text-white font-bold' : link.url ? 'text-on-surface-variant hover:bg-surface-variant' : 'text-on-surface-variant/30 cursor-default'"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>
</template>
