<script setup>
import { ref } from 'vue'
import { PH_LOCATIONS, PH_PROVINCES, citiesForProvince } from '@/utils/philippineLocations.js'

const props = defineProps({
    city: { type: String, default: '' },
    province: { type: String, default: '' },
    cityError: { type: String, default: '' },
    provinceError: { type: String, default: '' },
    dense: { type: Boolean, default: false },
})

const emit = defineEmits(['update:city', 'update:province'])

const ALL_CITIES = [...new Set(PH_LOCATIONS.flatMap(entry => entry.cities))].sort()

// "Other" mode is a UI concern (which widget to show), not derivable from the value alone — an
// empty value is ambiguous between "nothing chosen yet" (show the dropdown) and "chose Other but
// hasn't typed a value yet" (show the text input) — so it needs its own local state, seeded from
// whether the incoming value is already a non-curated custom entry (e.g. loaded on Show.vue's
// edit form for a site outside this curated list).
const provinceOther = ref(props.province !== '' && !PH_PROVINCES.includes(props.province))
const cityOther = ref(props.city !== '' && !ALL_CITIES.includes(props.city))

function selectProvince(event) {
    const value = event.target.value
    if (value === '__other__') {
        provinceOther.value = true
        emit('update:province', '')
        return
    }
    emit('update:province', value)
}

function useProvinceDropdown() {
    provinceOther.value = false
    emit('update:province', '')
}

function selectCity(event) {
    const value = event.target.value
    if (value === '__other__') {
        cityOther.value = true
        emit('update:city', '')
        return
    }
    emit('update:city', value)
}

function useCityDropdown() {
    cityOther.value = false
    emit('update:city', '')
}

function cityOptionsFor(province) {
    const scoped = citiesForProvince(province)
    return scoped.length ? scoped : ALL_CITIES
}
</script>

<template>
    <!-- display:contents so these two fields lay out as direct siblings in the parent grid,
         while still being a single Vue root element -->
    <div class="contents">
    <div class="flex flex-col gap-1.5">
        <label
            class="text-xs font-bold uppercase tracking-widest text-on-surface-variant"
            :class="{ 'text-on-surface-variant/60': dense }"
        >City <span class="text-error">*</span></label>

        <div v-if="!cityOther" class="relative">
            <select
                :value="city"
                @change="selectCity"
                class="w-full appearance-none bg-surface-container-low border rounded-lg px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-10"
                :class="[dense ? 'py-2.5' : 'py-3', cityError ? 'border-error' : 'border-outline/40']"
            >
                <option value="" disabled>Select city</option>
                <option v-for="c in cityOptionsFor(province)" :key="c" :value="c">{{ c }}</option>
                <option value="__other__">Other (type manually)</option>
            </select>
            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
        </div>
        <div v-else class="flex flex-col gap-1">
            <input
                :value="city"
                @input="$emit('update:city', $event.target.value)"
                type="text"
                placeholder="e.g. Quezon City"
                class="w-full bg-surface-container-low border rounded-lg px-4 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                :class="[dense ? 'py-2.5' : 'py-3', cityError ? 'border-error' : 'border-outline/40']"
            />
            <button type="button" @click="useCityDropdown" class="text-[11px] font-semibold text-primary hover:underline self-start">
                Choose from list instead
            </button>
        </div>
        <p v-if="cityError" class="text-xs text-error flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">error</span>{{ cityError }}
        </p>
    </div>

    <div class="flex flex-col gap-1.5">
        <label
            class="text-xs font-bold uppercase tracking-widest text-on-surface-variant"
            :class="{ 'text-on-surface-variant/60': dense }"
        >Province</label>

        <div v-if="!provinceOther" class="relative">
            <select
                :value="province"
                @change="selectProvince"
                class="w-full appearance-none bg-surface-container-low border rounded-lg px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all cursor-pointer pr-10"
                :class="[dense ? 'py-2.5' : 'py-3', provinceError ? 'border-error' : 'border-outline/40']"
            >
                <option value="">Select province</option>
                <option v-for="p in PH_PROVINCES" :key="p" :value="p">{{ p }}</option>
                <option value="__other__">Other (type manually)</option>
            </select>
            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-[18px]">expand_more</span>
        </div>
        <div v-else class="flex flex-col gap-1">
            <input
                :value="province"
                @input="$emit('update:province', $event.target.value)"
                type="text"
                placeholder="e.g. Ilocos Norte"
                class="w-full bg-surface-container-low border rounded-lg px-4 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                :class="[dense ? 'py-2.5' : 'py-3', provinceError ? 'border-error' : 'border-outline/40']"
            />
            <button type="button" @click="useProvinceDropdown" class="text-[11px] font-semibold text-primary hover:underline self-start">
                Choose from list instead
            </button>
        </div>
        <p v-if="provinceError" class="text-xs text-error flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">error</span>{{ provinceError }}
        </p>
    </div>
    </div>
</template>
