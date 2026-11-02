<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

defineProps({
    roles: Array,
    departments: Array,
})

const form = useForm({
    full_name: '',
    email: '',
    role_id: '',
    department_id: '',
    status: 'ACTIVE',
})

function submit() {
    form.post('/users')
}
</script>

<template>
    <Head title="New User" />
    <!-- Page header -->
    <div class="flex items-center gap-4">
        <a href="/users" class="p-2 text-on-surface-variant hover:text-on-surface transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">Add User</h1>
            <p class="text-on-surface-variant text-sm mt-1">A temporary password will be generated and emailed to the user.</p>
        </div>
    </div>

    <!-- Form card -->
    <div class="bg-surface-bright rounded-xl border border-outline/10 max-w-2xl">
        <form @submit.prevent="submit" class="p-8 flex flex-col gap-5">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Full Name</label>
                <input
                    v-model="form.full_name"
                    type="text"
                    placeholder="Jane Doe"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                />
                <p v-if="form.errors.full_name" class="text-error text-xs mt-1">{{ form.errors.full_name }}</p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Email</label>
                <input
                    v-model="form.email"
                    type="email"
                    placeholder="jane@company.com"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                />
                <p v-if="form.errors.email" class="text-error text-xs mt-1">{{ form.errors.email }}</p>
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
                </select>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-5 py-2.5 text-sm font-bold bg-primary text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                >
                    Create User
                </button>
                <a href="/users" class="px-4 py-2.5 text-sm font-semibold text-on-surface-variant border border-outline/40 rounded-lg hover:bg-surface-variant transition-all">
                    Cancel
                </a>
            </div>

        </form>
    </div>
</template>