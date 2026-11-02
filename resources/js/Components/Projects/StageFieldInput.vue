<script setup>
import { computed } from 'vue'
import DocumentStatusField from '@/Components/Projects/DocumentStatusField.vue'
import IspTaskListField from '@/Components/Projects/IspTaskListField.vue'
import SimpleDocumentUpload from '@/Components/Projects/SimpleDocumentUpload.vue'
import StageTaskListField from '@/Components/Projects/StageTaskListField.vue'

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { default: '' },
    notesValue: { default: '' },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    documents: { type: Array, default: () => [] },
    isps: { type: Array, default: () => [] },
    stageTasks: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    projectId: { type: [String, Number], default: null },
    stageId: { type: [String, Number], default: null },
    stageName: { type: String, default: null },
    networkSiteAssignmentId: { type: [String, Number], default: null },
    canManageNetworkFeasibility: { type: Boolean, default: false },
    hideLabel: { type: Boolean, default: false },
    notesError: { type: String, default: '' },
})

const notesRequired = computed(() =>
    (props.field.notes_required_when ?? []).includes(props.modelValue)
)

const emit = defineEmits(['update:modelValue', 'update:notesValue', 'change'])

function update(value) {
    emit('update:modelValue', value)
}

function updateFromEvent(event) {
    update(event.target.value)
    emit('change')
}

const matchingDocuments = computed(() =>
    props.documents.filter(document => document.category === props.field.document_category)
)

const matchingStageTasks = computed(() =>
    props.stageTasks.filter(task => task.field_key === props.field.key)
)
</script>

<template>
    <div :class="{ 'col-span-2': field.type === 'document_status' || field.type === 'document_upload' || field.type === 'isp_task_list' || field.type === 'stage_task_list' || (field.type === 'select' && (field.document_category || field.notes)) }">
        <label v-if="!hideLabel" class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant block mb-1">{{ field.label }}</label>

        <IspTaskListField
            v-if="field.type === 'isp_task_list'"
            :isps="isps"
            :disabled="disabled"
            :project-id="projectId"
            :stage-id="stageId"
            :stage-name="stageName"
            :network-site-assignment-id="networkSiteAssignmentId"
            :can-manage-network-feasibility="canManageNetworkFeasibility"
        />

        <StageTaskListField
            v-else-if="field.type === 'stage_task_list'"
            :stage-tasks="matchingStageTasks"
            :disabled="disabled"
            :project-id="projectId"
            :stage-id="stageId"
        />

        <DocumentStatusField
            v-else-if="field.type === 'document_status'"
            :field="field"
            :documents="matchingDocuments"
            :disabled="disabled"
            :project-id="projectId"
            :stage-id="stageId"
        />

        <div v-else-if="field.type === 'document_upload'" class="rounded-xl border border-dashed border-primary/30 bg-primary/5 px-4 py-3">
            <SimpleDocumentUpload
                :document-category="field.document_category"
                :documents="matchingDocuments"
                :disabled="disabled"
                :project-id="projectId"
                :stage-id="stageId"
            />
        </div>

        <select
            v-else-if="field.type === 'select'"
            :value="modelValue"
            :disabled="disabled"
            @change="updateFromEvent"
            class="w-full appearance-none cursor-pointer text-sm font-medium text-on-surface bg-surface-container-lowest border border-outline/40 rounded-xl pl-3.5 pr-10 py-2.5 bg-[right_0.75rem_center] bg-no-repeat transition-colors hover:border-outline/70 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary disabled:opacity-60 disabled:cursor-not-allowed"
            style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2349454f%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><polyline points=%226 9 12 15 18 9%22/></svg>')"
        >
            <option value="">— Select —</option>
            <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
        </select>

        <input
            v-else-if="field.type === 'date'"
            :value="modelValue"
            type="date"
            :disabled="disabled"
            @change="updateFromEvent"
            class="w-full text-sm font-medium text-on-surface bg-surface-container-lowest border border-outline/40 rounded-xl pl-3.5 pr-3.5 py-2.5 transition-colors hover:border-outline/70 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary disabled:opacity-60 disabled:cursor-not-allowed"
        />

        <select
            v-else-if="field.type === 'user_select'"
            :value="modelValue"
            :disabled="disabled"
            @change="updateFromEvent"
            class="w-full appearance-none cursor-pointer text-sm font-medium text-on-surface bg-surface-container-lowest border border-outline/40 rounded-xl pl-3.5 pr-10 py-2.5 bg-[right_0.75rem_center] bg-no-repeat transition-colors hover:border-outline/70 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary disabled:opacity-60 disabled:cursor-not-allowed"
            style="background-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2349454f%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><polyline points=%226 9 12 15 18 9%22/></svg>')"
        >
            <option value="">Unassigned</option>
            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.full_name }}</option>
        </select>

        <div
            v-else-if="field.type === 'computed'"
            class="w-full flex items-center gap-2 text-sm font-medium text-on-surface-variant bg-surface-container border border-outline/20 rounded-xl pl-3.5 pr-3.5 py-2.5"
        >
            <span class="material-symbols-outlined text-[15px] text-on-surface-variant/60">lock</span>
            {{ modelValue || '—' }}
        </div>

        <input
            v-else
            :value="modelValue"
            type="text"
            :disabled="disabled"
            @input="update($event.target.value)"
            @change="$emit('change')"
            class="w-full text-sm font-medium text-on-surface bg-surface-container-lowest border border-outline/40 rounded-xl pl-3.5 pr-3.5 py-2.5 transition-colors hover:border-outline/70 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary disabled:opacity-60 disabled:cursor-not-allowed"
        />

        <SimpleDocumentUpload
            v-if="field.type === 'select' && field.document_category"
            :document-category="field.document_category"
            :documents="matchingDocuments"
            :disabled="disabled"
            :project-id="projectId"
            :stage-id="stageId"
        />

        <textarea
            v-if="field.notes"
            :value="notesValue"
            :disabled="disabled"
            @input="$emit('update:notesValue', $event.target.value)"
            @change="$emit('change')"
            rows="2"
            :placeholder="notesRequired ? 'Remarks (required)' : 'Notes (optional)'"
            class="w-full text-sm text-on-surface bg-surface-container-lowest border rounded-xl px-3.5 py-2.5 mt-2 resize-none transition-colors hover:border-outline/70 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary disabled:opacity-60"
            :class="notesError ? 'border-error' : 'border-outline/40'"
        ></textarea>
        <p v-if="notesError" class="text-xs text-error mt-1">{{ notesError }}</p>

        <p v-if="error" class="text-xs text-error mt-1">{{ error }}</p>
    </div>
</template>
