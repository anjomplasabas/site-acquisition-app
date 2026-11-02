<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    role: Object,
    pipelineStatuses: Array,
    moduleOptions: Array,
    actionPermissionOptions: Array,
    projectActionPermissionOptions: Array,
    networkActionPermissionOptions: Array,
    sitePlanningActionPermissionOptions: Array,
    projectStages: Array,
})

const isEditing = !!props.role

const form = useForm({
    name: props.role?.name ?? '',
    permissions: props.role?.permissions?.map(p => p.pipeline_status) ?? [],
    module_permissions: props.role?.module_permissions?.map(p => p.module)
        ?? props.role?.modulePermissions?.map(p => p.module)
        ?? [],
    action_permissions: props.role?.action_permissions?.map(p => p.permission)
        ?? props.role?.actionPermissions?.map(p => p.permission)
        ?? [],
    project_stage_permissions: props.role?.project_stage_permissions?.map(p => p.project_stage)
        ?? props.role?.projectStagePermissions?.map(p => p.project_stage)
        ?? [],
})

function submit() {
    if (isEditing) {
        form.put(`/roles/${props.role.id}`)
    } else {
        form.post('/roles')
    }
}

function togglePermission(status) {
    const index = form.permissions.indexOf(status)
    if (index === -1) {
        form.permissions.push(status)
    } else {
        form.permissions.splice(index, 1)
    }
}

function toggleModule(module) {
    const index = form.module_permissions.indexOf(module)
    if (index === -1) {
        form.module_permissions.push(module)
    } else {
        form.module_permissions.splice(index, 1)
    }
}

function toggleActionPermission(permission) {
    const index = form.action_permissions.indexOf(permission)
    if (index === -1) {
        form.action_permissions.push(permission)
    } else {
        form.action_permissions.splice(index, 1)
    }
}

