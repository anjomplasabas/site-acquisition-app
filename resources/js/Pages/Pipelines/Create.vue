<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import CityProvinceFields from '@/Components/Pipelines/CityProvinceFields.vue'
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    siteProfiles: Array,
    siteContacts: Array,
    siteProfileTypes: Array,
    duplicateSite: Object,
})

const isReAttempt = ref(false)
const contactMode = ref('new')
const duplicateSiteDialog = ref(props.duplicateSite)

const marketingFieldNames = [
    'lobby_booth_allowed',
    'lobby_standee_allowed',
    'lobby_flyers_stand_allowed',
    'roaming_sales_rep_allowed',
    'marketing_paraphernalia',
    'brochure_posting_location',
]

const form = useForm({
    endorsement_date: new Date().toISOString().slice(0, 10),

    // New site fields (used when not a re-attempt)
    site_name: '',
    site_type: '',
    site_type_other: '',
    developer: '',
    total_unit_count: '',
    total_floors_per_tower: '',
    total_commercial_unit_count: '',
    total_building_count: '',
    residential_floor_starts_at: '',
    floors_not_in_plan: '',
    turnover_rate: '',
    occupancy_rate: '',
    whz_location: '',
    total_whz_allocation: '',
    wifi_area_location: '',
    city: '',
    barangay: '',
    province: '',
    street: '',
    contact_id: '',
    contact_name: '',
    contact_designation: '',
    contact_email: '',
    contact_mobile_number: '',

    // Re-attempt: pick existing site profile
    site_profile_id: '',

    // Optional survey marketing fields
    lobby_booth_allowed: false,
    lobby_standee_allowed: false,
    lobby_flyers_stand_allowed: false,
    roaming_sales_rep_allowed: false,
    marketing_paraphernalia: '',
    brochure_posting_location: '',
})

function marketingPayload(data) {
    return Object.fromEntries(marketingFieldNames.map(field => [field, data[field]]))
}

const selectedContact = computed(() => props.siteContacts.find(contact => String(contact.id) === String(form.contact_id)))

watch(() => props.duplicateSite, (site) => {
    duplicateSiteDialog.value = site
})

function contactPayload(data) {
    if (contactMode.value === 'existing') {
        return {
            contact_id: data.contact_id,
        }
    }

    return {
        contact_name: data.contact_name,
        contact_designation: data.contact_designation,
        contact_email: data.contact_email,
        contact_mobile_number: data.contact_mobile_number,
    }
}

function submit() {
    if (isReAttempt.value) {
        form.transform(data => ({
            endorsement_date: data.endorsement_date,
            site_profile_id: data.site_profile_id,
            ...marketingPayload(data),
        })).post('/pipelines')
    } else {
        form.transform(data => ({
            endorsement_date: data.endorsement_date,
            site_name: data.site_name,
            site_type: data.site_type,
            site_type_other: data.site_type === 'Others' ? data.site_type_other : null,
            developer: data.developer,
            total_unit_count: data.total_unit_count,
            total_floors_per_tower: data.total_floors_per_tower,
            total_commercial_unit_count: data.total_commercial_unit_count,
            total_building_count: data.total_building_count,
            residential_floor_starts_at: data.residential_floor_starts_at,
            floors_not_in_plan: data.floors_not_in_plan,
            turnover_rate: data.turnover_rate,
            occupancy_rate: data.occupancy_rate,
            whz_location: data.whz_location,
            total_whz_allocation: data.total_whz_allocation,
            wifi_area_location: data.wifi_area_location,
            city: data.city,
            barangay: data.barangay,
            province: data.province,
            street: data.street,
            ...contactPayload(data),
            ...marketingPayload(data),
        })).post('/pipelines')
    }
}

function useDuplicateSite() {
    if (!duplicateSiteDialog.value) {
        return
    }

    isReAttempt.value = true
    form.site_profile_id = duplicateSiteDialog.value.id
    duplicateSiteDialog.value = null
}

function duplicateSiteUrl(site) {
    if (site?.pipeline_id) {
        return `/pipelines/${site.pipeline_id}`
    }

    return `/site-management/${site.id}`
}
</script>

