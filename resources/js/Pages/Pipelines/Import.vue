<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    previewRows: { type: Array, default: null },
    summary: { type: Object, default: null },
    columns: { type: Array, default: () => [] },
    suggestions: { type: Object, default: () => ({}) },
    tempPath: { type: String, default: null },
    fileExtension: { type: String, default: null },
})

const step = computed(() => {
    if (props.summary) return 'done'
    if (props.previewRows !== null) return 'preview'
    if (props.columns?.length) return 'mapping'
    return 'upload'
})

// ── Upload step ──────────────────────────────────────────────
const uploadForm = useForm({ file: null })

function upload() {
    uploadForm.post('/pipelines/import/upload', {
        forceFormData: true,
        onSuccess: () => uploadForm.reset('file'),
    })
}

// ── Mapping step ─────────────────────────────────────────────
const mappingForm = useForm({
    temp_path: '',
    file_extension: '',
    mapping: {},
})

// Inertia reuses the same component instance across redirects, so useForm won't
// reinitialize on its own. Watch for props.columns to populate the form with suggestions.
watch(
    () => props.columns,
    (columns) => {
        if (!columns?.length) return
        mappingForm.temp_path = props.tempPath ?? ''
        mappingForm.file_extension = props.fileExtension ?? ''
        mappingForm.mapping = Object.fromEntries(
            columns.map(col => [col.header, props.suggestions?.[col.header] ?? ''])
        )
    },
    { immediate: true }
)

function mappingStatus(header) {
    const current = mappingForm.mapping[header]
    if (!current) return 'skip'
    return current === (props.suggestions?.[header] ?? '') ? 'auto' : 'manual'
}

const mappedCount = computed(() =>
    Object.values(mappingForm.mapping).filter(v => !!v).length
)

function preview() {
    mappingForm.post('/pipelines/import/preview')
}

// ── Preview step ─────────────────────────────────────────────
const expandedRows = ref(new Set())

function toggleRow(rowNumber) {
    if (expandedRows.value.has(rowNumber)) {
        expandedRows.value.delete(rowNumber)
    } else {
        expandedRows.value.add(rowNumber)
    }
    expandedRows.value = new Set(expandedRows.value)
}

function filledFields(data) {
    return Object.entries(data ?? {}).filter(([, v]) => v !== '' && v !== null && v !== undefined)
}

const confirmForm = useForm({ rows: [] })

watch(
    () => props.previewRows,
    (previewRows) => {
        if (previewRows === null) return
        confirmForm.rows = (previewRows ?? []).map(row => ({
            row_number: row.row_number,
            data: row.data,
            site_action: row.site_status === 'matched'
                ? 'use_existing'
                : row.site_status === 'new' ? 'create_new' : '',
            site_profile_id: row.site_matches?.length === 1 ? row.site_matches[0].id : null,
            pipeline_action: row.pipeline_duplicate ? 'skip' : 'create',
        }))
    },
    { immediate: true }
)

function confirm() {
    confirmForm.post('/pipelines/import/confirm')
}

// ── Field groups for mapping dropdown ────────────────────────
const fieldGroups = [
    {
        label: 'Pipeline',
        fields: ['pipeline_id', 'pipeline_name', 'endorsement_date', 'assigned_saq_email', 'assigned_saq_name'],
    },
    {
        label: 'Site',
        fields: ['site_profile_id', 'site_name', 'site_type', 'developer', 'total_unit_count'],
    },
    {
        label: 'Address',
        fields: ['street', 'barangay', 'city', 'province'],
    },
    {
        label: 'Contact',
        fields: ['contact_name', 'contact_designation', 'contact_email', 'contact_mobile_number'],
    },
    {
        label: 'Survey',
        fields: ['floors_surveyed', 'actual_unit_count', 'has_roof_access', 'equipment_notes', 'remarks'],
    },
    {
        label: 'Network',
        fields: [
            'building_network_system', 'indoor_outlet_capacity', 'isp_count_per_unit',
            'telco_room_location', 'electrical_room_location',
        ],
    },
    {
        label: 'Marketing',
        fields: [
            'lobby_booth_allowed', 'lobby_standee_allowed', 'lobby_flyers_stand_allowed',
            'roaming_sales_rep_allowed', 'marketing_paraphernalia', 'brochure_posting_location',
        ],
    },
    {
        label: 'Leasing',
        fields: ['telco_room_fee'],
    },
]

