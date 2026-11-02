<script setup>
import { ref } from 'vue'
import { useForm, useHttp } from '@inertiajs/vue3'
import { formatDateTime } from '@/utils/pipeline.js'

const props = defineProps({
    document: { type: Object, required: true },
    replaceUrl: { type: String, default: null },
    deleteUrl: { type: String, default: null },
    canManage: { type: Boolean, default: false },
    refreshOptions: { type: Object, default: null },
})

const viewingPhoto = ref(false)
const showHistory = ref(false)
const fileInput = ref(null)

const replaceForm = useForm({ file: null })
const deleteForm = useForm({})
const historyHttp = useHttp({})
const versions = ref(null)

function requestOptions(extra = {}) {
    return { preserveScroll: true, preserveState: true, ...(props.refreshOptions ?? {}), ...extra }
}

function documentIcon(doc) {
    return doc.type === 'photo' ? 'image' : 'description'
}

function openDocument() {
    if (props.document.type === 'photo') {
        viewingPhoto.value = true
        return
    }

    if (props.document.url) {
        window.open(props.document.url, '_blank')
    }
}

function triggerReplace() {
    fileInput.value?.click()
}

function submitReplace(event) {
    const file = event.target.files[0]
    if (!file) return

    replaceForm.file = file
    replaceForm.post(props.replaceUrl, requestOptions({
        forceFormData: true,
        onFinish: () => {
            replaceForm.reset()
            if (fileInput.value) fileInput.value.value = ''
        },
    }))
}

function removeDocument() {
    if (!confirm(`Remove "${props.document.name}"?`)) return
    deleteForm.delete(props.deleteUrl, requestOptions())
}

function toggleHistory() {
    showHistory.value = !showHistory.value

    if (showHistory.value && versions.value === null) {
        historyHttp.get(`/documents/${props.document.id}/versions`, {
            onSuccess: (data) => { versions.value = data.versions },
        })
    }
}
</script>

<template>
    <div class="flex flex-col">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[15px] text-on-surface-variant shrink-0">{{ documentIcon(document) }}</span>
            <button type="button" @click="openDocument" class="text-sm text-on-surface hover:text-primary truncate flex-1 min-w-0 text-left">
                {{ document.name }}
            </button>
            <span v-if="document.version > 1" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary shrink-0">v{{ document.version }}</span>
            <slot name="trailing" />
            <button type="button" @click="toggleHistory" class="text-[10px] font-bold text-on-surface-variant hover:text-primary px-1.5 py-1 rounded-lg hover:bg-primary/5 shrink-0">
                History
            </button>
            <template v-if="canManage">
                <button v-if="replaceUrl" type="button" @click="triggerReplace" :disabled="replaceForm.processing" class="text-xs font-bold text-primary px-2 py-1 rounded-lg hover:bg-primary/5 shrink-0 disabled:opacity-50">
                    Replace
                </button>
                <input ref="fileInput" type="file" class="sr-only" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" @change="submitReplace" />
                <button v-if="deleteUrl" type="button" @click="removeDocument" :disabled="deleteForm.processing" class="text-xs font-bold text-error px-2 py-1 rounded-lg hover:bg-error/5 shrink-0 disabled:opacity-50">
                    Remove
                </button>
            </template>
        </div>

        <p v-if="replaceForm.errors.file" class="text-xs text-error mt-1">{{ replaceForm.errors.file }}</p>
        <p v-if="deleteForm.errors.document" class="text-xs text-error mt-1">{{ deleteForm.errors.document }}</p>

        <div v-if="showHistory" class="mt-1.5 ml-5 flex flex-col gap-1 border-l-2 border-outline/10 pl-3">
            <p v-if="historyHttp.processing" class="text-[10px] text-on-surface-variant italic">Loading history…</p>
            <template v-else-if="versions?.length">
                <div v-for="version in versions" :key="version.id" class="flex items-center gap-2">
                    <a :href="version.url" target="_blank" class="text-[11px] text-on-surface hover:text-primary hover:underline truncate flex-1 min-w-0">
                        v{{ version.version }} — {{ version.name }}
                    </a>
                    <span v-if="version.is_current" class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 shrink-0">Current</span>
                    <span class="text-[10px] text-on-surface-variant shrink-0">{{ version.uploaded_by ?? '—' }} · {{ formatDateTime(version.created_at) }}</span>
                </div>
            </template>
            <p v-else class="text-[10px] text-on-surface-variant italic">No earlier versions.</p>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="viewingPhoto" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click.self="viewingPhoto = false">
            <div class="relative flex flex-col bg-surface-bright rounded-xl overflow-hidden shadow-2xl w-full max-w-4xl max-h-[90vh]">
                <div class="flex items-center justify-between px-4 py-3 border-b border-outline/10 shrink-0">
                    <span class="text-sm font-semibold text-on-surface truncate">{{ document.name }}</span>
                    <button @click="viewingPhoto = false" class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors ml-3">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
                <div class="overflow-auto flex-1 flex items-center justify-center p-4 bg-black/30 min-h-0">
                    <img :src="document.url" :alt="document.name" class="max-w-full max-h-full object-contain rounded" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