function toggleProjectStagePermission(stage) {
    const index = form.project_stage_permissions.indexOf(stage)
    if (index === -1) {
        form.project_stage_permissions.push(stage)
    } else {
        form.project_stage_permissions.splice(index, 1)
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Role' : 'New Role'" />

    <!-- Header -->
    <div class="flex items-center gap-4">
        <a href="/roles" class="p-2 text-on-surface-variant hover:text-on-surface transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">{{ isEditing ? 'Edit Role' : 'New Role' }}</h1>
            <p class="text-on-surface-variant text-sm mt-1">{{ isEditing ? 'Update role stage and module access.' : 'Create a role and assign stages and sidebar modules.' }}</p>
        </div>
    </div>

    <!-- Form -->
    <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-[minmax(280px,360px)_1fr] gap-6 items-start">
        <!-- Left: Role setup -->
        <section class="bg-surface-bright rounded-xl border border-outline/10 p-6 lg:sticky lg:top-24">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-primary text-[20px]">admin_panel_settings</span>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-on-surface">Role Setup</p>
                    <p class="text-xs text-on-surface-variant">Name and save changes</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Role Name</label>
                <input
                    v-model="form.name"
                    type="text"
                    placeholder="e.g. Field Surveyor"
                    class="w-full bg-surface-container-low border rounded-lg py-3 px-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                    :class="form.errors.name ? 'border-error' : 'border-outline/40'"
                />
                <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <div class="flex flex-col sm:flex-row lg:flex-col gap-3 mt-6">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex items-center justify-center gap-2 px-5 py-3 text-sm font-bold bg-primary text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                >
                    <span v-if="form.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-[16px]">save</span>
                    {{ isEditing ? 'Save Changes' : 'Create Role' }}
                </button>
                <a href="/roles" class="flex items-center justify-center px-4 py-3 text-sm font-semibold text-on-surface-variant border border-outline/40 rounded-lg hover:bg-surface-variant transition-all">
                    Cancel
                </a>
            </div>
        </section>

        <!-- Right: Permission workspace -->
        <section class="flex flex-col gap-6 min-w-0">
            <!-- Module Permissions -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant">Module Access</label>
                        <p class="text-xs text-on-surface-variant mt-1">Sidebar modules this role can open.</p>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-primary/10 text-primary shrink-0">
                        {{ form.module_permissions.length }} selected
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-2">
                    <label
                        v-for="module in moduleOptions"
                        :key="module.value"
                        class="group flex items-center gap-3 min-h-12 p-3 rounded-lg border cursor-pointer transition-all"
                        :class="form.module_permissions.includes(module.value)
                            ? 'bg-primary/5 border-primary/40 shadow-sm'
                            : 'border-outline/20 hover:bg-surface-container-low hover:border-outline/40'"
                    >
                        <input
                            type="checkbox"
                            :value="module.value"
                            :checked="form.module_permissions.includes(module.value)"
                            @change="toggleModule(module.value)"
                            class="accent-primary w-4 h-4 shrink-0"
                        />
                        <span class="text-sm text-on-surface font-medium leading-snug">{{ module.label }}</span>
                    </label>
                </div>
                <p v-if="form.errors.module_permissions" class="text-error text-xs mt-2">{{ form.errors.module_permissions }}</p>
            </div>

            <!-- Pipeline Stage Permissions -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pipeline Stages</label>
                        <p class="text-xs text-on-surface-variant mt-1">Stages this role is allowed to act on.</p>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-primary/10 text-primary shrink-0">
                        {{ form.permissions.length }} selected
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-2">
                    <label
                        v-for="status in pipelineStatuses"
                        :key="status"
                        class="group flex items-center gap-3 min-h-12 p-3 rounded-lg border cursor-pointer transition-all"
                        :class="form.permissions.includes(status)
                            ? 'bg-primary/5 border-primary/40 shadow-sm'
                            : 'border-outline/20 hover:bg-surface-container-low hover:border-outline/40'"
                    >
                        <input
                            type="checkbox"
                            :value="status"
                            :checked="form.permissions.includes(status)"
                            @change="togglePermission(status)"
                            class="accent-primary w-4 h-4 shrink-0"
                        />
                        <span class="text-sm text-on-surface font-medium leading-snug">{{ status }}</span>
                    </label>
                </div>
                <p v-if="form.errors.permissions" class="text-error text-xs mt-2">{{ form.errors.permissions }}</p>
            </div>

            <!-- Pipeline Action Permissions -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pipeline Actions</label>
                        <p class="text-xs text-on-surface-variant mt-1">Workflow actions this role can perform.</p>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-primary/10 text-primary shrink-0">
                        {{ form.action_permissions.length }} selected
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-2">
                    <label
                        v-for="permission in actionPermissionOptions"
                        :key="permission.value"
                        class="group flex items-center gap-3 min-h-12 p-3 rounded-lg border cursor-pointer transition-all"
                        :class="form.action_permissions.includes(permission.value)
                            ? 'bg-primary/5 border-primary/40 shadow-sm'
                            : 'border-outline/20 hover:bg-surface-container-low hover:border-outline/40'"
                    >
                        <input
                            type="checkbox"
                            :value="permission.value"
                            :checked="form.action_permissions.includes(permission.value)"
                            @change="toggleActionPermission(permission.value)"
                            class="accent-primary w-4 h-4 shrink-0"
                        />
                        <span class="text-sm text-on-surface font-medium leading-snug">{{ permission.label }}</span>
                    </label>
                </div>
                <p v-if="form.errors.action_permissions" class="text-error text-xs mt-2">{{ form.errors.action_permissions }}</p>
            </div>

            <!-- Site Planning Action Permissions -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant">Site Planning Actions</label>
                        <p class="text-xs text-on-surface-variant mt-1">Site Planning actions this role can perform (survey scheduling, design plan work, approval, assignment).</p>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-primary/10 text-primary shrink-0">
                        {{ form.action_permissions.filter(p => p.startsWith('site_planning.')).length }} selected
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-2">
                    <label
                        v-for="permission in sitePlanningActionPermissionOptions"
                        :key="permission.value"
                        class="group flex items-center gap-3 min-h-12 p-3 rounded-lg border cursor-pointer transition-all"
                        :class="form.action_permissions.includes(permission.value)
                            ? 'bg-primary/5 border-primary/40 shadow-sm'
                            : 'border-outline/20 hover:bg-surface-container-low hover:border-outline/40'"
                    >
                        <input
                            type="checkbox"
                            :value="permission.value"
                            :checked="form.action_permissions.includes(permission.value)"
                            @change="toggleActionPermission(permission.value)"
                            class="accent-primary w-4 h-4 shrink-0"
                        />
                        <span class="text-sm text-on-surface font-medium leading-snug">{{ permission.label }}</span>
                    </label>
                </div>
            </div>

            <!-- Project Action Permissions -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant">Project Actions</label>
                        <p class="text-xs text-on-surface-variant mt-1">Project Management actions this role can perform (sections, phases, tasks).</p>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-primary/10 text-primary shrink-0">
                        {{ form.action_permissions.filter(p => p.startsWith('projects.')).length }} selected
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-2">
                    <label
                        v-for="permission in projectActionPermissionOptions"
                        :key="permission.value"
                        class="group flex items-center gap-3 min-h-12 p-3 rounded-lg border cursor-pointer transition-all"
                        :class="form.action_permissions.includes(permission.value)
                            ? 'bg-primary/5 border-primary/40 shadow-sm'
                            : 'border-outline/20 hover:bg-surface-container-low hover:border-outline/40'"
                    >
                        <input
                            type="checkbox"
                            :value="permission.value"
                            :checked="form.action_permissions.includes(permission.value)"
                            @change="toggleActionPermission(permission.value)"
                            class="accent-primary w-4 h-4 shrink-0"
                        />
                        <span class="text-sm text-on-surface font-medium leading-snug">{{ permission.label }}</span>
                    </label>
                </div>
            </div>

            <!-- Network Action Permissions -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant">Network Actions</label>
                        <p class="text-xs text-on-surface-variant mt-1">Network module actions this role can perform (view sites, manage ISP feasibility).</p>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-primary/10 text-primary shrink-0">
                        {{ form.action_permissions.filter(p => p.startsWith('network.')).length }} selected
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-3 gap-2">
                    <label
                        v-for="permission in networkActionPermissionOptions"
                        :key="permission.value"
                        class="group flex items-center gap-3 min-h-12 p-3 rounded-lg border cursor-pointer transition-all"
                        :class="form.action_permissions.includes(permission.value)
                            ? 'bg-primary/5 border-primary/40 shadow-sm'
                            : 'border-outline/20 hover:bg-surface-container-low hover:border-outline/40'"
                    >
                        <input
                            type="checkbox"
                            :value="permission.value"
                            :checked="form.action_permissions.includes(permission.value)"
                            @change="toggleActionPermission(permission.value)"
                            class="accent-primary w-4 h-4 shrink-0"
                        />
                        <span class="text-sm text-on-surface font-medium leading-snug">{{ permission.label }}</span>
                    </label>
                </div>
            </div>

            <!-- Project Stage Permissions -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant">Project Stages</label>
                        <p class="text-xs text-on-surface-variant mt-1">Which Project Management stages this role can act on (requires a Project Action above too).</p>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-primary/10 text-primary shrink-0">
                        {{ form.project_stage_permissions.length }} selected
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-4 gap-2">
                    <label
                        v-for="stage in projectStages"
                        :key="stage.value"
                        class="group flex items-center gap-3 min-h-12 p-3 rounded-lg border cursor-pointer transition-all"
                        :class="form.project_stage_permissions.includes(stage.value)
                            ? 'bg-primary/5 border-primary/40 shadow-sm'
                            : 'border-outline/20 hover:bg-surface-container-low hover:border-outline/40'"
                    >
                        <input
                            type="checkbox"
                            :value="stage.value"
                            :checked="form.project_stage_permissions.includes(stage.value)"
                            @change="toggleProjectStagePermission(stage.value)"
                            class="accent-primary w-4 h-4 shrink-0"
                        />
                        <span class="text-sm text-on-surface font-medium leading-snug">{{ stage.label }}</span>
                    </label>
                </div>
                <p v-if="form.errors.project_stage_permissions" class="text-error text-xs mt-2">{{ form.errors.project_stage_permissions }}</p>
            </div>
        </section>
    </form>
</template>
