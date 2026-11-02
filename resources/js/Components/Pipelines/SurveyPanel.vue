<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

const props = defineProps({
    pipeline: Object,
    documentTypes: {
        type: Array,
        default: () => [],
    },
    missingDocumentRequirements: {
        type: Array,
        default: () => [],
    },
})

const surveyForm = useForm({
    floors_surveyed: props.pipeline.site_survey?.floors_surveyed ?? '',
    actual_unit_count: props.pipeline.site_survey?.actual_unit_count ?? '',
    has_roof_access: props.pipeline.site_survey?.has_roof_access ?? null,
    equipment_notes: props.pipeline.site_survey?.equipment_notes ?? '',
    remarks: '',
    building_network_system: props.pipeline.site_survey?.building_network_system ?? '',
    indoor_outlet_capacity: props.pipeline.site_survey?.indoor_outlet_capacity ?? '',
    isp_count_per_unit: props.pipeline.site_survey?.isp_count_per_unit ?? '',
    telco_room_location: props.pipeline.site_survey?.telco_room_location ?? '',
    electrical_room_location: props.pipeline.site_survey?.electrical_room_location ?? '',
    lobby_booth_allowed: props.pipeline.site_survey?.lobby_booth_allowed ?? null,
    lobby_standee_allowed: props.pipeline.site_survey?.lobby_standee_allowed ?? null,
    lobby_flyers_stand_allowed: props.pipeline.site_survey?.lobby_flyers_stand_allowed ?? null,
    roaming_sales_rep_allowed: props.pipeline.site_survey?.roaming_sales_rep_allowed ?? null,
    telco_room_fee: props.pipeline.site_survey?.telco_room_fee ?? '',
    marketing_paraphernalia: props.pipeline.site_survey?.marketing_paraphernalia ?? '',
    brochure_posting_location: props.pipeline.site_survey?.brochure_posting_location ?? '',
    providers: props.pipeline.site_survey?.providers?.map(p => ({
        provider_name: p.provider_name,
    })) ?? [],
    document_category: '',
    files: [],
})

const fileInput = ref(null)

// Client-side pre-check: reject oversized files immediately instead of letting the
// request bounce off the server/web-server limits with an error page. The server's
// MAX_UPLOAD_KB rule stays the backstop.
const MAX_FILE_MB = 100
const fileSizeError = ref('')

function selectFiles(event) {
    const files = Array.from(event.target.files)
    const oversized = files.filter(file => file.size > MAX_FILE_MB * 1024 * 1024)

    if (oversized.length) {
        fileSizeError.value = `${oversized.map(file => `"${file.name}"`).join(', ')} exceeds the ${MAX_FILE_MB}MB limit — each file must be ${MAX_FILE_MB}MB or smaller.`
        surveyForm.files = []
        event.target.value = ''
        return
    }

    fileSizeError.value = ''
    surveyForm.files = files
}
const pipelineRefreshOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['pipeline', 'can', 'flash', 'documentTypes', 'missingDocumentRequirements'],
}

function submitSaveSurvey() {
    surveyForm.post(`/pipelines/${props.pipeline.id}/survey-findings`, {
        ...pipelineRefreshOptions,
        forceFormData: true,
        onSuccess: () => {
            surveyForm.files = []
            if (fileInput.value) fileInput.value.value = ''
        },
    })
}

function surveyDocumentUrl(document) {
    return `/pipelines/${props.pipeline.id}/survey-documents/${document.id}`
}
</script>

