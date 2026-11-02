<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    roles: Array,
    pipelineStatuses: Array,
    moduleOptions: Array,
    actionPermissionOptions: Array,
    projectActionPermissionOptions: Array,
    networkActionPermissionOptions: Array,
    sitePlanningActionPermissionOptions: Array,
    flash: Object,
})

const search = ref('')

const filteredRoles = computed(() => {
    const query = search.value.trim().toLowerCase()
    if (!query) return props.roles

    return props.roles.filter(role => role.name.toLowerCase().includes(query))
})

function deleteRole(role) {
    if (!confirm(`Delete role "${role.name}"? Users with this role will lose their permissions.`)) { return }
    router.delete(`/roles/${role.id}`)
}

function moduleLabel(value) {
    return props.moduleOptions.find(m => m.value === value)?.label ?? value
}

function actionLabel(value) {
    return props.actionPermissionOptions.find(o => o.value === value)?.label ?? value
}

function projectActionLabel(value) {
    return props.projectActionPermissionOptions.find(o => o.value === value)?.label ?? value
}

function networkActionLabel(value) {
    return props.networkActionPermissionOptions.find(o => o.value === value)?.label ?? value
}

function sitePlanningActionLabel(value) {
    return props.sitePlanningActionPermissionOptions.find(o => o.value === value)?.label ?? value
}

function pipelineActions(role) {
    return (role.action_permissions ?? role.actionPermissions ?? []).filter(p => p.permission.startsWith('pipelines.'))
}

function projectActions(role) {
    return (role.action_permissions ?? role.actionPermissions ?? []).filter(p => p.permission.startsWith('projects.'))
}

function networkActions(role) {
    return (role.action_permissions ?? role.actionPermissions ?? []).filter(p => p.permission.startsWith('network.'))
}

function sitePlanningActions(role) {
    return (role.action_permissions ?? role.actionPermissions ?? []).filter(p => p.permission.startsWith('site_planning.'))
}
</script>

