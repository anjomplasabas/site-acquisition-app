<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StagePanel from '@/Components/Projects/StagePanel.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    project: Object,
    sitePlanningAssignment: { type: Object, default: null },
    projectStatuses: Array,
    stages: Array,
    stageFieldDefinitions: Object,
    stageDocumentRequirements: Object,
    requiredStageDocumentRequirements: Object,
    users: Array,
    can: Object,
    networkSiteAssignmentId: { type: [String, Number], default: null },
})

// ── Project header form ──────────────────────────────────────────────────────
const projectForm = useForm({
    status: props.project.status,
})

function saveProjectDetails() {
    projectForm.patch(`/projects/${props.project.id}`, {
        onError: () => {
            projectForm.status = props.project.status
        },
    })
}

function projectStatusStyle(s) {
    return {
        Active: 'bg-emerald-100 text-emerald-800',
        'On Hold': 'bg-amber-100 text-amber-800',
        Completed: 'bg-primary/10 text-primary',
        Cancelled: 'bg-error/10 text-error',
    }[s] ?? ''
}

// ── Overall progress (stages completed) ───────────────────────────────────────
const overallProgress = computed(() => {
    const total = props.project.stages.length
    const done = props.project.stages.filter(s => s.status === 'Completed').length
    return { done, total, pct: total ? Math.round((done / total) * 100) : 0 }
})

// ── Stage stepper ─────────────────────────────────────────────────────────────
const selectedStageValue = ref(props.project.current_stage)

const selectedStage = computed(() =>
    props.project.stages.find(s => s.stage === selectedStageValue.value)
)

const isSelectedStageActive = computed(() => selectedStageValue.value === props.project.current_stage)

function stageStatusFor(stageValue) {
    return props.project.stages.find(s => s.stage === stageValue)?.status ?? 'Pending'
}

function stepStyle(stageValue) {
    const status = stageStatusFor(stageValue)
    if (status === 'Completed') return 'bg-emerald-500 text-white'
    if (stageValue === props.project.current_stage) return 'bg-primary text-white'
    return 'bg-surface-container-high text-on-surface-variant'
}

function canViewStage(stageValue) {
    const status = stageStatusFor(stageValue)
    return status === 'Completed' || stageValue === props.project.current_stage
}
</script>

<template>
    <Head :title="`${project.pipeline.name} — Project`" />

    <!-- Header -->
    <div class="flex items-start gap-4">
        <Link href="/projects" class="p-2 text-on-surface-variant hover:text-on-surface transition-colors mt-0.5">
            <span class="material-symbols-outlined">arrow_back</span>
        </Link>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight truncate">
                    {{ project.pipeline.name }}
                </h1>
                <span v-if="project.tower" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-orange-50 text-orange-700">
                    <span class="material-symbols-outlined text-[13px]">domain</span>
                    {{ project.tower.name }}
                </span>
                <span class="relative inline-flex items-center">
                    <select
                        v-model="projectForm.status"
                        :disabled="!can?.updateStatus"
                        @change="saveProjectDetails"
                        class="appearance-none text-xs font-bold pl-2.5 pr-6 py-0.5 rounded-full border-0 cursor-pointer hover:brightness-95 focus:outline-none focus:ring-1 focus:ring-primary/50 transition-all disabled:opacity-70"
                        :class="projectStatusStyle(projectForm.status)"
                    >
                        <option v-for="s in projectStatuses" :key="s" :value="s">{{ s }}</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-1.5 top-1/2 -translate-y-1/2 pointer-events-none text-[14px]">expand_more</span>
                </span>
            </div>
            <p v-if="projectForm.errors.status" class="text-xs text-error mt-1">{{ projectForm.errors.status }}</p>
            <p class="text-on-surface-variant text-sm mt-1">
                {{ [project.pipeline.site_profile?.name, project.pipeline.site_profile?.address?.city, project.pipeline.site_profile?.address?.province].filter(Boolean).join(' · ') }}
            </p>
            <p class="text-xs text-on-surface-variant font-mono mt-1.5 flex items-center flex-wrap gap-x-1.5 gap-y-1.5">
                <Link :href="`/pipelines/${project.pipeline.id}`" class="hover:text-primary hover:underline transition-colors">
                    {{ project.pipeline.pipeline_id }}
                </Link>
            </p>
        </div>

        <!-- Overall progress -->
        <div class="shrink-0 text-right">
            <p class="text-2xl font-bold text-on-surface">{{ overallProgress.pct }}%</p>
            <p class="text-xs text-on-surface-variant">{{ overallProgress.done }}/{{ overallProgress.total }} stages</p>
            <div class="w-32 h-1.5 bg-surface-container-high rounded-full overflow-hidden mt-1.5">
                <div class="h-full bg-primary rounded-full transition-all" :style="{ width: `${overallProgress.pct}%` }" />
            </div>
        </div>
    </div>

    <!-- Stage stepper -->
    <div class="flex items-center gap-2 bg-surface-container-low rounded-xl p-2 overflow-x-auto">
        <template v-for="(stage, index) in stages" :key="stage.value">
            <button
                @click="canViewStage(stage.value) && (selectedStageValue = stage.value)"
                :disabled="!canViewStage(stage.value)"
                class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold transition-colors shrink-0"
                :class="[
                    selectedStageValue === stage.value ? 'ring-2 ring-primary/50' : '',
                    canViewStage(stage.value) ? 'cursor-pointer hover:brightness-95' : 'cursor-not-allowed opacity-50',
                ]"
            >
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[11px]" :class="stepStyle(stage.value)">
                    <span v-if="stageStatusFor(stage.value) === 'Completed'" class="material-symbols-outlined text-[13px]">check</span>
                    <span v-else>{{ index + 1 }}</span>
                </span>
                {{ stage.label }}
            </button>
            <span v-if="index < stages.length - 1" class="text-on-surface-variant/30 shrink-0">—</span>
        </template>
    </div>

    <!-- Selected stage panel -->
    <StagePanel
        v-if="selectedStage"
        :key="selectedStage.stage"
        :project="project"
        :site-planning-assignment="sitePlanningAssignment"
        :stage="selectedStage"
        :field-definitions="stageFieldDefinitions[selectedStage.stage] ?? []"
        :document-requirements="stageDocumentRequirements[selectedStage.stage] ?? []"
        :required-document-requirements="requiredStageDocumentRequirements[selectedStage.stage] ?? []"
        :users="users"
        :can="can"
        :readonly="!isSelectedStageActive"
        :network-site-assignment-id="networkSiteAssignmentId"
    />
</template>
