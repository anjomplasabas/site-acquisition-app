<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    assignment: Object,
    designers: Array,
})

const assignForm = useForm({
    design_plan_assignee_id: props.assignment.design_plan_assignee?.id ?? '',
})

function submit() {
    assignForm.patch(`/site-planning/${props.assignment.id}/design-plan/assign`, { preserveScroll: true })
}
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-1.5 max-w-xs">
        <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] text-on-surface-variant/50 pointer-events-none">engineering</span>
            <select
                v-model="assignForm.design_plan_assignee_id"
                class="w-full appearance-none bg-surface-bright border rounded-lg pl-9 pr-9 py-2 text-sm font-medium text-on-surface hover:border-outline/60 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer shadow-sm"
                :class="assignForm.errors.design_plan_assignee_id ? 'border-error' : 'border-outline/30'"
                @change="submit"
            >
                <option value="" disabled>Assign a designer…</option>
                <option v-for="designer in designers" :key="designer.id" :value="designer.id">{{ designer.full_name }}</option>
            </select>
            <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant/50 pointer-events-none">expand_more</span>
        </div>
        <p v-if="assignForm.errors.design_plan_assignee_id" class="text-xs text-error">{{ assignForm.errors.design_plan_assignee_id }}</p>
    </form>
</template>
