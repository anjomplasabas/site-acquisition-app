<script setup>
import { useForm } from '@inertiajs/vue3'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

const props = defineProps({
    documentCategory: { type: String, required: true },
    documents: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    projectId: { type: [String, Number], required: true },
    stageId: { type: [String, Number], required: true },
})

const uploadForm = useForm({ document_category: props.documentCategory, files: [] })

function submitUpload() {
    uploadForm.post(`/projects/${props.projectId}/stages/${props.stageId}/documents`, {
        preserveScroll: true,
        onSuccess: () => uploadForm.reset('files'),
    })
}

function documentUrl(document) {
    return `/projects/${props.projectId}/stages/${props.stageId}/documents/${document.id}`
}
</script>

<template>
    <div class="flex flex-col gap-2 mt-2">
        <DocumentRow
            v-for="document in documents"
            :key="document.id"
            :document="document"
            :replace-url="!disabled ? `${documentUrl(document)}/replace` : null"
            :delete-url="!disabled ? documentUrl(document) : null"
            :can-manage="!disabled"
        />
        <p v-if="documents.length === 0" class="text-xs text-on-surface-variant/50">No files attached.</p>

        <form v-if="!disabled" @submit.prevent="submitUpload" class="flex items-center gap-2">
            <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary border border-primary/30 bg-primary/5 hover:bg-primary/10 rounded-lg px-3 py-1.5 cursor-pointer transition-colors shrink-0">
                <span class="material-symbols-outlined text-[15px]">attach_file</span>
                Choose files
                <input
                    type="file"
                    multiple
                    class="hidden"
                    @change="uploadForm.files = Array.from($event.target.files)"
                />
            </label>
            <span class="text-sm text-on-surface-variant truncate flex-1 min-w-0">
                {{ uploadForm.files.length ? uploadForm.files.map((file) => file.name).join(', ') : 'No file chosen' }}
            </span>
            <button
                type="submit"
                :disabled="!uploadForm.files.length || uploadForm.processing"
                class="bg-primary text-white text-xs font-semibold px-3 py-1.5 rounded-lg disabled:opacity-40 shrink-0"
            >{{ uploadForm.processing ? 'Uploading…' : 'Upload' }}</button>
            <span v-if="uploadForm.recentlySuccessful" class="text-[10px] font-semibold text-emerald-700 shrink-0">Uploaded</span>
        </form>
        <p v-if="uploadForm.errors.files" class="text-xs text-error">{{ uploadForm.errors.files }}</p>
        <p v-if="uploadForm.errors['files.0']" class="text-xs text-error">{{ uploadForm.errors['files.0'] }}</p>
    </div>
</template>
