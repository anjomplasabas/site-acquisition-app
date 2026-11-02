<script setup>
import { useForm } from '@inertiajs/vue3'
import { reactive } from 'vue'
import DocumentRow from '@/Components/Documents/DocumentRow.vue'

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    assignmentId: { type: [String, Number], required: true },
})

function baseUrl(task) {
    return `/site-planning/${props.assignmentId}/survey-tasks/${task.id}`
}

const notesForms = reactive({})

function notesFormFor(task) {
    if (!notesForms[task.id]) {
        notesForms[task.id] = useForm({ notes: task.notes ?? '' })
    }

    return notesForms[task.id]
}

function saveTaskNotes(task) {
    notesFormFor(task).patch(baseUrl(task), { preserveScroll: true })
}

const uploadForms = reactive({})

function uploadFormFor(task) {
    if (!uploadForms[task.id]) {
        uploadForms[task.id] = useForm({ files: [] })
    }

    return uploadForms[task.id]
}

function submitTaskUpload(task, event) {
    const files = Array.from(event.target.files ?? [])
    if (!files.length) { return }

    uploadFormFor(task).transform(() => ({ files })).post(`${baseUrl(task)}/documents`, {
        preserveScroll: true,
        onFinish: () => { event.target.value = '' },
    })
}

function taskDocumentUrl(task, document, action) {
    return `${baseUrl(task)}/documents/${document.id}${action ? `/${action}` : ''}`
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div v-for="task in tasks" :key="task.id" class="rounded-lg border border-outline/15 bg-surface-bright p-3 flex flex-col gap-2">
            <p class="text-sm font-semibold text-on-surface">{{ task.name }}</p>

            <div class="flex flex-col gap-1.5">
                <textarea
                    v-model="notesFormFor(task).notes"
                    :disabled="disabled"
                    rows="2"
                    placeholder="Notes…"
                    class="w-full bg-surface-container-low border rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none disabled:opacity-60"
                    :class="notesFormFor(task).errors.notes ? 'border-error' : 'border-outline/40'"
                    @blur="!disabled && saveTaskNotes(task)"
                />
                <p v-if="notesFormFor(task).errors.notes" class="text-xs text-error">{{ notesFormFor(task).errors.notes }}</p>
            </div>

            <div v-if="task.documents?.length" class="flex flex-col gap-1.5">
                <DocumentRow
                    v-for="document in task.documents"
                    :key="document.id"
                    :document="document"
                    :replace-url="taskDocumentUrl(task, document, 'replace')"
                    :delete-url="taskDocumentUrl(task, document)"
                    :can-manage="!disabled"
                    :refresh-options="{ only: ['assignment', 'flash'] }"
                />
            </div>

            <label v-if="!disabled" class="inline-flex items-center gap-1.5 text-xs font-bold text-primary cursor-pointer w-fit">
                <span class="material-symbols-outlined text-[16px]">attach_file</span>
                Attach files/photos
                <input type="file" multiple class="sr-only" @change="submitTaskUpload(task, $event)" />
            </label>
            <p v-if="uploadFormFor(task).errors['files.0']" class="text-xs text-error">{{ uploadFormFor(task).errors['files.0'] }}</p>
        </div>
    </div>
</template>
