<script setup>
import { formatDate } from '@/utils/pipeline.js'

defineProps({
    pipeline: Object,
})
</script>

<template>
    <div v-if="pipeline.site_survey" class="bg-surface-bright rounded-xl border border-outline/10 p-6">
        <div class="flex items-center justify-between mb-4">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site Planning</p>
            <span
                v-if="pipeline.site_survey.survey_completed_at"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700"
            >
                <span class="material-symbols-outlined text-[13px]">check_circle</span>
                Survey Completed
            </span>
            <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-surface-container text-on-surface-variant">
                Survey Pending
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 mb-5">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Surveyor</p>
                <p class="text-sm text-on-surface">{{ pipeline.site_survey.surveyor?.full_name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Survey Date</p>
                <p class="text-sm text-on-surface">{{ pipeline.site_survey.survey_date ? formatDate(pipeline.site_survey.survey_date) : '—' }}</p>
            </div>
        </div>

        <div class="pt-4 border-t border-outline/10">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Basic Info</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-8 gap-y-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Floors Surveyed</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.floors_surveyed ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Actual Unit Count</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.actual_unit_count ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Roof Access</p>
                    <p class="text-sm text-on-surface">
                        {{ pipeline.site_survey.has_roof_access === null ? '—' : pipeline.site_survey.has_roof_access ? 'Yes' : 'No' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-outline/10 mt-4">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Infrastructure</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Building Network System</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.building_network_system || '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">No. of Distribution Box Needed</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.indoor_outlet_capacity || '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">ISP Count per Unit</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.isp_count_per_unit ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Telco Room Location</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.telco_room_location || '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Electrical Room Location</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.electrical_room_location || '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Telco Room Fee</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.telco_room_fee || '—' }}</p>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-outline/10 mt-4">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Lobby Access</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-8 gap-y-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Booth</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.lobby_booth_allowed === null ? '—' : pipeline.site_survey.lobby_booth_allowed ? 'Yes' : 'No' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Standee</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.lobby_standee_allowed === null ? '—' : pipeline.site_survey.lobby_standee_allowed ? 'Yes' : 'No' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Flyers Stand</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.lobby_flyers_stand_allowed === null ? '—' : pipeline.site_survey.lobby_flyers_stand_allowed ? 'Yes' : 'No' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Roaming Rep</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.roaming_sales_rep_allowed === null ? '—' : pipeline.site_survey.roaming_sales_rep_allowed ? 'Yes' : 'No' }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 mt-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Marketing Paraphernalia</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.marketing_paraphernalia || '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Brochure Posting Location</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_survey.brochure_posting_location || '—' }}</p>
                </div>
            </div>
        </div>

        <div v-if="pipeline.site_survey.providers?.length" class="pt-4 border-t border-outline/10 mt-4">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Providers</p>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="provider in pipeline.site_survey.providers"
                    :key="provider.id"
                    class="flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-surface-container-low border border-outline/20"
                >
                    {{ provider.provider_name }}
                    <span class="text-[10px] font-bold uppercase text-on-surface-variant/60">{{ provider.status }}</span>
                </span>
            </div>
        </div>

        <div v-if="pipeline.site_survey.equipment_notes || pipeline.site_survey.remarks" class="pt-4 border-t border-outline/10 mt-4 grid grid-cols-1 gap-y-3">
            <div v-if="pipeline.site_survey.equipment_notes">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Equipment Notes</p>
                <p class="text-sm text-on-surface whitespace-pre-wrap">{{ pipeline.site_survey.equipment_notes }}</p>
            </div>
            <div v-if="pipeline.site_survey.remarks">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Survey Remarks</p>
                <p class="text-sm text-on-surface whitespace-pre-wrap">{{ pipeline.site_survey.remarks }}</p>
            </div>
        </div>

        <div v-if="(pipeline.site_planning_assignment?.site_planning_tasks ?? []).length" class="pt-4 border-t border-outline/10 mt-4">
            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Plans/Drawing</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Type</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_planning_assignment.design_plan_type ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Progress</p>
                    <p class="text-sm text-on-surface">{{ pipeline.site_planning_assignment.plans_drawing_progress_percent ?? 0 }}% done</p>
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-start gap-2 bg-blue-50 rounded-lg px-3 py-2.5">
            <span class="material-symbols-outlined text-blue-700 text-[16px] shrink-0">info</span>
            <p class="text-xs text-blue-700">
                <span class="font-bold">Note:</span> Plans and drawings are managed in the Site Planning module.
            </p>
        </div>
    </div>
</template>
