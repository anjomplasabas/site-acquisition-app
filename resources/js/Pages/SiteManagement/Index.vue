<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { getInitials, statusStyle } from '@/utils/pipeline.js'

defineOptions({ layout: AppLayout })

const props = defineProps({
    sites: Object,
    contacts: Object,
    tab: String,
    filters: Object,
    flash: Object,
})

// ─── Tab state ────────────────────────────────────────────────────────────────
const activeTab = ref(props.tab ?? 'sites')

function switchTab(tab) {
    activeTab.value = tab
    router.get('/site-management', { tab }, { preserveState: true, replace: true })
}

// ─── Search ───────────────────────────────────────────────────────────────────
const siteSearch = ref(props.filters?.search ?? '')
const contactSearch = ref(props.filters?.contact_search ?? '')

let searchTimer = null

watch(siteSearch, (val) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.get('/site-management', { tab: 'sites', search: val }, { preserveState: true, replace: true })
    }, 300)
})

watch(contactSearch, (val) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.get('/site-management', { tab: 'contacts', contact_search: val }, { preserveState: true, replace: true })
    }, 300)
})

// ─── Success banner ───────────────────────────────────────────────────────────
const showSuccessBanner = ref(!!props.flash?.success)

// ─── Site Contact modal ───────────────────────────────────────────────────────
const showContactModal = ref(false)
const editingContact = ref(null)

const contactForm = useForm({
    name: '',
    designation: '',
    email: '',
    mobile_number: '',
})

function openCreateContact() {
    editingContact.value = null
    contactForm.reset()
    showContactModal.value = true
}

function openEditContact(contact) {
    editingContact.value = contact
    contactForm.name = contact.name ?? ''
    contactForm.designation = contact.designation ?? ''
    contactForm.email = contact.email ?? ''
    contactForm.mobile_number = contact.mobile_number ?? ''
    showContactModal.value = true
}

function submitContact() {
    if (editingContact.value) {
        contactForm.patch(`/site-contacts/${editingContact.value.id}`, {
            onSuccess: () => { showContactModal.value = false },
        })
    } else {
        contactForm.post('/site-contacts', {
            onSuccess: () => {
                showContactModal.value = false
                contactForm.reset()
            },
        })
    }
}

function deleteContact(contact) {
    if (confirm(`Delete "${contact.name}"? This cannot be undone.`)) {
        router.delete(`/site-contacts/${contact.id}`)
    }
}

</script>

