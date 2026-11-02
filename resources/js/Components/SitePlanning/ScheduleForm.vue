<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { formatDate } from '@/utils/pipeline.js'

const props = defineProps({
    assignment: Object,
    surveyors: Array,
    designPlanTypes: Array,
})

// Once a surveyor and date have been saved, the schedule is locked in — show it read-only
// instead of the form.
const isScheduled = computed(() => !!(props.assignment.site_survey?.surveyor && props.assignment.site_survey?.survey_date))

// Type is locked once the Plans/Drawing checklist has been seeded — changing it afterward would
// orphan whatever task status/notes/documents have already been recorded against the old list.
const isTypeLocked = computed(() => (props.assignment.site_planning_tasks ?? []).length > 0)

const scheduleForm = useForm({
    surveyor_id: props.assignment.site_survey?.surveyor?.id ?? '',
    survey_date: props.assignment.site_survey?.survey_date
        ? new Date(props.assignment.site_survey.survey_date).toISOString().slice(0, 10)
        : '',
    design_plan_type: props.assignment.design_plan_type ?? '',
    remarks: '',
    files: [],
})

function submit() {
    scheduleForm.patch(`/site-planning/${props.assignment.id}/schedule`, {
        preserveScroll: true,
        forceFormData: true,
    })
}
</script>

<template>
    <div class="bg-surface-bright rounded-2xl border border-outline/10 p-7">
        <template v-if="isScheduled">
            <div class="flex items-center gap-2.5 mb-6">
                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">event_available</span>
                <div>
                    <p class="text-sm font-bold text-on-surface">Site Survey Scheduled</p>
                    <p class="text-xs text-on-surface-variant mt-0.5">Surveyor and survey date have been saved.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-10 gap-y-5">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Surveyor</p>
                    <p class="text-sm font-semibold text-on-surface">{{ assignment.site_survey.surveyor.full_name }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Survey Date</p>
                    <p class="text-sm font-semibold text-on-surface">{{ formatDate(assignment.site_survey.survey_date) }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Type</p>
                    <p class="text-sm font-semibold text-on-surface">{{ assignment.design_plan_type ?? '—' }}</p>
                </div>
            </div>

            <div v-if="assignment.site_survey.remarks" class="pt-5 mt-5 border-t border-outline/10">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Remarks</p>
                <p class="text-sm text-on-surface">{{ assignment.site_survey.remarks }}</p>
            </div>
        </template>

        <template v-else>
            <div class="flex items-center gap-2.5 mb-6">
                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">event_available</span>
                <div>
                    <p class="text-sm font-bold text-on-surface">Schedule Site Survey</p>
                    <p class="text-xs text-on-surface-variant mt-0.5">Assign a Surveyor and set the survey date.</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Surveyor</label>
                        <select
                            v-model="scheduleForm.surveyor_id"
                            class="w-full appearance-none bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer"
                            :class="scheduleForm.errors.surveyor_id ? 'border-error' : 'border-outline/40'"
                        >
                            <option value="" disabled>Select a surveyor</option>
                            <option v-for="surveyor in surveyors" :key="surveyor.id" :value="surveyor.id">{{ surveyor.full_name }}</option>
                        </select>
                        <p v-if="scheduleForm.errors.surveyor_id" class="text-xs text-error">{{ scheduleForm.errors.surveyor_id }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Survey Date</label>
                        <input
                            v-model="scheduleForm.survey_date"
                            type="date"
                            class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer"
                            :class="scheduleForm.errors.survey_date ? 'border-error' : 'border-outline/40'"
                        />
                        <p v-if="scheduleForm.errors.survey_date" class="text-xs text-error">{{ scheduleForm.errors.survey_date }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Type</label>
                        <select
                            v-model="scheduleForm.design_plan_type"
                            :disabled="isTypeLocked"
                            class="w-full appearance-none bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                            :class="scheduleForm.errors.design_plan_type ? 'border-error' : 'border-outline/40'"
                        >
                            <option value="" disabled>Select type…</option>
                            <option v-for="type in designPlanTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                        <p v-if="scheduleForm.errors.design_plan_type" class="text-xs text-error">{{ scheduleForm.errors.design_plan_type }}</p>
                        <p v-else-if="isTypeLocked" class="text-xs text-on-surface-variant/70">Locked — the Plans/Drawing checklist has already been generated.</p>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Remarks</label>
                    <textarea
                        v-model="scheduleForm.remarks"
                        rows="2"
                        placeholder="Optional notes for the surveyor…"
                        class="w-full bg-surface-container-low border rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none"
                        :class="scheduleForm.errors.remarks ? 'border-error' : 'border-outline/40'"
                    />
                    <p v-if="scheduleForm.errors.remarks" class="text-xs text-error">{{ scheduleForm.errors.remarks }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="scheduleForm.processing"
                    class="w-full flex items-center justify-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-sm"
                >
                    <span v-if="scheduleForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-[16px]">event_available</span>
                    Schedule Site Survey
                </button>
            </form>
        </template>
    </div>
</template>
