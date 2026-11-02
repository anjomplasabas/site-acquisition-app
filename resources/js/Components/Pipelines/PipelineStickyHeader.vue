<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { statusStyle } from '@/utils/pipeline.js'
import { currentPipelineStep, PIPELINE_STEPS, pipelineStepState } from '@/utils/pipelineWorkflow.js'

const props = defineProps({
    pipeline: {
        type: Object,
        required: true,
    },
    error: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['dismiss-error'])
const isScrolled = ref(false)
const currentStepIndex = computed(() => currentPipelineStep(props.pipeline.status))

function stepState(index) {
    return pipelineStepState(props.pipeline.status, index)
}

function onScroll() {
    if (!isScrolled.value && window.scrollY > 220) {
        isScrolled.value = true
    } else if (isScrolled.value && window.scrollY < 80) {
        isScrolled.value = false
    }
}

onMounted(() => {
    document.documentElement.style.overflowAnchor = 'none'
    window.addEventListener('scroll', onScroll, { passive: true })
})

onUnmounted(() => {
    document.documentElement.style.overflowAnchor = ''
    window.removeEventListener('scroll', onScroll)
})
</script>

<template>
    <div class="sticky top-[72px] z-10 -mx-4 flex flex-col gap-6 bg-background px-4 pb-2 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" style="overflow-anchor: none">
        <div v-if="!isScrolled">
            <nav class="mb-3 flex items-center gap-1.5 text-sm text-on-surface-variant">
                <a href="/pipelines" class="transition-colors hover:text-on-surface">Pipelines</a>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="font-semibold text-primary">{{ pipeline.pipeline_id }}</span>
            </nav>
            <div class="flex flex-wrap items-start gap-2 sm:gap-3">
                <h1 class="font-headline text-2xl font-extrabold tracking-tight text-on-surface sm:text-3xl">{{ pipeline.name }}</h1>
                <span class="mt-1 inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold" :class="statusStyle(pipeline.status).badge">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="statusStyle(pipeline.status).dot"></span>
                    {{ pipeline.status }}
                </span>
                <span v-if="pipeline.aging_badge" class="mt-1 inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-1 text-[10px] font-bold" :class="pipeline.aging_badge.classes">
                    <span class="material-symbols-outlined text-[12px]">schedule</span>
                    {{ pipeline.aging_badge.label }} no activity
                </span>
                <span v-if="pipeline.stage_context" class="mt-1 inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-1 text-[10px] font-bold" :class="pipeline.stage_context.classes" :title="`${pipeline.stage_context.stage_age_label} in ${pipeline.status}`">
                    <span class="material-symbols-outlined text-[12px]">hourglass_top</span>
                    {{ pipeline.stage_context.stage_age_days }}d in stage
                </span>
            </div>
            <p class="mt-1 font-mono text-sm text-on-surface-variant">{{ pipeline.pipeline_id }}</p>
        </div>

        <div v-else class="flex items-center gap-2 sm:gap-3">
            <h2 class="truncate font-headline text-base font-extrabold tracking-tight text-on-surface">{{ pipeline.name }}</h2>
            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-bold" :class="statusStyle(pipeline.status).badge">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="statusStyle(pipeline.status).dot"></span>
                {{ pipeline.status }}
            </span>
            <span class="shrink-0 font-mono text-xs text-on-surface-variant">{{ pipeline.pipeline_id }}</span>
        </div>

        <div v-if="error && !isScrolled" class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-3.5 text-sm font-medium text-red-700">
            <span class="material-symbols-outlined text-[18px]">error</span>
            {{ error }}
            <button type="button" class="ml-auto text-red-500 transition-colors hover:text-red-700" @click="emit('dismiss-error')">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>

        <div :class="isScrolled ? 'py-2.5' : 'py-5'" class="overflow-x-auto rounded-xl border border-outline/10 bg-surface-bright px-4 sm:px-8">
            <div class="flex min-w-[480px] items-center">
                <template v-for="(step, index) in PIPELINE_STEPS" :key="step.status">
                    <div class="flex shrink-0 items-center" :class="isScrolled ? 'gap-1' : 'flex-col gap-2'">
                        <div class="flex items-center justify-center rounded-full font-bold transition-all" :class="[
                            isScrolled ? 'h-5 w-5 text-[10px]' : 'h-8 w-8 text-[13px]',
                            stepState(index) === 'active' ? 'bg-primary text-white shadow-sm' : '',
                            stepState(index) === 'done' ? 'bg-emerald-500 text-white' : '',
                            stepState(index) === 'upcoming' ? 'bg-surface-container text-on-surface-variant' : '',
                        ]">
                            <span v-if="stepState(index) === 'done'" class="material-symbols-outlined" :class="isScrolled ? 'text-[11px]' : 'text-[16px]'">check</span>
                            <span v-else>{{ index + 1 }}</span>
                        </div>
                        <span v-if="!isScrolled || stepState(index) === 'active'" class="whitespace-nowrap text-[10px] font-bold uppercase tracking-widest" :class="[
                            isScrolled ? 'ml-1' : '',
                            stepState(index) === 'active' ? 'text-primary' : '',
                            stepState(index) === 'done' ? 'text-emerald-600' : '',
                            stepState(index) === 'upcoming' ? 'text-on-surface-variant/50' : '',
                        ]">{{ step.label }}</span>
                    </div>
                    <div v-if="index < PIPELINE_STEPS.length - 1" class="mx-2 h-0.5 flex-1 rounded-full" :class="index < currentStepIndex ? 'bg-emerald-400' : 'bg-outline/15'"></div>
                </template>
            </div>
        </div>
    </div>
</template>