<template>
    <Head title="New Site" />

    <!-- Header -->
    <div>
        <nav class="flex items-center gap-1.5 text-sm text-on-surface-variant mb-3">
            <a href="/pipelines" class="hover:text-on-surface transition-colors">Pipelines</a>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-primary font-semibold">New Site</span>
        </nav>
        <h1 class="font-headline text-2xl sm:text-3xl font-extrabold text-on-surface tracking-tight">Register New Site</h1>
        <p class="text-on-surface-variant text-sm mt-1 max-w-xl font-light">
            Enter the site details. The pipeline will start at Prospect status.
        </p>
    </div>

    <!-- Form -->
    <div class="w-full max-w-2xl">
        <form @submit.prevent="submit" class="flex flex-col gap-6">

            <!-- Pipeline Details -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6 flex flex-col gap-5">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Pipeline Details</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Endorsement Date</label>
                        <input
                            v-model="form.endorsement_date"
                            type="date"
                            class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer"
                            :class="form.errors.endorsement_date ? 'border-error' : 'border-outline/40'"
                        />
                        <p v-if="form.errors.endorsement_date" class="text-xs text-error flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.endorsement_date }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Site Details -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6 flex flex-col gap-5">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Site Details</p>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="isReAttempt" class="accent-primary w-4 h-4" />
                        <span class="text-xs font-semibold text-on-surface-variant">Re-attempt for existing site</span>
                    </label>
                </div>

                <!-- Re-attempt: pick existing site profile -->
                <div v-if="isReAttempt" class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Existing Site</label>
                    <div class="relative">
                        <select
                            v-model="form.site_profile_id"
                            class="w-full appearance-none bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-10"
                            :class="form.errors.site_profile_id ? 'border-error' : 'border-outline/40'"
                        >
                            <option value="" disabled>Select an existing site</option>
                            <option v-for="profile in siteProfiles" :key="profile.id" :value="profile.id">{{ profile.name }}</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
                    </div>
                    <p v-if="form.errors.site_profile_id" class="text-xs text-error flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.site_profile_id }}
                    </p>
                </div>

                <!-- New site: inline fields -->
                <template v-else>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Site Name <span class="text-error">*</span></label>
                            <input
                                v-model="form.site_name"
                                type="text"
                                placeholder="e.g. Lumina Residences Bulacan"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.site_name ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.site_name" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.site_name }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Type <span class="text-error">*</span></label>
                            <div class="relative">
                                <select
                                    v-model="form.site_type"
                                    class="w-full appearance-none bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-10"
                                    :class="form.errors.site_type ? 'border-error' : 'border-outline/40'"
                                >
                                    <option value="" disabled>Select type</option>
                                    <option v-for="t in siteProfileTypes" :key="t" :value="t">{{ t }}</option>
                                </select>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
                            </div>
                            <p v-if="form.errors.site_type" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.site_type }}
                            </p>
                            <input
                                v-if="form.site_type === 'Others'"
                                v-model="form.site_type_other"
                                type="text"
                                maxlength="255"
                                placeholder="Specify the type"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.site_type_other ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.site_type_other" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.site_type_other }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Developer</label>
                            <input
                                v-model="form.developer"
                                type="text"
                                placeholder="e.g. SMDC"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.developer ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.developer" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.developer }}
                            </p>
                        </div>

                        <CityProvinceFields
                            v-model:city="form.city"
                            v-model:province="form.province"
                            :city-error="form.errors.city"
                            :province-error="form.errors.province"
                        />

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Barangay</label>
                            <input
                                v-model="form.barangay"
                                type="text"
                                placeholder="e.g. Barangay Holy Spirit"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.barangay ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.barangay" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.barangay }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Street</label>
                            <input
                                v-model="form.street"
                                type="text"
                                placeholder="e.g. 123 Katipunan Ave."
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.street ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.street" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.street }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <!-- Per-tower totals support sites made up of multiple buildings. -->
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total Unit per Tower</label>
                            <input
                                v-model="form.total_unit_count"
                                type="number"
                                min="0"
                                placeholder="e.g. 500"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.total_unit_count ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.total_unit_count" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.total_unit_count }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total Floor per Tower</label>
                            <input
                                v-model="form.total_floors_per_tower"
                                type="number"
                                min="0"
                                placeholder="e.g. 30"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.total_floors_per_tower ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.total_floors_per_tower" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.total_floors_per_tower }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total Number of Commercial Units / Residential Floor</label>
                            <input
                                v-model="form.total_commercial_unit_count"
                                type="number"
                                min="0"
                                placeholder="e.g. 20"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.total_commercial_unit_count ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.total_commercial_unit_count" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.total_commercial_unit_count }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total No. of Towers / Buildings</label>
                            <input
                                v-model="form.total_building_count"
                                type="number" min="0"
                                placeholder="e.g. 3"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.total_building_count ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.total_building_count" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.total_building_count }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Residential Unit Starting Floor</label>
                            <input
                                v-model="form.residential_floor_starts_at"
                                type="number" min="0"
                                placeholder="e.g. 5"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.residential_floor_starts_at ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.residential_floor_starts_at" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.residential_floor_starts_at }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Floor Not in Plan</label>
                            <input
                                v-model="form.floors_not_in_plan"
                                type="text"
                                placeholder="e.g. 13, 14"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.floors_not_in_plan ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.floors_not_in_plan" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.floors_not_in_plan }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Turnover Rate (%)</label>
                            <input
                                v-model="form.turnover_rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                placeholder="e.g. 85"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.turnover_rate ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.turnover_rate" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.turnover_rate }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Occupancy Rate (%)</label>
                            <input
                                v-model="form.occupancy_rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                placeholder="e.g. 90"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.occupancy_rate ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.occupancy_rate" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.occupancy_rate }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">WHZ Location</label>
                            <input
                                v-model="form.whz_location"
                                type="text"
                                placeholder="e.g. Ground Floor Lobby"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.whz_location ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.whz_location" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.whz_location }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total WHZ Allocation</label>
                            <input
                                v-model="form.total_whz_allocation"
                                type="number"
                                min="0"
                                placeholder="e.g. 2"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.total_whz_allocation ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.total_whz_allocation" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.total_whz_allocation }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Free WiFi Area/Location</label>
                            <input
                                v-model="form.wifi_area_location"
                                type="text"
                                placeholder="e.g. Lobby"
                                class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                :class="form.errors.wifi_area_location ? 'border-error' : 'border-outline/40'"
                            />
                            <p v-if="form.errors.wifi_area_location" class="text-xs text-error flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.wifi_area_location }}
                            </p>
                        </div>
                    </div>

                    <!-- Contact Person (optional) -->
                    <div class="border-t border-outline/10 pt-4 flex flex-col gap-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Contact Person <span class="text-on-surface-variant/50 normal-case font-normal tracking-normal">(optional)</span></p>
                            <div class="inline-flex w-full rounded-lg border border-outline/30 bg-surface-container-low p-1 sm:w-auto">
                                <button
                                    type="button"
                                    class="flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors sm:flex-none"
                                    :class="contactMode === 'new' ? 'bg-surface-bright text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                                    @click="contactMode = 'new'"
                                >
                                    New contact
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors sm:flex-none"
                                    :class="contactMode === 'existing' ? 'bg-surface-bright text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                                    @click="contactMode = 'existing'"
                                >
                                    Existing contact
                                </button>
                            </div>
                        </div>

                        <div v-if="contactMode === 'existing'" class="flex flex-col gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Saved Contact</label>
                                <div class="relative">
                                    <select
                                        v-model="form.contact_id"
                                        class="w-full appearance-none bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-10 disabled:cursor-not-allowed disabled:opacity-60"
                                        :class="form.errors.contact_id ? 'border-error' : 'border-outline/40'"
                                        :disabled="!siteContacts.length"
                                    >
                                        <option value="" disabled>{{ siteContacts.length ? 'Select a saved contact' : 'No saved contacts available' }}</option>
                                        <option v-for="contact in siteContacts" :key="contact.id" :value="contact.id">{{ contact.name }}</option>
                                    </select>
                                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
                                </div>
                                <p v-if="form.errors.contact_id" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.contact_id }}
                                </p>
                            </div>

                            <div v-if="selectedContact" class="rounded-lg border border-outline/15 bg-surface-container-low p-4">
                                <p class="text-sm font-semibold text-on-surface">{{ selectedContact.name }}</p>
                                <p v-if="selectedContact.designation" class="mt-0.5 text-xs text-on-surface-variant">{{ selectedContact.designation }}</p>
                                <div class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Mobile</p>
                                        <p class="text-on-surface">{{ selectedContact.mobile_number || '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">Email</p>
                                        <p class="break-words text-on-surface">{{ selectedContact.email || '—' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Name</label>
                                <input
                                    v-model="form.contact_name"
                                    type="text"
                                    placeholder="e.g. Juan dela Cruz"
                                    class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="form.errors.contact_name ? 'border-error' : 'border-outline/40'"
                                />
                                <p v-if="form.errors.contact_name" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.contact_name }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Designation</label>
                                <input
                                    v-model="form.contact_designation"
                                    type="text"
                                    placeholder="e.g. Property Manager"
                                    class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="form.errors.contact_designation ? 'border-error' : 'border-outline/40'"
                                />
                                <p v-if="form.errors.contact_designation" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.contact_designation }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Mobile</label>
                                <input
                                    v-model="form.contact_mobile_number"
                                    type="text"
                                    placeholder="e.g. 09171234567"
                                    class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="form.errors.contact_mobile_number ? 'border-error' : 'border-outline/40'"
                                />
                                <p v-if="form.errors.contact_mobile_number" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.contact_mobile_number }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Email</label>
                                <input
                                    v-model="form.contact_email"
                                    type="email"
                                    placeholder="e.g. juan@example.com"
                                    class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                                    :class="form.errors.contact_email ? 'border-error' : 'border-outline/40'"
                                />
                                <p v-if="form.errors.contact_email" class="text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.contact_email }}
                                </p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Marketing Related -->
            <div class="bg-surface-bright rounded-xl border border-outline/10 p-6 flex flex-col gap-5">
                <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Marketing Related <span class="text-on-surface-variant/50 normal-case font-normal tracking-normal">(optional)</span></p>

                <div class="flex flex-col gap-2">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant/60">Lobby Marketing Options</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-on-surface">
                            <input type="checkbox" v-model="form.lobby_booth_allowed" class="accent-primary w-4 h-4" />
                            Booth
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-on-surface">
                            <input type="checkbox" v-model="form.lobby_standee_allowed" class="accent-primary w-4 h-4" />
                            Standee
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-on-surface">
                            <input type="checkbox" v-model="form.lobby_flyers_stand_allowed" class="accent-primary w-4 h-4" />
                            Flyers Stand
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-on-surface">
                            <input type="checkbox" v-model="form.roaming_sales_rep_allowed" class="accent-primary w-4 h-4" />
                            Roaming Sales Rep
                        </label>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Marketing Paraphernalia</label>
                    <textarea
                        v-model="form.marketing_paraphernalia"
                        rows="3"
                        placeholder="List allowed marketing materials..."
                        class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none"
                        :class="form.errors.marketing_paraphernalia ? 'border-error' : 'border-outline/40'"
                    />
                    <p v-if="form.errors.marketing_paraphernalia" class="text-xs text-error flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.marketing_paraphernalia }}
                    </p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Designated Brochure Posting Location</label>
                    <textarea
                        v-model="form.brochure_posting_location"
                        rows="3"
                        placeholder="Describe allowed posting areas..."
                        class="w-full bg-surface-container-low border rounded-lg px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all resize-none"
                        :class="form.errors.brochure_posting_location ? 'border-error' : 'border-outline/40'"
                    />
                    <p v-if="form.errors.brochure_posting_location" class="text-xs text-error flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span>{{ form.errors.brochure_posting_location }}
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex items-center gap-2 bg-primary text-white font-semibold text-sm px-6 py-3 rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-sm"
                >
                    <span v-if="form.processing" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-[16px]">add_location_alt</span>
                    Register Site
                </button>
                <a
                    href="/pipelines"
                    class="flex items-center gap-2 border border-outline/40 text-on-surface-variant font-semibold text-sm px-6 py-3 rounded-lg hover:bg-surface-container transition-colors"
                >
                    Cancel
                </a>
            </div>

        </form>
    </div>

    <div v-if="duplicateSiteDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
        <div class="w-full max-w-md rounded-xl border border-outline/20 bg-surface-bright p-6 shadow-xl">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50">
                    <span class="material-symbols-outlined text-amber-600">warning</span>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-bold text-on-surface">Site already exists</p>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Link the existing site instead of creating a duplicate record.
                    </p>
                </div>
            </div>

            <div class="mt-5 rounded-lg border border-outline/15 bg-surface-container-low p-4">
                <p class="text-sm font-semibold text-on-surface">{{ duplicateSiteDialog.name }}</p>
                <p v-if="duplicateSiteDialog.type" class="mt-0.5 text-xs text-on-surface-variant">{{ duplicateSiteDialog.type }}</p>
                <p v-if="duplicateSiteDialog.location" class="mt-2 text-xs text-on-surface-variant">{{ duplicateSiteDialog.location }}</p>
                <p v-if="duplicateSiteDialog.pipeline_ref" class="mt-2 text-[10px] font-mono text-primary">{{ duplicateSiteDialog.pipeline_ref }}</p>
            </div>

            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                <button type="button" @click="duplicateSiteDialog = null" class="rounded-lg border border-outline/40 px-4 py-2 text-sm font-semibold text-on-surface-variant hover:bg-surface-container">
                    Cancel
                </button>
                <a :href="duplicateSiteUrl(duplicateSiteDialog)" class="rounded-lg border border-outline/40 px-4 py-2 text-center text-sm font-semibold text-on-surface-variant hover:bg-surface-container">
                    Open existing site
                </a>
                <button type="button" @click="useDuplicateSite" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                    Use existing site
                </button>
            </div>
        </div>
    </div>
</template>
