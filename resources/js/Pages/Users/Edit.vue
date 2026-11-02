<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    user: Object,
    roles: Array,
    departments: Array,
})

const flash = computed(() => usePage().props.flash)

const form = useForm({
    full_name: props.user.full_name,
    email: props.user.email,
    password: '',
    role_id: props.user.role_id,
    department_id: props.user.department_id,
    status: props.user.status,
})

const resetForm = useForm({})

function submit() {
    form.put(`/users/${props.user.id}`)
}

function resetPassword() {
    if (confirm(`Reset password for ${props.user.full_name}? A new password will be emailed to them.`)) {
        resetForm.post(`/users/${props.user.id}/reset-password`)
    }
}
</script>

<template>
    <Head title="Edit User" />
    <!-- Page header -->
    <div class="flex items-center gap-4">
        <a href="/users" class="p-2 text-on-surface-variant hover:text-on-surface transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">Edit User</h1>
            <p class="text-on-surface-variant text-sm mt-1">Update details for {{ user.full_name }}.</p>
        </div>
    </div>

    <!-- Flash -->
    <div v-if="flash.success" class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800 text-sm max-w-2xl">
        <span class="material-symbols-outlined text-green-500 text-[20px]">check_circle</span>
        {{ flash.success }}
    </div>

    <!-- Form card -->
    <div class="bg-surface-bright rounded-xl border border-outline/10 max-w-2xl">
        <form @submit.prevent="submit" class="p-8 flex flex-col gap-5">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Full Name</label>
                <input
                    v-model="form.full_name"
                    type="text"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                />
                <p v-if="form.errors.full_name" class="text-error text-xs mt-1">{{ form.errors.full_name }}</p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Email</label>
                <input
                    v-model="form.email"
                    type="email"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                />
                <p v-if="form.errors.email" class="text-error text-xs mt-1">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
                    New Password <span class="normal-case font-normal text-on-surface-variant">(leave blank to keep current)</span>
                </label>
                <input
                    v-model="form.password"
                    type="password"
                    placeholder="••••••••"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                />
                <p v-if="form.errors.password" class="text-error text-xs mt-1">{{ form.errors.password }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Role</label>
                    <select
                        v-model="form.role_id"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                    >
                        <option value="">Select role</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                    </select>
                    <p v-if="form.errors.role_id" class="text-error text-xs mt-1">{{ form.errors.role_id }}</p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Department</label>
                    <select
                        v-model="form.department_id"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                    >
                        <option value="">Select department</option>
                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                    <p v-if="form.errors.department_id" class="text-error text-xs mt-1">{{ form.errors.department_id }}</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Status</label>
                <select
                    v-model="form.status"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                >
                    <option value="ACTIVE">Active</option>
                    <option value="INACTIVE">Inactive</option>
                </select>
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2.5 text-sm font-bold bg-primary text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                    >
                        Save Changes
                    </button>
                    <a href="/users" class="px-4 py-2.5 text-sm font-semibold text-on-surface-variant border border-outline/40 rounded-lg hover:bg-surface-variant transition-all">
                        Cancel
                    </a>
                </div>
                <button
                    type="button"
                    @click="resetPassword"
                    :disabled="resetForm.processing"
                    class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-amber-700 border border-amber-300 bg-amber-50 rounded-lg hover:bg-amber-100 transition-all disabled:opacity-60"
                >
                    <span class="material-symbols-outlined text-[18px]">lock_reset</span>
                    Reset Password
                </button>
            </div>

        </form>
    </div>
</template>