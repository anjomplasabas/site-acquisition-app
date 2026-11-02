<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    activeConfigs: Array,
    roles: Array,
    stageDocumentRequirements: Array,
    pipelineStages: Array,
    projectStages: Array,
    alarmThresholds: Object,
})

const editingStep = ref(null)
const editingStageRequirement = ref(false)

const nextStep = computed(() => {
    const steps = props.activeConfigs.map(config => Number(config.step))
    return steps.length ? Math.max(...steps) + 1 : 1
})

const approvalForm = useForm({ role_id: '' })
const removeApprovalForm = useForm({})

const alarmForm = useForm({
    yellow_days: props.alarmThresholds?.yellow_days ?? 2,
    red_days: props.alarmThresholds?.red_days ?? 4,
})

const stageRequirementForm = useForm({
    module: 'pipeline',
    stage: 'Prospecting',
    name: '',
    is_required: true,
    is_active: true,
    sort_order: 0,
})
const deleteStageRequirementForm = useForm({})

const stagesForModule = computed(() =>
    stageRequirementForm.module === 'project' ? props.projectStages : props.pipelineStages
)

function onModuleChange() {
    stageRequirementForm.stage = stagesForModule.value[0] ?? ''
}

function roleLabel(name) {
    return name?.replace(/_/g, ' ') ?? '-'
}

function startApprovalEdit(config = null) {
    editingStep.value = config?.step ?? nextStep.value
    approvalForm.role_id = config?.role_id ?? ''
}

function cancelApprovalEdit() {
    editingStep.value = null
    approvalForm.reset()
}

function submitApproval() {
    approvalForm.post(`/settings/approvals/${editingStep.value}`, {
        onSuccess: cancelApprovalEdit,
    })
}

function removeApproval(step) {
    removeApprovalForm.delete(`/settings/approvals/${step}`)
}

function submitAlarmThresholds() {
    alarmForm.patch('/settings/alarm-thresholds')
}

function startStageRequirementEdit(requirement = null) {
    editingStageRequirement.value = requirement
    stageRequirementForm.module = requirement?.module ?? 'pipeline'
    stageRequirementForm.stage = requirement?.stage ?? 'Prospecting'
    stageRequirementForm.name = requirement?.name ?? ''
    stageRequirementForm.is_required = requirement?.is_required ?? true
    stageRequirementForm.is_active = requirement?.is_active ?? true
    stageRequirementForm.sort_order = requirement?.sort_order ?? 0
}

function cancelStageRequirementEdit() {
    editingStageRequirement.value = false
    stageRequirementForm.reset()
    stageRequirementForm.module = 'pipeline'
    stageRequirementForm.stage = 'Prospecting'
    stageRequirementForm.is_required = true
    stageRequirementForm.is_active = true
    stageRequirementForm.sort_order = 0
}

function submitStageRequirement() {
    const options = { onSuccess: cancelStageRequirementEdit }

    if (editingStageRequirement.value) {
        stageRequirementForm.patch(`/settings/stage-document-requirements/${editingStageRequirement.value.id}`, options)
        return
    }

    stageRequirementForm.post('/settings/stage-document-requirements', options)
}

function deactivateStageRequirement(requirement) {
    deleteStageRequirementForm.delete(`/settings/stage-document-requirements/${requirement.id}`)
}

</script>

