<script setup>
defineProps({
    document: {
        type: Object,
        default: null,
    },
})

defineEmits(['close'])
</script>

<template>
    <Teleport to="body">
        <div v-if="document" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click.self="$emit('close')">
            <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-surface-bright shadow-2xl">
                <div class="flex shrink-0 items-center justify-between border-b border-outline/10 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="material-symbols-outlined shrink-0 text-[16px] text-on-surface-variant">image</span>
                        <span class="truncate text-sm font-semibold text-on-surface">{{ document.name }}</span>
                    </div>
                    <button type="button" class="ml-3 shrink-0 rounded-lg p-1.5 text-on-surface-variant transition-colors hover:bg-surface-container" @click="$emit('close')">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
                <div class="flex min-h-0 flex-1 items-center justify-center overflow-auto bg-black/30 p-4">
                    <img :src="document.url" :alt="document.name" class="max-h-full max-w-full rounded object-contain" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