<template>
    <div class="bg-surface-bright rounded-xl border border-outline/10 p-6">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-lg bg-orange-50 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-orange-600 text-[20px]">search_check</span>
            </div>
            <div>
                <p class="text-sm font-bold text-on-surface">Prospecting Details</p>
                <p class="text-xs text-on-surface-variant">Capture early site, network, marketing, and document findings.</p>
            </div>
        </div>

        <form @submit.prevent="submitSaveSurvey" class="flex flex-col gap-4">

            <!-- Floors + Units -->
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Floors Surveyed</label>
                    <input v-model="surveyForm.floors_surveyed" type="number" min="1" placeholder="e.g. 12"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                    <p v-if="surveyForm.errors.floors_surveyed" class="text-xs text-error flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.floors_surveyed }}
                    </p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Actual Units</label>
                    <input v-model="surveyForm.actual_unit_count" type="number" min="0" placeholder="e.g. 480"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                    <p v-if="surveyForm.errors.actual_unit_count" class="text-xs text-error flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.actual_unit_count }}
                    </p>
                </div>
            </div>

            <!-- Roof Access -->
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Roof Access</label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" v-model="surveyForm.has_roof_access" :value="true" class="accent-primary" />
                        <span class="text-sm text-on-surface">Yes</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" v-model="surveyForm.has_roof_access" :value="false" class="accent-primary" />
                        <span class="text-sm text-on-surface">No</span>
                    </label>
                </div>
            </div>

            <!-- ISP Providers -->
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Existing ISP Providers</label>
                    <button type="button" @click="surveyForm.providers.push({ provider_name: '' })" class="flex items-center gap-1 text-xs font-bold text-primary hover:underline transition-colors">
                        <span class="material-symbols-outlined text-[14px]">add_circle</span>
                        Add
                    </button>
                </div>
                <div v-if="surveyForm.providers.length === 0" class="text-xs text-on-surface-variant/60 bg-surface-container-low rounded-lg px-3 py-2.5 border border-outline/20">
                    No providers added yet.
                </div>
                <div v-for="(provider, index) in surveyForm.providers" :key="index" class="bg-surface-container-low rounded-lg p-3 border border-outline/20 flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <input v-model="provider.provider_name" type="text" placeholder="Provider name"
                            class="flex-1 bg-surface-bright border rounded-lg px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                            :class="surveyForm.errors[`providers.${index}.provider_name`] ? 'border-error' : 'border-outline/40'" />
                        <button type="button" @click="surveyForm.providers.splice(index, 1)" class="p-1.5 text-on-surface-variant hover:text-error transition-colors rounded">
                            <span class="material-symbols-outlined text-[16px]">delete</span>
                        </button>
                    </div>
                    <p v-if="surveyForm.errors[`providers.${index}.provider_name`]" class="text-xs text-error flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors[`providers.${index}.provider_name`] }}
                    </p>
                </div>
            </div>

            <!-- Documents -->
            <div class="flex flex-col gap-2">
                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Photos & Documents</label>

                <div v-if="documentTypes.length" class="flex flex-wrap gap-1.5">
                    <span
                        v-for="type in documentTypes"
                        :key="type.id"
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full border"
                        :class="missingDocumentRequirements.includes(type.name) ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                    >
                        {{ type.name }} {{ missingDocumentRequirements.includes(type.name) ? 'Required' : 'Uploaded' }}
                    </span>
                </div>

                <div v-if="pipeline.site_survey?.documents?.length" class="flex flex-col gap-1.5">
                    <div v-for="doc in pipeline.site_survey.documents" :key="doc.id" class="bg-surface-container-low rounded-lg px-3 py-2 border border-outline/20">
                        <DocumentRow
                            :document="doc"
                            :replace-url="`${surveyDocumentUrl(doc)}/replace`"
                            :delete-url="surveyDocumentUrl(doc)"
                            :can-manage="true"
                            :refresh-options="pipelineRefreshOptions"
                        />
                    </div>
                </div>

                <div v-if="documentTypes.length" class="relative">
                    <select
                        v-model="surveyForm.document_category"
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
                        {{ surveyForm.files.length ? `${surveyForm.files.length} file(s) selected` : 'Upload photos or documents' }}
                    </span>
                    <input ref="fileInput" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" class="sr-only" @change="selectFiles" />
                </label>
                <p v-if="fileSizeError" class="text-xs text-error flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">error</span>{{ fileSizeError }}
                </p>
                <p v-if="surveyForm.errors['files.0']" class="text-xs text-error flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors['files.0'] }}
                </p>
            </div>

            <!-- Equipment Notes -->
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Equipment Placement Notes</label>
                <textarea v-model="surveyForm.equipment_notes" rows="3" placeholder="Describe recommended equipment placement, infrastructure needs…"
                    class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none" />
                <p v-if="surveyForm.errors.equipment_notes" class="text-xs text-error flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.equipment_notes }}
                </p>
            </div>

            <!-- Network Details -->
            <div class="border-t border-outline/10 pt-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Network Details</p>
                <div class="flex flex-col gap-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Building Network System</label>
                            <input v-model="surveyForm.building_network_system" type="text" placeholder="e.g. FTTH, CATV"
                                class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                            <p v-if="surveyForm.errors.building_network_system" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.building_network_system }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">No. of Distribution Box Needed</label>
                            <input v-model="surveyForm.indoor_outlet_capacity" type="text" placeholder="e.g. 2 per unit"
                                class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                            <p v-if="surveyForm.errors.indoor_outlet_capacity" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.indoor_outlet_capacity }}
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">No. of ISP Allowed per Unit</label>
                            <input v-model="surveyForm.isp_count_per_unit" type="number" min="0" placeholder="e.g. 2"
                                class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                            <p v-if="surveyForm.errors.isp_count_per_unit" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.isp_count_per_unit }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Telco Room Fee</label>
                            <input v-model="surveyForm.telco_room_fee" type="text" placeholder="e.g. PHP 5,000/mo"
                                class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                            <p v-if="surveyForm.errors.telco_room_fee" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.telco_room_fee }}
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Telco Room Location</label>
                            <input v-model="surveyForm.telco_room_location" type="text" placeholder="e.g. Basement Level 1"
                                class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                            <p v-if="surveyForm.errors.telco_room_location" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.telco_room_location }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Electrical Room Location</label>
                            <input v-model="surveyForm.electrical_room_location" type="text" placeholder="e.g. Ground Floor"
                                class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all" />
                            <p v-if="surveyForm.errors.electrical_room_location" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.electrical_room_location }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Marketing Related -->
            <div class="border-t border-outline/10 pt-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Marketing Related</p>
                <div class="flex flex-col gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60 mb-2">Lobby Marketing Options</p>
                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="surveyForm.lobby_booth_allowed" class="accent-primary w-4 h-4" />
                                <span class="text-sm text-on-surface">Booth</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="surveyForm.lobby_standee_allowed" class="accent-primary w-4 h-4" />
                                <span class="text-sm text-on-surface">Standee</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="surveyForm.lobby_flyers_stand_allowed" class="accent-primary w-4 h-4" />
                                <span class="text-sm text-on-surface">Flyers Stand</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="surveyForm.roaming_sales_rep_allowed" class="accent-primary w-4 h-4" />
                                <span class="text-sm text-on-surface">Roaming Sales Rep</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Marketing Paraphernalia</label>
                        <textarea v-model="surveyForm.marketing_paraphernalia" rows="2" placeholder="List allowed marketing materials…"
                            class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none" />
                        <p v-if="surveyForm.errors.marketing_paraphernalia" class="text-xs text-error flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.marketing_paraphernalia }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Designated Brochure Posting Location</label>
                        <textarea v-model="surveyForm.brochure_posting_location" rows="2" placeholder="Describe allowed posting areas…"
                            class="w-full bg-surface-container-low border border-outline/40 rounded-lg px-3 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none" />
                        <p v-if="surveyForm.errors.brochure_posting_location" class="text-xs text-error flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">error</span>{{ surveyForm.errors.brochure_posting_location }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-col gap-2 mt-1">
                <button type="submit" :disabled="surveyForm.processing"
                    class="w-full flex items-center justify-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-sm">
                    <span v-if="surveyForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-[16px]">save</span>
                    Save Progress
                </button>
            </div>
        </form>
    </div>
</template>
