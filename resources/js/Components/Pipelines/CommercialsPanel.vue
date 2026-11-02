<script setup>
import { computed } from 'vue'
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

const props = defineProps({
    pipeline: Object,
    hasApprovalConfig: Boolean,
    documentTypes: Array,
    missingDocumentRequirements: {
        type: Array,
        default: () => [],
    },
})

const commercialsForm = useForm({ remarks: '', document_category: '', files: [] })
const reopenSurveyForm = useForm({})
const fileInput = ref(null)
const pipelineRefreshOptions = {
    preserveScroll: true,
    preserveState: true,
    only: ['pipeline', 'can', 'flash', 'hasApprovalConfig', 'documentTypes', 'missingDocumentRequirements'],
}

const requiredDocumentTypes = computed(() => props.documentTypes.filter(type => type.is_required))
const uploadedCategories = computed(() => new Set((props.pipeline.documents ?? []).map(doc => doc.category).filter(Boolean)))
const missingRequiredTypes = computed(() => requiredDocumentTypes.value.filter(type => props.missingDocumentRequirements.includes(type.name) || !uploadedCategories.value.has(type.name)))

function submitSaveProgress() {
    commercialsForm.post(`/pipelines/${props.pipeline.id}/save-commercials-progress`, {
        ...pipelineRefreshOptions,
        forceFormData: true,
        onSuccess: () => {
            commercialsForm.files = []
            if (fileInput.value) fileInput.value.value = ''
        },
    })
}

function submitCommercials() {
    commercialsForm.post(`/pipelines/${props.pipeline.id}/set-commercials`, {
        ...pipelineRefreshOptions,
        forceFormData: true,
        onSuccess: () => {
            commercialsForm.files = []
            if (fileInput.value) fileInput.value.value = ''
        },
    })
}

function submitReopenSurvey() {
    reopenSurveyForm.post(`/pipelines/${props.pipeline.id}/reopen-survey`, pipelineRefreshOptions)
}

function commercialDocumentUrl(document) {
    return `/pipelines/${props.pipeline.id}/commercial-documents/${document.id}`
}
</script>

<template>
    <div class="bg-surface-bright rounded-xl border border-outline/10 p-6 flex flex-col gap-5">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-yellow-50 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-yellow-600 text-[20px]">receipt_long</span>
            </div>
            <div>
                <p class="text-sm font-bold text-on-surface">Commercials</p>
                <p class="text-xs text-on-surface-variant">Add notes and supporting documents.</p>
            </div>
        </div>

        <form @submit.prevent="submitReopenSurvey" class="flex justify-end">
            <button type="submit" :disabled="reopenSurveyForm.processing" class="flex items-center gap-1 text-xs font-bold text-on-surface-variant hover:text-primary transition-colors">
                <span class="material-symbols-outlined text-[14px]">undo</span>
                Re-open Survey
            </button>
        </form>

        <form @submit.prevent="submitCommercials" class="flex flex-col gap-4">

            <div class="flex flex-col gap-2">
                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Commercial Documents</label>

                <div v-if="documentTypes.length" class="flex flex-wrap gap-1.5">
                    <span
                        v-for="type in documentTypes"
                        :key="type.id"
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full border"
                        :class="uploadedCategories.has(type.name)
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                            : type.is_required
                                ? 'bg-amber-50 text-amber-700 border-amber-200'
                                : 'bg-surface-container text-on-surface-variant border-outline/20'"
                    >
                        {{ type.name }} {{ uploadedCategories.has(type.name) ? 'Uploaded' : (type.is_required ? 'Required' : 'Optional') }}
                    </span>
                </div>

                <div v-if="pipeline.documents?.length" class="flex flex-col gap-1.5">
                    <div v-for="doc in pipeline.documents" :key="doc.id" class="bg-surface-container-low rounded-lg px-3 py-2 border border-outline/20">
                        <DocumentRow
                            :document="doc"
                            :replace-url="`${commercialDocumentUrl(doc)}/replace`"
                            :delete-url="commercialDocumentUrl(doc)"
                            :can-manage="true"
                            :refresh-options="pipelineRefreshOptions"
                        >
                            <template v-if="doc.category" #trailing>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary shrink-0">{{ doc.category }}</span>
                            </template>
                        </DocumentRow>
                    </div>
                </div>

                <!-- Document Type -->
                <div class="relative">
                    <select
                        v-model="commercialsForm.document_category"
                        class="w-full appearance-none bg-surface-container-low border border-outline/40 rounded-lg px-4 py-2.5 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-10"
                    >
                        <option value="">No category</option>
                        <option v-for="type in documentTypes" :key="type.id" :value="type.name">{{ type.name }}</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
                </div>

                <label class="flex items-center gap-2 cursor-pointer border border-dashed border-outline/40 rounded-lg px-3 py-3 hover:bg-surface-container-low transition-colors">
                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant">upload_file</span>
                    <span class="text-xs text-on-surface-variant">
                        {{ commercialsForm.files.length ? `${commercialsForm.files.length} file(s) selected` : 'Upload photos or documents' }}
                    </span>
                    <input ref="fileInput" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" class="sr-only" @change="commercialsForm.files = Array.from($event.target.files)" />
                </label>
                <p v-if="commercialsForm.errors['files.0']" class="text-xs text-error flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">error</span>{{ commercialsForm.errors['files.0'] }}
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <button type="button" @click="submitSaveProgress" :disabled="commercialsForm.processing"
                    class="w-full flex items-center justify-center gap-2 border border-primary text-primary font-semibold text-sm px-5 py-2.5 rounded-lg hover:bg-primary/5 active:scale-[0.99] transition-all disabled:opacity-60 disabled:cursor-not-allowed">
                    <span v-if="commercialsForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-[16px]">save</span>
                    Save Progress
                </button>

                <div v-if="!hasApprovalConfig" class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5 text-amber-700 text-xs">
                    <span class="material-symbols-outlined text-[16px] shrink-0 mt-0.5">warning</span>
                    <span>No approval workflow has been configured. <a href="/settings/approvals" class="font-bold underline hover:text-amber-900">Set up approval steps</a> before submitting.</span>
                </div>

                <div v-else-if="missingRequiredTypes.length" class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5 text-amber-700 text-xs">
                    <span class="material-symbols-outlined text-[16px] shrink-0 mt-0.5">warning</span>
                    <span>Upload required documents before submitting: {{ missingRequiredTypes.map(type => type.name).join(', ') }}.</span>
                </div>

                <button v-else type="submit" :disabled="commercialsForm.processing || missingRequiredTypes.length"
                    class="w-full flex items-center justify-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-sm">
                    <span v-if="commercialsForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-[16px]">send</span>
                    Submit & Trigger Approval
                </button>
            </div>
        </form>
    </div>
</template>
