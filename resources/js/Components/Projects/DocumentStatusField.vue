<script setup>
import { useForm } from '@inertiajs/vue3'
import { reactive } from 'vue'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

const props = defineProps({
    field: { type: Object, required: true },
    documents: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    projectId: { type: [String, Number], required: true },
    stageId: { type: [String, Number], required: true },
})

const uploadForm = useForm({ document_category: props.field.document_category, files: [] })

function submitUpload() {
    uploadForm.post(`/projects/${props.projectId}/stages/${props.stageId}/documents`, {
        preserveScroll: true,
        onSuccess: () => uploadForm.reset('files'),
    })
}

const statusForms = reactive({})

function statusFormFor(document) {
    if (!statusForms[document.id]) {
        statusForms[document.id] = useForm({ status: document.pivot?.status ?? '' })
    }

    return statusForms[document.id]
}

function saveStatus(document) {
    statusFormFor(document).patch(`/projects/${props.projectId}/stages/${props.stageId}/documents/${document.id}`, {
        preserveScroll: true,
    })
}

function documentUrl(document) {
    return `/projects/${props.projectId}/stages/${props.stageId}/documents/${document.id}`
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <DocumentRow
            v-for="document in documents"
            :key="document.id"
            :document="document"
            :replace-url="!disabled ? `${documentUrl(document)}/replace` : null"
            :delete-url="!disabled ? documentUrl(document) : null"
            :can-manage="!disabled"
        >
            <template #trailing>
                <select
                    v-model="statusFormFor(document).status"
                    :disabled="disabled"
                    @change="saveStatus(document)"
                    class="text-sm bg-surface-container-lowest border border-outline/40 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-primary/50 disabled:opacity-60"
                >
                    <option value="">— Status —</option>
                    <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
                </select>

                <span v-if="statusFormFor(document).processing" class="text-[10px] font-semibold text-on-surface-variant shrink-0">Saving…</span>
                <span v-else-if="statusFormFor(document).recentlySuccessful" class="text-[10px] font-semibold text-emerald-700 shrink-0">Saved</span>
            </template>
        </DocumentRow>

        <form v-if="!disabled" @submit.prevent="submitUpload" class="flex items-center gap-2">
            <input
                type="file"
                @change="uploadForm.files = Array.from($event.target.files)"
                class="text-sm flex-1 min-w-0"
            />
            <button
                type="submit"
                :disabled="uploadForm.processing"
                class="bg-primary text-white text-xs font-semibold px-3 py-1.5 rounded-lg disabled:opacity-60 shrink-0"
            >Upload</button>
            <span v-if="uploadForm.processing" class="text-[10px] font-semibold text-on-surface-variant shrink-0">Uploading…</span>
            <span v-else-if="uploadForm.recentlySuccessful" class="text-[10px] font-semibold text-emerald-700 shrink-0">Uploaded</span>
        </form>
        <p v-if="uploadForm.errors.files" class="text-xs text-error">{{ uploadForm.errors.files }}</p>
        <p v-if="uploadForm.errors['files.0']" class="text-xs text-error">{{ uploadForm.errors['files.0'] }}</p>
    </div>
</template>
