<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatDate, formatDateTime, statusStyle, getInitials } from '@/utils/pipeline.js'
import NetworkFeasibilityCard from '@/Components/Network/NetworkFeasibilityCard.vue'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    assignment: Object,
    providerStatuses: Array,
    activityLogs: Array,
    can: Object,
})

const pipeline = props.assignment.pipeline
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <a
                href="/network"
                class="w-10 h-10 rounded-xl flex items-center justify-center text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors shrink-0"
            >
                <span class="material-symbols-outlined text-[22px]">arrow_back</span>
            </a>
            <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center shrink-0 text-sm font-bold text-blue-700">
                {{ getInitials(pipeline.name) }}
            </div>
            <div>
                <h1 class="text-xl font-bold text-on-surface">{{ pipeline.name }}</h1>
                <p class="text-sm text-on-surface-variant mt-0.5">{{ pipeline.pipeline_id }}</p>
            </div>
        </div>
        <span
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-xs font-bold shrink-0"
            :class="statusStyle(pipeline.status).badge"
        >
            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(pipeline.status).dot"></span>
            {{ pipeline.status }}
        </span>
    </div>

    <!-- Site Profile (read-only) -->
    <div class="bg-surface-bright rounded-2xl border border-outline/10 p-7">
        <div class="flex items-center gap-2.5 mb-6">
            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">apartment</span>
            <p class="text-sm font-bold text-on-surface">Site Profile</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-10 gap-y-5">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Site Name</p>
                <p class="text-sm font-semibold text-on-surface">{{ pipeline.site_profile?.name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Type</p>
                <p class="text-sm font-semibold text-on-surface">{{ pipeline.site_profile?.type ?? '—' }}</p>
            </div>
            <div class="sm:col-span-2" v-if="pipeline.site_profile?.address">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Address</p>
                <p class="text-sm text-on-surface">
                    {{ [pipeline.site_profile.address.street, pipeline.site_profile.address.barangay, pipeline.site_profile.address.city, pipeline.site_profile.address.province].filter(Boolean).join(', ') || '—' }}
                </p>
            </div>
        </div>

        <div v-if="pipeline.site_profile?.contact" class="mt-6 pt-6 border-t border-outline/10">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Contact Person</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-10 gap-y-4">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Name</p>
                    <p class="text-sm font-semibold text-on-surface">{{ pipeline.site_profile.contact.name }}</p>
                    <p v-if="pipeline.site_profile.contact.designation" class="text-xs text-on-surface-variant mt-0.5">{{ pipeline.site_profile.contact.designation }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Contact</p>
                    <p v-if="pipeline.site_profile.contact.mobile_number" class="text-sm text-on-surface">{{ pipeline.site_profile.contact.mobile_number }}</p>
                    <p v-if="pipeline.site_profile.contact.email" class="text-xs text-on-surface-variant mt-0.5">{{ pipeline.site_profile.contact.email }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Survey Findings (read-only) -->
    <div v-if="pipeline.site_survey" class="bg-surface-bright rounded-2xl border border-outline/10 p-7">
        <div class="flex items-center gap-2.5 mb-6">
            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">fact_check</span>
            <p class="text-sm font-bold text-on-surface">Survey Findings</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-10 gap-y-5 mb-6">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Surveyor</p>
                <p class="text-sm text-on-surface">{{ pipeline.site_survey.surveyor?.full_name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Survey Date</p>
                <p class="text-sm text-on-surface">{{ pipeline.site_survey.survey_date ? formatDate(pipeline.site_survey.survey_date) : '—' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-10 gap-y-5 pt-6 border-t border-outline/10">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Building Network System</p>
                <p class="text-sm text-on-surface">{{ pipeline.site_survey.building_network_system || '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Telco Room Location</p>
                <p class="text-sm text-on-surface">{{ pipeline.site_survey.telco_room_location || '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1.5">Electrical Room Location</p>
                <p class="text-sm text-on-surface">{{ pipeline.site_survey.electrical_room_location || '—' }}</p>
            </div>
        </div>

        <div v-if="pipeline.site_survey.providers?.length" class="pt-6 mt-6 border-t border-outline/10">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">ISP Providers Noted at Survey</p>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="provider in pipeline.site_survey.providers"
                    :key="provider.id"
                    class="text-xs font-semibold px-3 py-1.5 rounded-full bg-surface-container text-on-surface-variant"
                >{{ provider.provider_name }}</span>
            </div>
        </div>
    </div>

    <!-- Documents inherited from Commercials once the pipeline is approved (read-only) -->
    <div v-if="assignment.documents?.length" class="bg-surface-bright rounded-2xl border border-outline/10 p-7">
        <div class="flex items-center gap-2.5 mb-6">
            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">folder_copy</span>
            <p class="text-sm font-bold text-on-surface">Documents</p>
        </div>
        <div class="flex flex-col divide-y divide-outline/10">
            <div v-for="document in assignment.documents" :key="document.id" class="py-3">
                <DocumentRow :document="document">
                    <template #trailing>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant shrink-0">{{ document.category }}</span>
                    </template>
                </DocumentRow>
            </div>
        </div>
    </div>

    <NetworkFeasibilityCard
        :assignment="assignment"
        :provider-statuses="providerStatuses"
        :can-manage-feasibility="can?.manageFeasibility"
        :can-complete-feasibility="can?.completeFeasibility"
    />

    <!-- Activity History -->
    <div class="bg-surface-bright rounded-2xl border border-outline/10 p-7">
        <div class="flex items-center gap-2.5 mb-6">
            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">history</span>
            <p class="text-sm font-bold text-on-surface">Activity History</p>
        </div>

        <div v-if="activityLogs.length" class="flex flex-col divide-y divide-outline/10">
            <div v-for="log in activityLogs" :key="log.id" class="py-4 flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-on-surface">{{ log.description }}</p>
                    <p class="text-xs text-on-surface-variant mt-1">{{ log.user?.full_name ?? 'System' }}</p>
                </div>
                <p class="text-xs text-on-surface-variant shrink-0">{{ formatDateTime(log.created_at) }}</p>
            </div>
        </div>
        <p v-else class="text-sm text-on-surface-variant/60">No activity recorded yet.</p>
    </div>
</template>
