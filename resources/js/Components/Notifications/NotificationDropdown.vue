<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const page = usePage()
const items = computed(() => page.props.auth?.notifications?.items ?? [])
const unreadCount = computed(() => page.props.auth?.notifications?.unread_count ?? 0)
const unreadCountLabel = computed(() => (unreadCount.value > 9 ? '9+' : unreadCount.value))

const open = ref(false)
const rootEl = ref(null)

function toggle() {
    open.value = !open.value
}

function onNotificationClick(item) {
    open.value = false
    router.post(`/notifications/${item.id}/read`)
}

function markAllRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true })
}

function onClickOutside(event) {
    if (open.value && rootEl.value && !rootEl.value.contains(event.target)) {
        open.value = false
    }
}

onMounted(() => document.addEventListener('click', onClickOutside))
onUnmounted(() => document.removeEventListener('click', onClickOutside))

function ageFor(isoDate) {
    const seconds = Math.floor((Date.now() - new Date(isoDate)) / 1000)
    if (seconds < 60) { return 'just now' }

    const units = [
        ['year', 31536000], ['month', 2592000], ['week', 604800],
        ['day', 86400], ['hour', 3600], ['minute', 60],
    ]

    for (const [label, secondsPerUnit] of units) {
        const value = Math.floor(seconds / secondsPerUnit)
        if (value >= 1) { return `${value}${label[0]} ago` }
    }

    return 'just now'
}
</script>

<template>
    <div ref="rootEl" class="relative">
        <button
            type="button"
            @click="toggle"
            class="relative p-2 text-on-surface-variant hover:text-primary transition-colors"
            title="Notifications"
        >
            <span class="material-symbols-outlined text-[20px]">notifications</span>
            <span
                v-if="unreadCount > 0"
                class="absolute top-0.5 right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center leading-none"
            >{{ unreadCountLabel }}</span>
        </button>

        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="absolute right-0 top-full mt-2 w-80 max-h-96 overflow-y-auto bg-surface-bright border border-outline/15 rounded-xl shadow-lg z-30"
            >
                <div class="flex items-center justify-between px-4 py-3 border-b border-outline/10">
                    <p class="text-sm font-bold text-on-surface">Notifications</p>
                    <button
                        v-if="items.length"
                        type="button"
                        @click="markAllRead"
                        class="text-xs font-semibold text-primary hover:underline"
                    >Mark all read</button>
                </div>

                <div v-if="!items.length" class="px-4 py-8 text-center text-sm text-on-surface-variant">
                    You're all caught up.
                </div>

                <button
                    v-for="item in items"
                    :key="item.id"
                    type="button"
                    @click="onNotificationClick(item)"
                    class="w-full flex items-start gap-3 px-4 py-3 text-left border-b border-outline/5 last:border-b-0 hover:bg-surface-container-low transition-colors"
                >
                    <span class="w-2 h-2 mt-1.5 rounded-full bg-primary shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-on-surface truncate">{{ item.title }}</p>
                        <p class="text-xs text-on-surface-variant mt-0.5 line-clamp-2">{{ item.message }}</p>
                        <p class="text-[10px] text-on-surface-variant/60 mt-1">{{ ageFor(item.created_at) }}</p>
                    </div>
                </button>
            </div>
        </Transition>
    </div>
</template>