<template>
    <Head title="Site Management" />

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="font-headline text-2xl sm:text-3xl font-extrabold text-on-surface tracking-tight">Site Management</h1>
            <p class="text-on-surface-variant text-sm mt-1 font-light">Project sites and linked acquisition pipelines.</p>
        </div>
        <button
            v-if="activeTab === 'contacts'"
            @click="openCreateContact"
            class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all shadow-sm"
        >
            <span class="material-symbols-outlined text-[16px]">add</span>
            New Contact
        </button>
    </div>

    <!-- Success banner -->
    <div
        v-if="showSuccessBanner"
        class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-5 py-3.5 text-sm font-medium"
    >
        <span class="material-symbols-outlined text-[18px]">check_circle</span>
        {{ flash.success }}
        <button @click="showSuccessBanner = false" class="ml-auto text-emerald-500 hover:text-emerald-700 transition-colors">
            <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
    </div>

    <!-- Tabs -->
    <div class="border-b border-outline/15">
        <div class="flex gap-0">
            <button
                v-for="t in [{ key: 'sites', label: 'Sites', icon: 'location_city' }, { key: 'contacts', label: 'Contacts', icon: 'contacts' }]"
                :key="t.key"
                @click="switchTab(t.key)"
                class="flex items-center gap-2 px-5 py-3 text-sm font-semibold border-b-2 transition-colors"
                :class="activeTab === t.key
                    ? 'border-primary text-primary'
                    : 'border-transparent text-on-surface-variant hover:text-on-surface'"
            >
                <span class="material-symbols-outlined text-[18px]">{{ t.icon }}</span>
                {{ t.label }}
                <span
                    class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                    :class="activeTab === t.key ? 'bg-primary/10 text-primary' : 'bg-surface-container text-on-surface-variant'"
                >
                    {{ t.key === 'sites' ? sites.total : contacts.total }}
                </span>
            </button>
        </div>
    </div>

    <!-- Sites tab -->
    <div v-if="activeTab === 'sites'" class="flex flex-col gap-5">

        <!-- Search -->
        <div class="relative max-w-sm">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px] pointer-events-none">search</span>
            <input
                v-model="siteSearch"
                type="text"
                placeholder="Search sites..."
                class="w-full bg-surface-bright border border-outline/40 rounded-lg pl-9 pr-4 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
            />
        </div>

        <!-- Grid -->
        <div v-if="sites.data.length" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <a
                v-for="site in sites.data"
                :key="site.id"
                :href="site.site_profile?.id ? `/site-management/${site.site_profile.id}` : `/pipelines/${site.id}`"
                class="bg-surface-bright rounded-xl border border-outline/10 p-5 flex flex-col gap-3 hover:border-outline/30 transition-colors"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm text-on-surface truncate">{{ site.site_profile?.name ?? site.name }}</p>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            <span
                                class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full border"
                                :class="statusStyle(site.status).badge"
                            >
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="statusStyle(site.status).dot"></span>
                                {{ site.status }}
                            </span>
                            <span v-if="site.site_profile?.type" class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full bg-primary/8 text-primary">
                                {{ site.site_profile.type }}
                            </span>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-on-surface-variant/40 text-[18px] shrink-0 mt-0.5">open_in_new</span>
                </div>

                <div v-if="site.site_profile?.address" class="flex items-start gap-1.5 text-xs text-on-surface-variant">
                    <span class="material-symbols-outlined text-[14px] mt-0.5 shrink-0">location_on</span>
                    <span>{{ [site.site_profile.address.city, site.site_profile.address.province].filter(Boolean).join(', ') || '—' }}</span>
                </div>

                <div v-if="site.project_manager" class="flex items-center gap-2 pt-2 border-t border-outline/8">
                    <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center shrink-0 text-[9px] font-bold text-primary">
                        {{ getInitials(site.project_manager.full_name) }}
                    </div>
                    <p class="text-xs text-on-surface-variant truncate">{{ site.project_manager.full_name }}</p>
                </div>

                <p class="text-[10px] font-mono text-on-surface-variant/50">{{ site.pipeline_id }}</p>
            </a>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-16 text-center">
            <span class="material-symbols-outlined text-[48px] text-on-surface-variant/30 mb-3">location_city</span>
            <p class="text-sm font-semibold text-on-surface-variant">No service-ready sites yet</p>
            <p class="text-xs text-on-surface-variant/60 mt-1">Sites appear here once they reach RFD, RFS, or Completed.</p>
        </div>

        <!-- Pagination -->
        <div v-if="sites.last_page > 1" class="flex items-center justify-between text-sm text-on-surface-variant">
            <span>Showing {{ sites.from }}–{{ sites.to }} of {{ sites.total }}</span>
            <div class="flex gap-2">
                <a v-if="sites.prev_page_url" :href="sites.prev_page_url + '&tab=sites'" class="px-3 py-1.5 rounded-lg border border-outline/40 hover:bg-surface-container transition-colors">Previous</a>
                <a v-if="sites.next_page_url" :href="sites.next_page_url + '&tab=sites'" class="px-3 py-1.5 rounded-lg border border-outline/40 hover:bg-surface-container transition-colors">Next</a>
            </div>
        </div>
    </div>

    <!-- Contacts tab -->
    <div v-else-if="activeTab === 'contacts'" class="flex flex-col gap-5">

        <!-- Search -->
        <div class="relative max-w-sm">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px] pointer-events-none">search</span>
            <input
                v-model="contactSearch"
                type="text"
                placeholder="Search contacts…"
                class="w-full bg-surface-bright border border-outline/40 rounded-lg pl-9 pr-4 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
            />
        </div>

        <!-- Table -->
        <div v-if="contacts.data.length" class="bg-surface-bright rounded-xl border border-outline/10 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline/10">
                        <th class="text-left px-5 py-3.5 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Name</th>
                        <th class="text-left px-5 py-3.5 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant hidden sm:table-cell">Contact</th>
                        <th class="text-left px-5 py-3.5 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant hidden md:table-cell">Sites</th>
                        <th class="px-5 py-3.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="contact in contacts.data"
                        :key="contact.id"
                        class="border-b border-outline/8 last:border-0 hover:bg-surface-container/40 transition-colors"
                    >
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0 text-[10px] font-bold text-primary">
                                    {{ getInitials(contact.name) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-on-surface">{{ contact.name }}</p>
                                    <p v-if="contact.designation" class="text-xs text-on-surface-variant">{{ contact.designation }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 hidden sm:table-cell">
                            <p v-if="contact.mobile_number" class="text-on-surface-variant">{{ contact.mobile_number }}</p>
                            <p v-if="contact.email" class="text-xs text-on-surface-variant/60">{{ contact.email }}</p>
                            <span v-if="!contact.mobile_number && !contact.email" class="text-on-surface-variant/40">—</span>
                        </td>
                        <td class="px-5 py-3.5 hidden md:table-cell">
                            <span class="text-on-surface-variant">{{ contact.site_profiles_count }} site{{ contact.site_profiles_count !== 1 ? 's' : '' }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    @click="openEditContact(contact)"
                                    class="p-1.5 text-on-surface-variant hover:text-primary transition-colors rounded-lg hover:bg-primary/5"
                                >
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>
                                <button
                                    @click="deleteContact(contact)"
                                    class="p-1.5 text-on-surface-variant hover:text-error transition-colors rounded-lg hover:bg-error/5"
                                >
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-16 text-center">
            <span class="material-symbols-outlined text-[48px] text-on-surface-variant/30 mb-3">contacts</span>
            <p class="text-sm font-semibold text-on-surface-variant">No contacts found</p>
            <p class="text-xs text-on-surface-variant/60 mt-1 mb-4">{{ contactSearch ? 'Try a different search term.' : 'Add a contact to reuse across site profiles.' }}</p>
            <button v-if="!contactSearch" @click="openCreateContact" class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-4 py-2 rounded-lg hover:brightness-110 transition-all shadow-sm">
                <span class="material-symbols-outlined text-[16px]">add</span>
                New Contact
            </button>
        </div>

        <!-- Pagination -->
        <div v-if="contacts.last_page > 1" class="flex items-center justify-between text-sm text-on-surface-variant">
            <span>Showing {{ contacts.from }}–{{ contacts.to }} of {{ contacts.total }}</span>
            <div class="flex gap-2">
                <a v-if="contacts.prev_page_url" :href="contacts.prev_page_url + '&tab=contacts'" class="px-3 py-1.5 rounded-lg border border-outline/40 hover:bg-surface-container transition-colors">Previous</a>
                <a v-if="contacts.next_page_url" :href="contacts.next_page_url + '&tab=contacts'" class="px-3 py-1.5 rounded-lg border border-outline/40 hover:bg-surface-container transition-colors">Next</a>
            </div>
        </div>
    </div>

    <!-- Contact Modal -->
    <Teleport to="body">
        <div v-if="showContactModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showContactModal = false" />
            <div class="relative z-10 w-full max-w-md mx-4 bg-surface-bright rounded-2xl border border-outline/10 shadow-2xl">

                <div class="flex items-center justify-between px-6 py-5 border-b border-outline/10">
                    <div>
                        <h2 class="font-headline text-lg font-extrabold text-on-surface">{{ editingContact ? 'Edit Contact' : 'New Contact' }}</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5 font-light">This contact can be reused across multiple site profiles.</p>
                    </div>
                    <button type="button" @click="showContactModal = false" class="text-on-surface-variant hover:text-on-surface transition-colors rounded-lg p-1">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="submitContact" class="px-6 py-5 flex flex-col gap-4">

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Name <span class="text-error">*</span></label>
                        <input v-model="contactForm.name" type="text" placeholder="e.g. Juan dela Cruz"
                            class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                            :class="contactForm.errors.name ? 'border-error' : 'border-outline/40'" />
                        <p v-if="contactForm.errors.name" class="text-xs text-error flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">error</span>{{ contactForm.errors.name }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Designation</label>
                        <input v-model="contactForm.designation" type="text" placeholder="e.g. Property Manager"
                            class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                            :class="contactForm.errors.designation ? 'border-error' : 'border-outline/40'" />
                        <p v-if="contactForm.errors.designation" class="text-xs text-error flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">error</span>{{ contactForm.errors.designation }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Mobile</label>
                            <input v-model="contactForm.mobile_number" type="text" placeholder="e.g. 09171234567"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="contactForm.errors.mobile_number ? 'border-error' : 'border-outline/40'" />
                            <p v-if="contactForm.errors.mobile_number" class="text-xs text-error flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">error</span>{{ contactForm.errors.mobile_number }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Email</label>
                            <input v-model="contactForm.email" type="email" placeholder="e.g. juan@example.com"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="contactForm.errors.email ? 'border-error' : 'border-outline/40'" />
                            <p v-if="contactForm.errors.email" class="text-xs text-error flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">error</span>{{ contactForm.errors.email }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-1">
                        <button type="button" @click="showContactModal = false"
                            class="flex items-center gap-2 border border-outline/40 text-on-surface-variant font-semibold text-sm px-5 py-2.5 rounded-lg hover:bg-surface-container transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="contactForm.processing"
                            class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60 shadow-sm">
                            <span v-if="contactForm.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                            <span v-else class="material-symbols-outlined text-[16px]">{{ editingContact ? 'save' : 'add_circle' }}</span>
                            {{ editingContact ? 'Save Changes' : 'Create Contact' }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </Teleport>
</template>
