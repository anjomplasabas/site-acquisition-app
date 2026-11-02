<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    department: Object,
})

const form = useForm({
    name: props.department.name,
    status: props.department.status,
})

function submit() {
    form.put(`/departments/${props.department.id}`)
}
</script>

<template>
    <Head title="Edit Department" />
    <!-- Page header -->
    <div class="flex items-center gap-4">
        <a href="/departments" class="p-2 text-on-surface-variant hover:text-on-surface transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">Edit Department</h1>
            <p class="text-on-surface-variant text-sm mt-1">Update details for {{ department.name }}.</p>
        </div>
    </div>

    <!-- Form card -->
    <div class="bg-surface-bright rounded-xl border border-outline/10 max-w-lg">
        <form @submit.prevent="submit" class="p-8 flex flex-col gap-5">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Department Name</label>
                <input
                    v-model="form.name"
                    type="text"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                />
                <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
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

            <div class="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-5 py-2.5 text-sm font-bold bg-primary text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                >
                    Save Changes
                </button>
                <a href="/departments" class="px-4 py-2.5 text-sm font-semibold text-on-surface-variant border border-outline/40 rounded-lg hover:bg-surface-variant transition-all">
                    Cancel
                </a>
            </div>

        </form>
    </div>
</template>