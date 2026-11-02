<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'
import SiteSurveyTasksPanel from '@/Components/SitePlanning/SiteSurveyTasksPanel.vue'

const props = defineProps({
    assignment: Object,
    canFillSurvey: Boolean,
    canSubmitInspection: Boolean,
    documentTypes: { type: Array, default: () => [] },
    missingDocumentRequirements: { type: Array, default: () => [] },
})

const survey = props.assignment.site_survey ?? {}

// On a towered site, start from the totals the surveyor declared for this tower —
// still editable, since the on-site count may differ from the declared figures.
const tower = props.assignment.tower

const form = useForm({
    floors_surveyed: survey.floors_surveyed ?? tower?.total_floors ?? '',
    actual_unit_count: survey.actual_unit_count ?? tower?.total_units ?? '',
    has_roof_access: survey.has_roof_access ?? null,
    equipment_notes: survey.equipment_notes ?? '',
    remarks: '',
    building_network_system: survey.building_network_system ?? '',
    indoor_outlet_capacity: survey.indoor_outlet_capacity ?? '',
    isp_count_per_unit: survey.isp_count_per_unit ?? '',
    telco_room_location: survey.telco_room_location ?? '',
    electrical_room_location: survey.electrical_room_location ?? '',
    providers: survey.providers?.map(p => ({ provider_name: p.provider_name })) ?? [],
    document_category: '',
    files: [],
})

const fileInput = ref(null)
const refreshOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['assignment', 'can', 'flash', 'activityLogs', 'documentTypes', 'missingDocumentRequirements'],
}

const uploadedDocumentCategories = computed(() => new Set(
    (props.assignment.documents ?? []).map(doc => doc.category).filter(Boolean)
))

function resetFiles() {
    form.files = []
    form.document_category = ''
    if (fileInput.value) fileInput.value.value = ''
}

function saveFindings() {
    form.post(`/site-planning/${props.assignment.id}/inspection`, {
        ...refreshOptions,
        forceFormData: true,
        onSuccess: resetFiles,
    })
}

function submitInspection() {
    form.post(`/site-planning/${props.assignment.id}/inspection/submit`, {
        ...refreshOptions,
        forceFormData: true,
        onSuccess: resetFiles,
    })
}

function documentUrl(document) {
    return `/site-planning/${props.assignment.id}/documents/${document.id}`
}
</script>

