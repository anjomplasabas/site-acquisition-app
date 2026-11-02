<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    departments: Object,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')
const statusFilter = ref(props.filters?.status ?? '')

watch([search, statusFilter], () => {
    router.get('/departments', { search: search.value, status: statusFilter.value }, {
        preserveState: true,
        replace: true,
    })
})

function deleteDepartment(department) {
    if (confirm(`Delete "${department.name}"? This cannot be undone.`)) {
        router.delete(`/departments/${department.id}`)
    }
}
</script>

<template>
    <Head title="Departments" />
    <!-- Page header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">Departments</h1>
            <p class="text-on-surface-variant text-sm mt-1">Manage your organisation's departments.</p>
        </div>
        <a
            href="/departments/create"
            class="flex items-center gap-2 bg-primary text-white font-bold text-sm px-4 py-2.5 rounded-lg hover:brightness-110 transition-all"
        >
            <span class="material-symbols-outlined text-[18px]">add</span>
            Add Department
        </a>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-3">
        <div class="relative flex-1 max-w-sm">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <span class="material-symbols-outlined text-[18px]">search</span>
            </div>
            <input
                v-model="search"
                type="text"
                placeholder="Search departments..."
                class="w-full bg-surface-bright border border-outline/40 rounded-lg py-2.5 pl-10 pr-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
            />
        </div>
        <select
            v-model="statusFilter"
            class="bg-surface-bright border border-outline/40 rounded-lg py-2.5 px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
        >
            <option value="">All Status</option>
            <option value="ACTIVE">Active</option>
            <option value="INACTIVE">Inactive</option>
        </select>
    </div>

    <!-- Table -->
    <div class="bg-surface-bright rounded-xl border border-outline/10">
        <table class="w-full">
            <thead>
                <tr class="border-b border-outline/10">
                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Name</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Members</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Status</th>
                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="departments.data.length === 0">
                    <td colspan="4" class="px-6 py-12 text-center text-sm text-on-surface-variant">No departments found.</td>
                </tr>
                <tr
                    v-for="dept in departments.data"
                    :key="dept.id"
                    class="border-b border-outline/5 hover:bg-surface-container-low transition-colors"
                >
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-primary text-[16px]">corporate_fare</span>
                            </div>
                            <span class="text-sm font-semibold text-on-surface">{{ dept.name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-sm text-on-surface-variant">{{ dept.users_count }} {{ dept.users_count === 1 ? 'member' : 'members' }}</td>
                    <td class="px-4 py-4">
                        <span
                            class="text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full"
                            :class="dept.status === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                        >
                            {{ dept.status }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1">
                            <a
                                :href="`/departments/${dept.id}/edit`"
                                class="p-1.5 text-on-surface-variant hover:text-primary transition-colors"
                                title="Edit"
                            >
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </a>
                            <button
                                @click="deleteDepartment(dept)"
                                class="p-1.5 text-on-surface-variant hover:text-error transition-colors"
                                title="Delete"
                            >
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="departments.last_page > 1" class="flex items-center justify-between px-6 py-4 border-t border-outline/10">
            <p class="text-xs text-on-surface-variant">
                Showing {{ departments.from }}–{{ departments.to }} of {{ departments.total }} departments
            </p>
            <div class="flex items-center gap-1">
                <a
                    v-for="link in departments.links"
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