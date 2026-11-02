<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { onUnmounted, ref } from 'vue'

const toasts = ref([])
let nextId = 0

function pushToast(type, message) {
    if (!message) return

    const id = nextId++
    toasts.value.push({ id, type, message })
    setTimeout(() => dismiss(id), 5000)
}

function dismiss(id) {
    toasts.value = toasts.value.filter(toast => toast.id !== id)
}

function showFlash(flash) {
    pushToast('success', flash?.success)
    pushToast('error', flash?.error)
}

// Fire on every completed visit rather than watching flash.success/flash.error directly —
// a plain watcher only re-triggers on a *change*, so two consecutive actions that flash the
// exact same message (e.g. saving the same form twice in a row) would silently show the
// toast once and never again.
showFlash(usePage().props.flash)
const removeSuccessListener = router.on('success', event => showFlash(event.detail.page.props.flash))
onUnmounted(removeSuccessListener)
</script>

<template>
    <div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-[calc(100%-2rem)] sm:w-full sm:max-w-sm">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="flex items-center gap-3 rounded-xl border px-5 py-3.5 text-sm font-medium shadow-lg"
                :class="toast.type === 'success'
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
                    : 'bg-red-50 border-red-200 text-red-700'"
            >
                <span class="material-symbols-outlined text-[18px] shrink-0">{{ toast.type === 'success' ? 'check_circle' : 'error' }}</span>
                <span class="flex-1">{{ toast.message }}</span>
                <button
                    type="button"
                    @click="dismiss(toast.id)"
                    class="shrink-0 opacity-70 hover:opacity-100 transition-opacity"
                    :class="toast.type === 'success' ? 'text-emerald-500 hover:text-emerald-700' : 'text-red-500 hover:text-red-700'"
                >
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