<template>
    <Head title="Roles" />

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">Roles</h1>
            <p class="text-on-surface-variant text-sm mt-1">Define stage, action, and sidebar access for each role.</p>
        </div>
        <a
            href="/roles/create"
            class="flex items-center gap-2 bg-primary text-white font-bold text-sm px-4 py-2.5 rounded-lg hover:brightness-110 transition-all"
        >
            <span class="material-symbols-outlined text-[18px]">add</span>
            New Role
        </a>
    </div>

    <!-- Search -->
    <div class="relative max-w-sm">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
            <span class="material-symbols-outlined text-[18px]">search</span>
        </div>
        <input
            v-model="search"
            type="text"
            placeholder="Search roles..."
            class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-2.5 pl-10 pr-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
        />
    </div>

    <!-- ── Mobile card list (< md) ─────────────────────────────────────── -->
    <div class="md:hidden flex flex-col gap-2">
        <p v-if="filteredRoles.length === 0" class="text-center text-sm text-on-surface-variant py-12">
            {{ roles.length === 0 ? 'No roles yet.' : 'No roles match your search.' }}
        </p>

        <div
            v-for="role in filteredRoles"
            :key="role.id"
            class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden"
        >
            <!-- Role name + actions -->
            <div class="flex items-center justify-between gap-3 px-4 py-3.5">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-primary text-[16px]">badge</span>
                    </div>
                    <span class="text-sm font-semibold text-on-surface truncate">{{ role.name }}</span>
                </div>
                <div class="flex items-center gap-0.5 shrink-0">
                    <a
                        :href="`/roles/${role.id}/edit`"
                        class="p-1.5 text-on-surface-variant hover:text-primary transition-colors"
                        title="Edit"
                    >
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                    </a>
                    <button
                        @click="deleteRole(role)"
                        class="p-1.5 text-on-surface-variant hover:text-error transition-colors"
                        title="Delete"
                    >
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                    </button>
                </div>
            </div>

            <!-- Permission sections -->
            <div class="border-t border-outline/10 divide-y divide-outline/5">
                <!-- Pipeline Stages -->
                <div class="flex items-start gap-3 px-4 py-2.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant w-16 shrink-0 pt-0.5">Stages</span>
                    <div v-if="role.permissions.length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="perm in role.permissions"
                            :key="perm.id"
                            class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary"
                        >
                            {{ perm.pipeline_status }}
                        </span>
                    </div>
                    <span v-else class="text-xs text-on-surface-variant/60 italic">None</span>
                </div>

                <!-- Modules -->
                <div class="flex items-start gap-3 px-4 py-2.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant w-16 shrink-0 pt-0.5">Modules</span>
                    <div v-if="(role.module_permissions ?? role.modulePermissions)?.length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="perm in (role.module_permissions ?? role.modulePermissions)"
                            :key="perm.id"
                            class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant"
                        >
                            {{ moduleLabel(perm.module) }}
                        </span>
                    </div>
                    <span v-else class="text-xs text-on-surface-variant/60 italic">None</span>
                </div>

                <!-- Pipeline Actions -->
                <div class="flex items-start gap-3 px-4 py-2.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant w-16 shrink-0 pt-0.5">Actions</span>
                    <div v-if="pipelineActions(role).length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="perm in pipelineActions(role)"
                            :key="perm.id"
                            class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700"
                        >
                            {{ actionLabel(perm.permission) }}
                        </span>
                    </div>
                    <span v-else class="text-xs text-on-surface-variant/60 italic">None</span>
                </div>

                <!-- Site Planning Actions -->
                <div class="flex items-start gap-3 px-4 py-2.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant w-16 shrink-0 pt-0.5">Site Plan</span>
                    <div v-if="sitePlanningActions(role).length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="perm in sitePlanningActions(role)"
                            :key="perm.id"
                            class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-violet-50 text-violet-700"
                        >
                            {{ sitePlanningActionLabel(perm.permission) }}
                        </span>
                    </div>
                    <span v-else class="text-xs text-on-surface-variant/60 italic">None</span>
                </div>

                <!-- Project Actions -->
                <div class="flex items-start gap-3 px-4 py-2.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant w-16 shrink-0 pt-0.5">Projects</span>
                    <div v-if="projectActions(role).length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="perm in projectActions(role)"
                            :key="perm.id"
                            class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700"
                        >
                            {{ projectActionLabel(perm.permission) }}
                        </span>
                    </div>
                    <span v-else class="text-xs text-on-surface-variant/60 italic">None</span>
                </div>

                <!-- Network Actions -->
                <div class="flex items-start gap-3 px-4 py-2.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant w-16 shrink-0 pt-0.5">Network</span>
                    <div v-if="networkActions(role).length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="perm in networkActions(role)"
                            :key="perm.id"
                            class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700"
                        >
                            {{ networkActionLabel(perm.permission) }}
                        </span>
                    </div>
                    <span v-else class="text-xs text-on-surface-variant/60 italic">None</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Desktop table (≥ md) ────────────────────────────────────────── -->
    <div class="hidden md:block bg-surface-bright rounded-xl border border-outline/10">
        <table class="w-full">
            <thead>
                <tr class="border-b border-outline/10">
                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Role Name</th>
                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline Stages</th>
                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Modules</th>
                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline Actions</th>
                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site Planning Actions</th>
                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Project Actions</th>
                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Network Actions</th>
                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="filteredRoles.length === 0">
                    <td colspan="8" class="px-6 py-12 text-center text-sm text-on-surface-variant">
                        {{ roles.length === 0 ? 'No roles yet.' : 'No roles match your search.' }}
                    </td>
                </tr>
                <tr
                    v-for="role in filteredRoles"
                    :key="role.id"
                    class="border-b border-outline/5 hover:bg-surface-container-low transition-colors"
                >
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-primary text-[16px]">badge</span>
                            </div>
                            <span class="text-sm font-semibold text-on-surface">{{ role.name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div v-if="role.permissions.length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="perm in role.permissions"
                                :key="perm.id"
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary"
                            >
                                {{ perm.pipeline_status }}
                            </span>
                        </div>
                        <span v-else class="text-xs text-on-surface-variant/60 italic">No stages assigned</span>
                    </td>
                    <td class="px-6 py-4">
                        <div v-if="(role.module_permissions ?? role.modulePermissions)?.length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="perm in (role.module_permissions ?? role.modulePermissions)"
                                :key="perm.id"
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant"
                            >
                                {{ moduleLabel(perm.module) }}
                            </span>
                        </div>
                        <span v-else class="text-xs text-on-surface-variant/60 italic">No modules assigned</span>
                    </td>
                    <td class="px-6 py-4">
                        <div v-if="pipelineActions(role).length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="perm in pipelineActions(role)"
                                :key="perm.id"
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700"
                            >
                                {{ actionLabel(perm.permission) }}
                            </span>
                        </div>
                        <span v-else class="text-xs text-on-surface-variant/60 italic">No actions assigned</span>
                    </td>
                    <td class="px-6 py-4">
                        <div v-if="sitePlanningActions(role).length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="perm in sitePlanningActions(role)"
                                :key="perm.id"
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-violet-50 text-violet-700"
                            >
                                {{ sitePlanningActionLabel(perm.permission) }}
                            </span>
                        </div>
                        <span v-else class="text-xs text-on-surface-variant/60 italic">No actions assigned</span>
                    </td>
                    <td class="px-6 py-4">
                        <div v-if="projectActions(role).length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="perm in projectActions(role)"
                                :key="perm.id"
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700"
                            >
                                {{ projectActionLabel(perm.permission) }}
                            </span>
                        </div>
                        <span v-else class="text-xs text-on-surface-variant/60 italic">No actions assigned</span>
                    </td>
                    <td class="px-6 py-4">
                        <div v-if="networkActions(role).length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="perm in networkActions(role)"
                                :key="perm.id"
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700"
                            >
                                {{ networkActionLabel(perm.permission) }}
                            </span>
                        </div>
                        <span v-else class="text-xs text-on-surface-variant/60 italic">No actions assigned</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1">
                            <a
                                :href="`/roles/${role.id}/edit`"
                                class="p-1.5 text-on-surface-variant hover:text-primary transition-colors"
                                title="Edit"
                            >
                                <span class="material-symbols-outlined text-[18px]">edit</span>
                            </a>
                            <button
                                @click="deleteRole(role)"
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
    </div>
</template>