<template>
    <div class="bg-surface-bright rounded-2xl border border-outline/10 p-7">
        <div class="flex items-center gap-2.5 mb-6">
            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">search_check</span>
            <div>
                <p class="text-sm font-bold text-on-surface">Site Survey Form</p>
                <p class="text-xs text-on-surface-variant mt-0.5">Fill in survey findings and submit.</p>
            </div>
        </div>

        <form @submit.prevent="saveFindings" class="flex flex-col gap-4">
            <fieldset :disabled="!canFillSurvey" class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Floors Surveyed</label>
                        <input v-model="form.floors_surveyed" type="number" min="1"
                            class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                        <p v-if="tower?.total_floors != null" class="text-[11px] text-on-surface-variant">Declared for {{ tower.name }}: {{ tower.total_floors }} floors</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Actual Units</label>
                        <input v-model="form.actual_unit_count" type="number" min="0"
                            class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                        <p v-if="tower?.total_units != null" class="text-[11px] text-on-surface-variant">Declared for {{ tower.name }}: {{ tower.total_units }} units</p>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Roof Access</label>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.has_roof_access" :value="true" class="accent-primary" />
                            <span class="text-sm text-on-surface">Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.has_roof_access" :value="false" class="accent-primary" />
                            <span class="text-sm text-on-surface">No</span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Existing ISP Providers</label>
                        <button type="button" @click="form.providers.push({ provider_name: '' })" class="flex items-center gap-1 text-xs font-bold text-primary hover:underline transition-colors">
                            <span class="material-symbols-outlined text-[14px]">add_circle</span>
                            Add
                        </button>
                    </div>
                    <div v-if="form.providers.length === 0" class="text-xs text-on-surface-variant/60 bg-surface-container-low rounded-lg px-3 py-2.5 border border-outline/20">
                        No providers added yet.
                    </div>
                    <div v-for="(provider, index) in form.providers" :key="index" class="bg-surface-container-low rounded-lg p-3 border border-outline/20 flex items-center gap-2">
                        <input v-model="provider.provider_name" type="text" placeholder="Provider name"
                            class="flex-1 bg-surface-bright border border-outline/40 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                        <button type="button" @click="form.providers.splice(index, 1)" class="p-1.5 text-on-surface-variant hover:text-error transition-colors rounded">
                            <span class="material-symbols-outlined text-[16px]">delete</span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Equipment Placement Notes</label>
                    <textarea v-model="form.equipment_notes" rows="3"
                        class="w-full bg-surface-container-low border rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none"
                        :class="form.errors.equipment_notes ? 'border-error' : 'border-outline/40'" />
                    <p v-if="form.errors.equipment_notes" class="text-xs text-error">{{ form.errors.equipment_notes }}</p>
                </div>

                <div class="border-t border-outline/10 pt-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Network Details</p>
                    <div class="flex flex-col gap-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Building Network System</label>
                                <input v-model="form.building_network_system" type="text"
                                    class="w-full bg-surface-container-low border rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="form.errors.building_network_system ? 'border-error' : 'border-outline/40'" />
                                <p v-if="form.errors.building_network_system" class="text-xs text-error">{{ form.errors.building_network_system }}</p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">No. of Distribution Box Needed</label>
                                <input v-model="form.indoor_outlet_capacity" type="text"
                                    class="w-full bg-surface-container-low border rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="form.errors.indoor_outlet_capacity ? 'border-error' : 'border-outline/40'" />
                                <p v-if="form.errors.indoor_outlet_capacity" class="text-xs text-error">{{ form.errors.indoor_outlet_capacity }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">No. of ISP Allowed per Unit</label>
                            <input v-model="form.isp_count_per_unit" type="number" min="0"
                                class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Telco Room Location</label>
                                <input v-model="form.telco_room_location" type="text"
                                    class="w-full bg-surface-container-low border rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="form.errors.telco_room_location ? 'border-error' : 'border-outline/40'" />
                                <p v-if="form.errors.telco_room_location" class="text-xs text-error">{{ form.errors.telco_room_location }}</p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Electrical Room Location</label>
                                <input v-model="form.electrical_room_location" type="text"
                                    class="w-full bg-surface-container-low border rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="form.errors.electrical_room_location ? 'border-error' : 'border-outline/40'" />
                                <p v-if="form.errors.electrical_room_location" class="text-xs text-error">{{ form.errors.electrical_room_location }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="assignment.site_survey?.site_survey_tasks?.length" class="border-t border-outline/10 pt-3 flex flex-col gap-2">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Additional Task Fields</label>
                    <SiteSurveyTasksPanel
                        :tasks="assignment.site_survey.site_survey_tasks"
                        :assignment-id="assignment.id"
                        :disabled="!canFillSurvey"
                    />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Remarks</label>
                    <textarea v-model="form.remarks" rows="2"
                        class="w-full bg-surface-container-low border rounded-lg px-3 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none"
                        :class="form.errors.remarks ? 'border-error' : 'border-outline/40'" />
                    <p v-if="form.errors.remarks" class="text-xs text-error">{{ form.errors.remarks }}</p>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Photos & Documents</label>
                    <div v-if="documentTypes.length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="type in documentTypes"
                            :key="type.id"
                            class="text-[10px] font-bold px-2 py-0.5 rounded-full border"
                            :class="uploadedDocumentCategories.has(type.name)
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                : missingDocumentRequirements.includes(type.name)
                                    ? 'bg-amber-50 text-amber-700 border-amber-200'
                                    : 'bg-surface-container text-on-surface-variant border-outline/20'"
                        >
                            {{ type.name }} {{ uploadedDocumentCategories.has(type.name) ? 'Uploaded' : (missingDocumentRequirements.includes(type.name) ? 'Required' : 'Optional') }}
                        </span>
                    </div>
                    <div v-if="assignment.documents?.filter(d => d.category !== 'Design Plan').length" class="flex flex-col gap-1.5">
                        <div v-for="document in assignment.documents.filter(d => d.category !== 'Design Plan')" :key="document.id" class="bg-surface-container-low rounded-lg px-3 py-2 border border-outline/20">
                            <DocumentRow
                                :document="document"
                                :replace-url="`${documentUrl(document)}/replace`"
                                :delete-url="documentUrl(document)"
                                :can-manage="true"
                                :refresh-options="refreshOptions"
                            />
                        </div>
                    </div>
                    <div v-if="documentTypes.length" class="relative">
                        <select
                            v-model="form.document_category"
                            class="w-full appearance-none bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-xs text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-9"
                        >
                            <option value="">No category</option>
                            <option v-for="type in documentTypes" :key="type.id" :value="type.name">{{ type.name }}</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[16px]">expand_more</span>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer border border-dashed border-outline/40 rounded-lg px-3 py-3 hover:bg-surface-container-low transition-colors">
                        <span class="material-symbols-outlined text-[18px] text-on-surface-variant">upload_file</span>
                        <span class="text-xs text-on-surface-variant">
                            {{ form.files.length ? `${form.files.length} file(s) selected` : 'Upload photos or documents' }}
                        </span>
                        <input ref="fileInput" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" class="sr-only" @change="form.files = Array.from($event.target.files)" />
                    </label>
                    <p v-if="form.errors['files.0']" class="text-xs text-error">{{ form.errors['files.0'] }}</p>
                </div>
            </fieldset>

            <div v-if="canFillSurvey" class="flex flex-col gap-2 mt-1">
                <p v-if="form.errors.required_documents" class="flex items-center gap-1.5 text-xs font-medium text-error bg-error/5 border border-error/20 rounded-lg px-3 py-2">
                    <span class="material-symbols-outlined text-[14px] shrink-0">error</span>
                    {{ form.errors.required_documents }}
                </p>
                <button type="submit" :disabled="form.processing"
                    class="w-full flex items-center justify-center gap-2 border border-outline/40 text-on-surface-variant font-semibold text-sm px-5 py-2.5 rounded-lg hover:bg-surface-container active:scale-[0.99] transition-all disabled:opacity-60">
                    <span class="material-symbols-outlined text-[16px]">save</span>
                    Save Progress
                </button>
                <button v-if="canSubmitInspection" type="button" :disabled="form.processing" @click="submitInspection"
                    class="w-full flex items-center justify-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-sm">
                    <span v-if="form.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    Submit & Continue to Design Plan
                </button>
            </div>
        </form>
    </div>
</template>
