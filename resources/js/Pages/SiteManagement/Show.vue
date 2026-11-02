<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { formatDate, formatDateTime, statusStyle } from '@/utils/pipeline.js'

defineOptions({ layout: AppLayout })

const props = defineProps({
    site: Object,
})

function address(site) {
    return [site.address?.street, site.address?.barangay, site.address?.city, site.address?.province].filter(Boolean).join(', ') || '—'
}

function latestSurvey(site) {
    return site.pipelines?.map(pipeline => pipeline.site_survey).filter(Boolean).at(-1) ?? null
}

const selectedSurvey = computed(() => latestSurvey(props.site))

const networkFeasibilities = computed(() => selectedSurvey.value?.network_feasibilities ?? [])

const pipelinesWithApprovals = computed(() =>
    (props.site.pipelines ?? [])
        .filter(pipeline => pipeline.approvals?.length)
        .map(pipeline => ({
            ...pipeline,
            approvals: [...pipeline.approvals].sort((a, b) => a.step - b.step),
        }))
)

const statusHistory = computed(() =>
    (props.site.pipelines ?? [])
        .flatMap(pipeline => (pipeline.status_logs ?? [])
            .map(log => ({
                ...log,
                pipeline_name: pipeline.name,
                pipeline_code: pipeline.pipeline_id,
            })))
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
)

const visitLogs = computed(() =>
    (props.site.pipelines ?? [])
        .flatMap(pipeline => (pipeline.remark_logs ?? [])
            .filter(log => (log.type ?? 'visit') === 'visit')
            .map(log => ({
                ...log,
                pipeline_name: pipeline.name,
                pipeline_code: pipeline.pipeline_id,
            })))
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
)

const allDocuments = computed(() => {
    const seen = new Set()

    return (props.site.pipelines ?? [])
        .flatMap(pipeline => [
            ...(pipeline.site_survey?.documents ?? []).map(doc => ({ ...doc, pipeline_name: pipeline.name })),
            ...(pipeline.documents ?? []).map(doc => ({ ...doc, pipeline_name: pipeline.name })),
        ])
        .filter(doc => {
            if (seen.has(doc.id)) { return false }
            seen.add(doc.id)

            return true
        })
})

const viewingDoc = ref(null)

function documentUrl(doc) {
    return doc.url
}

function openDocument(doc) {
    if (doc.type === 'photo') {
        viewingDoc.value = doc
    } else {
        if (!documentUrl(doc)) { return }

        window.open(documentUrl(doc), '_blank')
    }
}

function documentIcon(doc) {
    return doc.type === 'photo' ? 'image' : 'description'
}
</script>

