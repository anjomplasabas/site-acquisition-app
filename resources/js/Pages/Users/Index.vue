<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    users: Object,
    roles: Array,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')
const statusFilter = ref(props.filters?.status ?? '')
const roleFilter = ref(props.filters?.role ?? '')

watch([search, statusFilter, roleFilter], () => {
    router.get('/users', { search: search.value, status: statusFilter.value, role: roleFilter.value }, {
        preserveState: true,
        replace: true,
    })
})

function deleteUser(user) {
    if (confirm(`Delete "${user.full_name}"? This cannot be undone.`)) {
        router.delete(`/users/${user.id}`)
    }
}
</script>

<template>
    <Head title="Users" />
    <!-- Page header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">Users</h1>
            <p class="text-on-surface-variant text-sm mt-1">Manage team members and their access.</p>
        </div>
        <a
            href="/users/create"
            class="flex items-center gap-2 bg-primary text-white font-bold text-sm px-4 py-2.5 rounded-lg hover:brightness-110 transition-all"
        >
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            Add User
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
                placeholder="Search by name or email..."
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
        <select
            v-model="roleFilter"
            class="bg-surface-bright border border-outline/40 rounded-lg py-2.5 px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
        >
            <option value="">All Roles</option>
            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
        </select>
    </div>

    <!-- ── Mobile card list (< md) ─────────────────────────────────────── -->
    <div class="md:hidden flex flex-col gap-2">
        <p v-if="users.data.length === 0" class="text-center text-sm text-on-surface-variant py-12">
            No users found.
        </p>

        <div
            v-for="user in users.data"
            :key="user.id"
            class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden"
        >
            <!-- Name + actions -->
            <div class="flex items-center justify-between gap-3 px-4 py-3.5">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-primary text-[16px]">person</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-on-surface truncate">{{ user.full_name }}</p>
                        <p class="text-xs text-on-surface-variant truncate">{{ user.email }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-0.5 shrink-0">
                    <a
                        :href="`/users/${user.id}/edit`"
                        class="p-1.5 text-on-surface-variant hover:text-primary transition-colors"
                        title="Edit"
                    >
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                    </a>
                    <button
                        @click="deleteUser(user)"
                        class="p-1.5 text-on-surface-variant hover:text-error transition-colors"
                        title="Delete"
                    >
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                    </button>
                </div>
            </div>

            <!-- Meta row -->
            <div class="border-t border-outline/10 flex items-center gap-4 px-4 py-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant w-20 shrink-0">Role</span>
                <span class="text-xs text-on-surface truncate">{{ user.role?.name ?? '—' }}</span>
            </div>
            <div class="border-t border-outline/5 flex items-center gap-4 px-4 py-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant w-20 shrink-0">Dept</span>
                <span class="text-xs text-on-surface truncate">{{ user.department?.name ?? '—' }}</span>
            </div>
            <div class="border-t border-outline/5 flex items-center gap-4 px-4 py-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant w-20 shrink-0">Status</span>
                <span
                    class="text-[10px] font-bold uppercase tracking-wide px-2.5 py-0.5 rounded-full"
                    :class="user.status === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                >
                    {{ user.status }}
                </span>
            </div>
        </div>

        <!-- Mobile pagination -->
        <div v-if="users.last_page > 1" class="flex items-center justify-between pt-2">
            <p class="text-xs text-on-surface-variant">{{ users.from }}–{{ users.to }} of {{ users.total }}</p>
            <div class="flex items-center gap-1">
                <a
                    v-for="link in users.links"
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
        <table class="w-full table-fixed min-w-[900px]">
            <thead>
                <tr class="border-b border-outline/10">
                    <th class="px-6 py-3 w-[22%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Name</th>
                    <th class="px-4 py-3 w-[24%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Email</th>
                    <th class="px-4 py-3 w-[16%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Role</th>
                    <th class="px-4 py-3 w-[16%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Department</th>
                    <th class="px-4 py-3 w-[12%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Status</th>
                    <th class="px-6 py-3 w-[10%] text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="users.data.length === 0">
                    <td colspan="6" class="px-6 py-12 text-center text-sm text-on-surface-variant">No users found.</td>
                </tr>
                <tr
                    v-for="user in users.data"
                    :key="user.id"
                    class="border-b border-outline/5 hover:bg-surface-container-low transition-colors"
                >
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-primary text-[16px]">person</span>
                            </div>
                            <span class="text-sm font-semibold text-on-surface truncate" :title="user.full_name">{{ user.full_name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-sm text-on-surface-variant truncate" :title="user.email">{{ user.email }}</td>
                    <td class="px-4 py-4 text-sm text-on-surface-variant truncate" :title="user.role?.name">{{ user.role?.name ?? '—' }}</td>
                    <td class="px-4 py-4 text-sm text-on-surface-variant truncate" :title="user.department?.name">{{ user.department?.name ?? '—' }}</td>
                    <td class="px-4 py-4">
                        <span
                            class="text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full"
                            :class="user.status === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                        >
                            {{ user.status }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1">
                            <a
                                :href="`/users/${user.id}/edit`"
                                class="p-1.5 text-on-surface-variant hover:text-primary transition-colors"
                                title="Edit"
                            >
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </a>
                            <button
                                @click="deleteUser(user)"
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
        <div v-if="users.last_page > 1" class="flex items-center justify-between px-6 py-4 border-t border-outline/10">
            <p class="text-xs text-on-surface-variant">
                Showing {{ users.from }}–{{ users.to }} of {{ users.total }} users
            </p>
            <div class="flex items-center gap-1">
                <a
                    v-for="link in users.links"
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