<template>
    <Head title="Approval Settings" />

    <div>
        <nav class="flex items-center gap-1.5 text-sm text-on-surface-variant mb-3">
            <span class="text-primary font-semibold">Settings</span>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span>Workflow Configuration</span>
        </nav>
        <h1 class="font-headline text-2xl sm:text-3xl font-extrabold text-on-surface tracking-tight">Workflow Configuration</h1>
        <p class="text-on-surface-variant text-sm mt-1 max-w-xl">
            Configure approval steps, inactivity alarms, and required documents by stage.
        </p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <section class="bg-surface-bright rounded-xl border border-outline/10 p-6 flex flex-col gap-5">
            <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site Inactivity Alarms</p>
                    <p class="text-xs text-on-surface-variant mt-1">Badges are based on days since the last meaningful site update.</p>
            </div>

            <form @submit.prevent="submitAlarmThresholds" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Yellow after days</label>
                    <input v-model="alarmForm.yellow_days" type="number" min="1" class="mt-1 w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2 text-sm" />
                    <p v-if="alarmForm.errors.yellow_days" class="text-xs text-error mt-1">{{ alarmForm.errors.yellow_days }}</p>
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Red after days</label>
                    <input v-model="alarmForm.red_days" type="number" min="1" class="mt-1 w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2 text-sm" />
                    <p v-if="alarmForm.errors.red_days" class="text-xs text-error mt-1">{{ alarmForm.errors.red_days }}</p>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" :disabled="alarmForm.processing" class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-4 py-2 rounded-lg disabled:opacity-60">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        Save Thresholds
                    </button>
                </div>
            </form>
        </section>

        <section class="bg-surface-bright rounded-xl border border-outline/10 p-6 flex flex-col gap-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Required Documents By Stage</p>
                    <p class="text-xs text-on-surface-variant mt-1">Required active categories block stage transitions until uploaded.</p>
                </div>
                <button @click="startStageRequirementEdit()" class="flex items-center gap-1.5 bg-primary text-white font-semibold text-sm px-4 py-2 rounded-lg hover:brightness-110 transition-all">
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Add
                </button>
            </div>

            <form v-if="editingStageRequirement !== false" @submit.prevent="submitStageRequirement" class="flex flex-col gap-3 bg-surface-container-low rounded-lg border border-outline/20 p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="relative">
                        <select v-model="stageRequirementForm.module" @change="onModuleChange" class="w-full appearance-none bg-surface-bright border border-outline/40 rounded-lg px-4 py-2.5 text-sm pr-10">
                            <option value="pipeline">Pipeline stage</option>
                            <option value="project">Project stage</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
                    </div>
                    <div class="relative">
                        <select v-model="stageRequirementForm.stage" class="w-full appearance-none bg-surface-bright border border-outline/40 rounded-lg px-4 py-2.5 text-sm pr-10">
                            <option v-for="stage in stagesForModule" :key="stage" :value="stage">{{ stage }}</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
                    </div>
                    <input v-model="stageRequirementForm.name" type="text" placeholder="Document category" class="w-full bg-surface-bright border border-outline/40 rounded-lg px-4 py-2.5 text-sm sm:col-span-2" />
                </div>
                <p v-if="stageRequirementForm.errors.name" class="text-xs text-error">{{ stageRequirementForm.errors.name }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 text-sm text-on-surface">
                        <input v-model="stageRequirementForm.is_required" type="checkbox" class="accent-primary" />
                        Required
                    </label>
                    <label class="flex items-center gap-2 text-sm text-on-surface">
                        <input v-model="stageRequirementForm.is_active" type="checkbox" class="accent-primary" />
                        Active
                    </label>
                </div>
                <div class="flex gap-2">
                    <button type="submit" :disabled="stageRequirementForm.processing" class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-4 py-2 rounded-lg disabled:opacity-60">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        Save
                    </button>
                    <button type="button" @click="cancelStageRequirementEdit" class="border border-outline/40 text-on-surface-variant font-semibold text-sm px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                </div>
            </form>

            <div class="flex flex-col divide-y divide-outline/10">
                <div v-for="requirement in stageDocumentRequirements" :key="requirement.id" class="py-3 first:pt-0 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-on-surface truncate">{{ requirement.name }}</p>
                        <div class="flex flex-wrap gap-1.5 mt-1">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-outline/10 text-on-surface-variant capitalize">{{ requirement.module }}</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary">{{ requirement.stage }}</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="requirement.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-outline/10 text-on-surface-variant'">
                                {{ requirement.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="startStageRequirementEdit(requirement)" class="text-xs font-bold text-primary px-2 py-1 rounded-lg hover:bg-primary/5">Edit</button>
                        <button v-if="requirement.is_active" @click="deactivateStageRequirement(requirement)" :disabled="deleteStageRequirementForm.processing" class="text-xs font-bold text-error px-2 py-1 rounded-lg hover:bg-error/5 disabled:opacity-50">Deactivate</button>
                    </div>
                </div>
                <p v-if="stageDocumentRequirements.length === 0" class="text-sm text-on-surface-variant py-6 text-center">No stage document requirements configured.</p>
            </div>
        </section>

        <section class="bg-surface-bright rounded-xl border border-outline/10 p-6 flex flex-col gap-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Approval Steps</p>
                    <p class="text-xs text-on-surface-variant mt-1">Steps run in ascending order.</p>
                </div>
                <button
                    @click="startApprovalEdit()"
                    class="flex items-center gap-1.5 bg-primary text-white font-semibold text-sm px-4 py-2 rounded-lg hover:brightness-110 transition-all"
                >
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    Add Step
                </button>
            </div>

            <form v-if="editingStep" @submit.prevent="submitApproval" class="flex flex-col gap-3 bg-surface-container-low rounded-lg border border-outline/20 p-4">
                <p class="text-sm font-bold text-on-surface">Step {{ editingStep }}</p>
                <div class="relative">
                    <select
                        v-model="approvalForm.role_id"
                        class="w-full appearance-none bg-surface-bright border rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-10"
                        :class="approvalForm.errors.role_id ? 'border-error' : 'border-outline/40'"
                    >
                        <option value="" disabled>Select an approver role</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">{{ roleLabel(role.name) }}</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
                </div>
                <p v-if="approvalForm.errors.role_id" class="text-xs text-error">{{ approvalForm.errors.role_id }}</p>
                <div class="flex gap-2">
                    <button type="submit" :disabled="approvalForm.processing" class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-4 py-2 rounded-lg disabled:opacity-60">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        Save
                    </button>
                    <button type="button" @click="cancelApprovalEdit" class="border border-outline/40 text-on-surface-variant font-semibold text-sm px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                </div>
            </form>

            <div class="flex flex-col divide-y divide-outline/10">
                <div v-for="config in activeConfigs" :key="config.id" class="py-3 first:pt-0 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center text-xs font-bold shrink-0">{{ config.step }}</div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-on-surface truncate">{{ roleLabel(config.role?.name) }}</p>
                            <p class="text-xs text-on-surface-variant">Approval step {{ config.step }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="startApprovalEdit(config)" class="text-xs font-bold text-primary px-2 py-1 rounded-lg hover:bg-primary/5">Change</button>
                        <button @click="removeApproval(config.step)" :disabled="removeApprovalForm.processing" class="text-xs font-bold text-error px-2 py-1 rounded-lg hover:bg-error/5 disabled:opacity-50">Remove</button>
                    </div>
                </div>
                <p v-if="activeConfigs.length === 0" class="text-sm text-on-surface-variant py-6 text-center">No approval steps configured.</p>
            </div>
        </section>

    </div>
</template>