<template>
    <Head :title="site.name" />

    <div>
        <nav class="flex items-center gap-1.5 text-sm text-on-surface-variant mb-3">
            <a href="/site-management" class="hover:text-on-surface transition-colors">Projects</a>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-primary font-semibold">{{ site.name }}</span>
        </nav>
        <h1 class="font-headline text-3xl font-extrabold text-on-surface tracking-tight">{{ site.name }}</h1>
        <p class="text-on-surface-variant text-sm mt-1">{{ address(site) }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Site Information</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Type</p>
                        <p class="text-sm font-semibold text-on-surface">{{ site.type }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Developer</p>
                        <p class="text-sm font-semibold text-on-surface">{{ site.developer ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Total Unit per Tower</p>
                        <p class="text-sm font-semibold text-on-surface">{{ site.total_unit_count?.toLocaleString?.() ?? site.total_unit_count ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Total Floor per Tower</p>
                        <p class="text-sm font-semibold text-on-surface">{{ site.total_floors_per_tower ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Address</p>
                        <p class="text-sm font-semibold text-on-surface">{{ address(site) }}</p>
                    </div>
                    <div v-if="site.wifi_area_location">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Free WiFi Area/Location</p>
                        <p class="text-sm font-semibold text-on-surface">{{ site.wifi_area_location }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Linked Pipelines</p>
                <div v-if="site.pipelines?.length" class="divide-y divide-outline/10">
                    <a
                        v-for="pipeline in site.pipelines"
                        :key="pipeline.id"
                        :href="`/pipelines/${pipeline.id}`"
                        class="block py-4 first:pt-0 last:pb-0 hover:text-primary transition-colors"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-on-surface">{{ pipeline.name }}</p>
                                <p class="text-xs text-on-surface-variant">{{ pipeline.pipeline_id }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold shrink-0" :class="statusStyle(pipeline.status).badge">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(pipeline.status).dot"></span>
                                {{ pipeline.status }}
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Registered By</p>
                                <p class="text-xs text-on-surface">{{ pipeline.project_manager?.full_name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Assigned SAQ</p>
                                <p class="text-xs text-on-surface">{{ pipeline.assigned_saq?.full_name ?? 'Unassigned' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Assigned Surveyor</p>
                                <p class="text-xs text-on-surface">{{ pipeline.site_survey?.surveyor?.full_name ?? 'Unassigned' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Survey Visit Date</p>
                                <p class="text-xs text-on-surface">{{ pipeline.site_survey?.survey_date ? formatDate(pipeline.site_survey.survey_date) : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Endorsement Date</p>
                                <p class="text-xs text-on-surface">{{ pipeline.endorsement_date ? formatDate(pipeline.endorsement_date) : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Last Stage Change</p>
                                <p class="text-xs text-on-surface">{{ pipeline.status_changed_at ? formatDateTime(pipeline.status_changed_at) : '—' }}</p>
                            </div>
                        </div>

                        <p v-if="pipeline.commercial_remarks" class="mt-3 text-xs text-on-surface whitespace-pre-wrap">
                            {{ pipeline.commercial_remarks }}
                        </p>
                    </a>
                </div>
                <p v-else class="text-xs text-on-surface-variant">No linked pipelines yet.</p>
            </div>

            <div v-if="selectedSurvey" class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Survey Findings</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 mb-5">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Assigned Surveyor</p>
                        <p class="text-sm text-on-surface">{{ selectedSurvey.surveyor?.full_name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Survey Date</p>
                        <p class="text-sm text-on-surface">{{ selectedSurvey.survey_date ? formatDate(selectedSurvey.survey_date) : '—' }}</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-outline/10">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Basic Info</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-8 gap-y-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Floors Surveyed</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.floors_surveyed ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Actual Unit Count</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.actual_unit_count ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Roof Access</p>
                            <p class="text-sm text-on-surface">
                                {{ selectedSurvey.has_roof_access === null ? '—' : selectedSurvey.has_roof_access ? 'Yes' : 'No' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-outline/10 mt-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Infrastructure</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Building Network System</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.building_network_system || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">No. of Distribution Box Needed</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.indoor_outlet_capacity || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">ISP Count per Unit</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.isp_count_per_unit ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Telco Room Location</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.telco_room_location || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Electrical Room Location</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.electrical_room_location || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Telco Room Fee</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.telco_room_fee || '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-outline/10 mt-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Lobby Access</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-8 gap-y-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Booth</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.lobby_booth_allowed === null ? '—' : selectedSurvey.lobby_booth_allowed ? 'Yes' : 'No' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Standee</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.lobby_standee_allowed === null ? '—' : selectedSurvey.lobby_standee_allowed ? 'Yes' : 'No' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Flyers Stand</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.lobby_flyers_stand_allowed === null ? '—' : selectedSurvey.lobby_flyers_stand_allowed ? 'Yes' : 'No' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Roaming Rep</p>
                            <p class="text-sm text-on-surface">{{ selectedSurvey.roaming_sales_rep_allowed === null ? '—' : selectedSurvey.roaming_sales_rep_allowed ? 'Yes' : 'No' }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 mt-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Marketing Paraphernalia</p>
                            <p class="text-sm text-on-surface whitespace-pre-wrap">{{ selectedSurvey.marketing_paraphernalia || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Brochure Posting Location</p>
                            <p class="text-sm text-on-surface whitespace-pre-wrap">{{ selectedSurvey.brochure_posting_location || '—' }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="selectedSurvey.providers?.length" class="pt-4 border-t border-outline/10 mt-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Providers</p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="provider in selectedSurvey.providers"
                            :key="provider.id"
                            class="flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-surface-container-low border border-outline/20"
                        >
                            {{ provider.provider_name }}
                        </span>
                    </div>
                </div>

                <div v-if="networkFeasibilities.length" class="pt-4 border-t border-outline/10 mt-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Network Feasibility</p>
                    <div class="grid grid-cols-1 gap-2">
                        <div
                            v-for="entry in networkFeasibilities"
                            :key="entry.id"
                            class="rounded-lg border border-outline/20 bg-surface-container-low px-3 py-3"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-on-surface">{{ entry.isp_name }}</p>
                                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-primary">
                                    {{ entry.status }}
                                </span>
                            </div>
                            <p v-if="entry.notes" class="mt-2 text-xs text-on-surface-variant whitespace-pre-wrap">{{ entry.notes }}</p>
                            <p class="mt-2 text-[10px] text-on-surface-variant">Updated {{ formatDateTime(entry.updated_at) }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="selectedSurvey.equipment_notes || selectedSurvey.remarks" class="pt-4 border-t border-outline/10 mt-4 grid grid-cols-1 gap-y-3">
                    <div v-if="selectedSurvey.equipment_notes">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Equipment Notes</p>
                        <p class="text-sm text-on-surface whitespace-pre-wrap">{{ selectedSurvey.equipment_notes }}</p>
                    </div>
                    <div v-if="selectedSurvey.remarks">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60 mb-1">Survey Remarks</p>
                        <p class="text-sm text-on-surface whitespace-pre-wrap">{{ selectedSurvey.remarks }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Activity History</p>
                <div v-if="visitLogs.length" class="flex flex-col divide-y divide-outline/10">
                    <div v-for="log in visitLogs" :key="log.id" class="py-3 first:pt-0 last:pb-0">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                {{ log.status }}
                            </span>
                            <span class="text-[10px] text-on-surface-variant shrink-0">{{ formatDateTime(log.created_at) }}</span>
                        </div>
                        <p class="text-sm text-on-surface leading-relaxed">{{ log.content }}</p>
                        <div class="flex items-center gap-2 mt-1 text-xs text-on-surface-variant flex-wrap">
                            <span>— {{ log.added_by?.full_name ?? '—' }}</span>
                            <span>·</span>
                            <span>{{ log.pipeline_name }}</span>
                        </div>
                    </div>
                </div>
                <p v-else class="text-xs text-on-surface-variant/60 text-sm">No visits recorded yet.</p>
            </div>

            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Approval History</p>
                <div v-if="pipelinesWithApprovals.length" class="flex flex-col gap-4">
                    <div
                        v-for="pipeline in pipelinesWithApprovals"
                        :key="pipeline.id"
                        class="rounded-lg border border-outline/20 bg-surface-container-low p-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div>
                                <p class="text-sm font-semibold text-on-surface">{{ pipeline.name }}</p>
                                <p class="text-xs text-on-surface-variant">{{ pipeline.pipeline_id }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold shrink-0" :class="statusStyle(pipeline.status).badge">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(pipeline.status).dot"></span>
                                {{ pipeline.status }}
                            </span>
                        </div>
                        <div class="divide-y divide-outline/10">
                            <div v-for="approval in pipeline.approvals" :key="approval.id" class="py-3 first:pt-0 last:pb-0">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs font-bold text-on-surface">Step {{ approval.step }} · {{ approval.role?.name ?? '—' }}</p>
                                    <span class="rounded-full bg-surface-bright px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                                        {{ approval.status }}
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-on-surface-variant">
                                    <span>{{ approval.decided_by?.full_name ?? 'Pending' }}</span>
                                    <span v-if="approval.decided_at">· {{ formatDateTime(approval.decided_at) }}</span>
                                </div>
                                <p v-if="approval.remarks" class="mt-2 text-xs text-on-surface whitespace-pre-wrap">{{ approval.remarks }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-xs text-on-surface-variant">No approval history recorded yet.</p>
            </div>

            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Status History</p>
                <div v-if="statusHistory.length" class="flex flex-col divide-y divide-outline/10">
                    <div v-for="log in statusHistory" :key="log.id" class="py-3 first:pt-0 last:pb-0">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                                <span class="w-1.5 h-1.5 rounded-full bg-primary shrink-0"></span>
                                {{ log.status }}
                            </span>
                            <span class="text-[10px] text-on-surface-variant shrink-0">{{ formatDateTime(log.created_at) }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-on-surface-variant">
                            <span>{{ log.changed_by?.full_name ?? '—' }}</span>
                            <span>·</span>
                            <span>{{ log.pipeline_name }}</span>
                        </div>
                    </div>
                </div>
                <p v-else class="text-xs text-on-surface-variant">No status changes recorded yet.</p>
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Contact</p>
                <template v-if="site.contact">
                    <p class="text-sm font-semibold text-on-surface">{{ site.contact.name }}</p>
                    <p class="text-xs text-on-surface-variant mt-1">{{ site.contact.designation ?? '—' }}</p>
                    <div class="mt-4 text-sm text-on-surface">
                        <p>{{ site.contact.mobile_number ?? '—' }}</p>
                        <p class="text-on-surface-variant">{{ site.contact.email ?? '—' }}</p>
                    </div>
                </template>
                <p v-else class="text-xs text-on-surface-variant">No contact assigned.</p>
            </div>

            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Documents History</p>
                <div v-if="allDocuments.length" class="flex flex-col divide-y divide-outline/10">
                    <button
                        v-for="doc in allDocuments"
                        :key="doc.id"
                        type="button"
                        @click="openDocument(doc)"
                        class="py-3 first:pt-0 last:pb-0 flex items-start gap-3 w-full text-left hover:bg-surface-container-low rounded-lg px-2 -mx-2 transition-colors group"
                    >
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0 mt-0.5 group-hover:bg-primary/20 transition-colors">
                            <span class="material-symbols-outlined text-[16px] text-primary">{{ documentIcon(doc) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-on-surface truncate group-hover:text-primary transition-colors">{{ doc.name }}</p>
                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ doc.uploaded_status ?? '—' }}</span>
                                <span v-if="doc.version > 1" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary">v{{ doc.version }}</span>
                                <span v-if="doc.category" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary">{{ doc.category }}</span>
                                <span class="text-[10px] text-on-surface-variant">· {{ doc.uploaded_by?.full_name ?? '—' }}</span>
                                <span class="text-[10px] text-on-surface-variant">· {{ doc.pipeline_name }}</span>
                                <span class="text-[10px] text-on-surface-variant">· {{ formatDateTime(doc.pivot?.created_at) }}</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[14px] text-on-surface-variant/40 group-hover:text-primary shrink-0 mt-1 transition-colors">
                            {{ doc.type === 'photo' ? 'open_in_full' : 'open_in_new' }}
                        </span>
                    </button>
                </div>
                <p v-else class="text-xs text-on-surface-variant">No documents uploaded yet.</p>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="viewingDoc"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
            @click.self="viewingDoc = null"
        >
            <div class="relative flex flex-col bg-surface-bright rounded-xl overflow-hidden shadow-2xl w-full max-w-4xl max-h-[90vh]">
                <div class="flex items-center justify-between px-4 py-3 border-b border-outline/10 shrink-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="material-symbols-outlined text-[16px] text-on-surface-variant shrink-0">image</span>
                        <span class="text-sm font-semibold text-on-surface truncate">{{ viewingDoc.name }}</span>
                    </div>
                    <button
                        @click="viewingDoc = null"
                        class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors shrink-0 ml-3"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
                <div class="overflow-auto flex-1 flex items-center justify-center p-4 bg-black/30 min-h-0">
                    <img
                        :src="documentUrl(viewingDoc)"
                        :alt="viewingDoc.name"
                        class="max-w-full max-h-full object-contain rounded"
                    />
                </div>
            </div>
        </div>
    </Teleport>
</template>