function fieldLabel(field) {
    return field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<template>
    <Head title="Import Pipelines" />

    <!-- Page header -->
    <div class="flex items-center gap-4">
        <a href="/pipelines" class="p-2 text-on-surface-variant hover:text-on-surface transition-colors">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div class="flex-1">
            <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">Import Leads</h1>
            <p class="text-on-surface-variant text-sm mt-1">Upload a CSV or Excel file, map its columns, and create new prospects.</p>
        </div>
        <a
            href="/pipelines/import/template"
            class="flex items-center gap-2 border border-outline/40 text-on-surface-variant font-semibold text-sm px-4 py-2.5 rounded-lg hover:bg-surface-container active:scale-[0.99] transition-all shrink-0"
        >
            <span class="material-symbols-outlined text-[18px]">download</span>
            Download Template
        </a>
    </div>

    <!-- Step indicator -->
    <div class="flex items-center gap-0 text-xs font-semibold select-none">
        <div v-for="(label, i) in ['Upload', 'Map Columns', 'Preview', 'Done']" :key="label" class="flex items-center gap-0">
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full transition-colors"
                :class="{
                    'bg-primary text-white': (step === 'upload' && i === 0) || (step === 'mapping' && i === 1) || (step === 'preview' && i === 2) || (step === 'done' && i === 3),
                    'text-on-surface-variant': !((step === 'upload' && i === 0) || (step === 'mapping' && i === 1) || (step === 'preview' && i === 2) || (step === 'done' && i === 3)),
                }">
                <span class="w-4 h-4 rounded-full border flex items-center justify-center text-[10px]"
                    :class="{
                        'bg-primary border-primary text-white': (step === 'upload' && i === 0) || (step === 'mapping' && i === 1) || (step === 'preview' && i === 2) || (step === 'done' && i === 3),
                        'border-outline/40 text-on-surface-variant': !((step === 'upload' && i === 0) || (step === 'mapping' && i === 1) || (step === 'preview' && i === 2) || (step === 'done' && i === 3)),
                    }">{{ i + 1 }}</span>
                {{ label }}
            </div>
            <div v-if="i < 3" class="w-6 h-px bg-outline/20" />
        </div>
    </div>

    <!-- ── Step 1: Upload ─────────────────────────────────────── -->
    <template v-if="step === 'upload'">
        <form @submit.prevent="upload" class="bg-surface-bright rounded-xl border border-outline/10 p-6 flex flex-col gap-4 max-w-2xl">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">
                    File <span class="normal-case font-normal">(CSV or Excel .xlsx)</span>
                </label>
                <input
                    type="file"
                    accept=".csv,.txt,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                    @change="uploadForm.file = $event.target.files[0]"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface"
                />
                <p v-if="uploadForm.errors.file" class="text-error text-xs mt-1">{{ uploadForm.errors.file }}</p>
            </div>
            <button
                type="submit"
                :disabled="uploadForm.processing || !uploadForm.file"
                class="self-start flex items-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 disabled:opacity-60"
            >
                <span v-if="uploadForm.processing" class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                <span v-else class="material-symbols-outlined text-[18px]">upload_file</span>
                {{ uploadForm.processing ? 'Detecting…' : 'Detect Columns' }}
            </button>
        </form>
    </template>

    <!-- ── Step 2: Map Columns ───────────────────────────────── -->
    <template v-if="step === 'mapping'">
        <div class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-outline/10 flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-on-surface">Column Mapping</p>
                    <p class="text-xs text-on-surface-variant mt-0.5">
                        Detected <span class="font-semibold text-on-surface">{{ columns.length }}</span> columns.
                        <span class="font-semibold text-primary">{{ mappedCount }}</span> mapped,
                        <span class="font-semibold text-on-surface-variant">{{ columns.length - mappedCount }}</span> skipped.
                    </p>
                </div>
                <button
                    @click="preview"
                    :disabled="mappingForm.processing || mappedCount === 0"
                    class="shrink-0 flex items-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 disabled:opacity-60"
                >
                    <span v-if="mappingForm.processing" class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-[18px]">fact_check</span>
                    {{ mappingForm.processing ? 'Processing…' : 'Preview Import' }}
                </button>
            </div>

            <!-- Legend -->
            <div class="px-6 py-2.5 border-b border-outline/10 bg-surface-container-low/50 flex items-center gap-4 text-[11px] text-on-surface-variant">
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span> Auto-suggested</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span> Manually adjusted</span>
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-outline/30 inline-block"></span> Skipped</span>
            </div>

            <!-- Mapping table -->
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left">
                    <thead>
                        <tr class="border-b border-outline/10">
                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface w-[28%]">File Column</th>
                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface w-[28%]">Sample Values</th>
                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface w-[36%]">Maps To</th>
                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface w-[8%]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline/5">
                        <tr v-for="col in columns" :key="col.header"
                            class="hover:bg-surface-container-low/40 transition-colors"
                            :class="{ 'opacity-60': mappingStatus(col.header) === 'skip' }">

                            <!-- File column name -->
                            <td class="px-5 py-3">
                                <p class="text-sm font-semibold text-on-surface">{{ col.header }}</p>
                            </td>

                            <!-- Sample values -->
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="sample in col.samples.slice(0, 2)"
                                        :key="sample"
                                        class="inline-block max-w-[140px] truncate text-[11px] bg-surface-container-high px-2 py-0.5 rounded text-on-surface"
                                        :title="sample"
                                    >{{ sample }}</span>
                                    <span v-if="!col.samples.length" class="text-[11px] text-on-surface-variant italic">no data</span>
                                </div>
                            </td>

                            <!-- Mapping dropdown -->
                            <td class="px-5 py-3">
                                <select
                                    v-model="mappingForm.mapping[col.header]"
                                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2 text-xs text-on-surface focus:outline-none focus:ring-1 focus:ring-primary/50"
                                >
                                    <option value="">— Skip this column —</option>
                                    <optgroup v-for="group in fieldGroups" :key="group.label" :label="group.label">
                                        <option v-for="field in group.fields" :key="field" :value="field">
                                            {{ fieldLabel(field) }}
                                        </option>
                                    </optgroup>
                                </select>
                            </td>

                            <!-- Status badge -->
                            <td class="px-5 py-3 text-center">
                                <span v-if="mappingStatus(col.header) === 'auto'"
                                    class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 uppercase tracking-wide">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> auto
                                </span>
                                <span v-else-if="mappingStatus(col.header) === 'manual'"
                                    class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 uppercase tracking-wide">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> set
                                </span>
                                <span v-else
                                    class="inline-flex items-center gap-1 text-[10px] font-bold text-on-surface-variant uppercase tracking-wide">
                                    <span class="w-1.5 h-1.5 rounded-full bg-outline/50"></span> skip
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer action -->
            <div class="px-6 py-4 border-t border-outline/10 flex justify-end">
                <button
                    @click="preview"
                    :disabled="mappingForm.processing || mappedCount === 0"
                    class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 disabled:opacity-60"
                >
                    <span v-if="mappingForm.processing" class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-[18px]">fact_check</span>
                    Preview Import
                </button>
            </div>
        </div>
    </template>

    <!-- ── Step 3: Preview ───────────────────────────────────── -->
    <template v-if="step === 'preview'">
        <div class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden">
            <div class="px-6 py-4 border-b border-outline/10 flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-on-surface">Preview Rows</p>
                    <p class="text-xs text-on-surface-variant">
                        {{ previewRows.length }} row(s) found. Resolve any ambiguous site matches before confirming.
                    </p>
                </div>
                <button
                    @click="confirm"
                    :disabled="confirmForm.processing"
                    class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 disabled:opacity-60"
                >
                    <span class="material-symbols-outlined text-[18px]">upload</span>
                    Confirm Import
                </button>
            </div>

            <div v-if="!previewRows.length" class="px-6 py-12 text-center text-sm text-on-surface-variant">
                No importable rows were found. All rows may have been empty or invalid.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left">
                    <thead>
                        <tr class="border-b border-outline/10 bg-surface-container-low">
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant w-10"></th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Row</th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline / Site</th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site Action</th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline Action</th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(row, index) in previewRows" :key="row.row_number">
                            <!-- Main row -->
                            <tr class="border-b border-outline/5 hover:bg-surface-container-low/40 transition-colors cursor-pointer"
                                @click="toggleRow(row.row_number)">
                                <td class="px-4 py-3 text-center">
                                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform"
                                        :class="{ 'rotate-180': expandedRows.has(row.row_number) }">
                                        expand_more
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm font-mono text-on-surface-variant">{{ row.row_number }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-on-surface">{{ row.data.pipeline_name || row.data.name || row.data.site_name }}</p>
                                    <p class="text-xs text-on-surface-variant">
                                        {{ [row.data.site_name, row.data.city, row.data.province].filter(Boolean).join(' · ') }}
                                    </p>
                                    <p class="text-xs text-on-surface-variant">{{ row.data.assigned_saq_email || row.data.assigned_saq_name }}</p>
                                    <p v-if="row.site_matches?.length" class="text-[10px] text-primary mt-0.5">
                                        {{ row.site_matches.length }} existing site match(es)
                                    </p>
                                </td>
                                <td class="px-4 py-3" @click.stop>
                                    <select v-model="confirmForm.rows[index].site_action"
                                        class="bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2 text-xs">
                                        <option value="create_new">Create new site</option>
                                        <option value="use_existing" :disabled="!row.site_matches?.length">Use existing site</option>
                                        <option value="skip">Skip</option>
                                    </select>
                                    <select
                                        v-if="confirmForm.rows[index].site_action === 'use_existing'"
                                        v-model="confirmForm.rows[index].site_profile_id"
                                        class="mt-2 bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2 text-xs w-full"
                                    >
                                        <option v-for="site in row.site_matches" :key="site.id" :value="site.id">
                                            {{ site.name }} · {{ site.city ?? '—' }}
                                        </option>
                                    </select>
                                </td>
                                <td class="px-4 py-3" @click.stop>
                                    <select v-model="confirmForm.rows[index].pipeline_action"
                                        class="bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2 text-xs">
                                        <option value="create">Create</option>
                                        <option value="reattempt">Create re-attempt</option>
                                        <option value="skip">Skip</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="row.errors.length" class="text-xs text-error">
                                        <p v-for="error in row.errors" :key="error">{{ error }}</p>
                                    </div>
                                    <span v-else-if="row.pipeline_duplicate" class="text-xs font-bold text-error">Cancel</span>
                                    <template v-else>
                                        <span class="text-xs font-bold text-emerald-700 capitalize">{{ row.site_status.replace('_', ' ') }}</span>
                                        <div v-if="row.warnings?.length" class="mt-1">
                                            <p v-for="warning in row.warnings" :key="warning" class="text-[10px] text-amber-700">{{ warning }}</p>
                                        </div>
                                    </template>
                                </td>
                            </tr>

                            <!-- Expanded field details -->
                            <tr v-if="expandedRows.has(row.row_number)" class="border-b border-outline/10 bg-surface-container-low/30">
                                <td colspan="6" class="px-6 py-4">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">
                                        All imported fields — {{ filledFields(row.data).length }} field(s)
                                    </p>
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-2">
                                        <div v-for="[key, value] in filledFields(row.data)" :key="key">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-on-surface-variant">
                                                {{ key.replaceAll('_', ' ') }}
                                            </p>
                                            <p class="text-xs text-on-surface mt-0.5 break-words">{{ value }}</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <!-- ── Step 4: Done ──────────────────────────────────────── -->
    <template v-if="step === 'done'">
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-px bg-outline/10 rounded-xl overflow-hidden border border-outline/10">
            <div v-for="(value, key) in summary" :key="key" class="bg-surface-bright p-5">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ key.replaceAll('_', ' ') }}</p>
                <p class="font-headline text-3xl font-bold text-on-surface mt-2">{{ value }}</p>
            </div>
        </div>
        <div class="flex gap-3">
            <a href="/pipelines" class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110">
                <span class="material-symbols-outlined text-[18px]">list</span>
                View Pipelines
            </a>
            <a href="/pipelines/import" class="flex items-center gap-2 border border-outline/40 text-on-surface-variant font-semibold text-sm px-5 py-2.5 rounded-lg hover:bg-surface-container">
                <span class="material-symbols-outlined text-[18px]">upload_file</span>
                Import Another File
            </a>
        </div>
    </template>
</template>
