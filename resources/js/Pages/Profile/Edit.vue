<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const page = usePage()
const auth = computed(() => page.props.auth.user)
const flash = computed(() => page.props.flash)

const profileForm = useForm({
    full_name: auth.value.full_name,
    email: auth.value.email,
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

function updateProfile() {
    profileForm.put('/profile', {
        onSuccess: () => profileForm.reset(),
        preserveScroll: true,
    })
}

function updatePassword() {
    passwordForm.put('/profile/password', {
        onSuccess: () => passwordForm.reset(),
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="My Profile" />
    <!-- Page header -->
    <div>
        <h1 class="font-headline text-3xl font-bold text-on-surface tracking-tight">My Profile</h1>
        <p class="text-on-surface-variant text-sm mt-1">Manage your account information and password.</p>
    </div>

    <!-- Force password change warning -->
    <div v-if="auth.force_password_change" class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-sm">
        <span class="material-symbols-outlined text-amber-500 text-[20px] shrink-0 mt-0.5">warning</span>
        <p>You must change your password before you can access the rest of the application.</p>
    </div>

    <!-- Success flash -->
    <div v-if="flash.success" class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800 text-sm">
        <span class="material-symbols-outlined text-green-500 text-[20px]">check_circle</span>
        {{ flash.success }}
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Personal Information -->
        <div class="bg-surface-bright rounded-xl border border-outline/10">
            <div class="px-8 pt-8 pb-4">
                <h2 class="text-base font-bold text-on-surface">Personal Information</h2>
                <p class="text-xs text-on-surface-variant mt-0.5">Update your name and email address.</p>
            </div>
            <form @submit.prevent="updateProfile" class="px-8 pb-8 flex flex-col gap-5">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Full Name</label>
                    <input
                        v-model="profileForm.full_name"
                        type="text"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                    />
                    <p v-if="profileForm.errors.full_name" class="text-error text-xs mt-1">{{ profileForm.errors.full_name }}</p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Email</label>
                    <input
                        v-model="profileForm.email"
                        type="email"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                    />
                    <p v-if="profileForm.errors.email" class="text-error text-xs mt-1">{{ profileForm.errors.email }}</p>
                </div>

                <div>
                    <button
                        type="submit"
                        :disabled="profileForm.processing"
                        class="px-5 py-2.5 text-sm font-bold bg-primary text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                    >
                        Save Changes
                    </button>
                </div>

            </form>
        </div>

        <!-- Change Password -->
        <div class="bg-surface-bright rounded-xl border border-outline/10">
            <div class="px-8 pt-8 pb-4">
                <h2 class="text-base font-bold text-on-surface">Change Password</h2>
                <p class="text-xs text-on-surface-variant mt-0.5">Use a strong password you don't use elsewhere.</p>
            </div>
            <form @submit.prevent="updatePassword" class="px-8 pb-8 flex flex-col gap-5">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Current Password</label>
                    <input
                        v-model="passwordForm.current_password"
                        type="password"
                        placeholder="••••••••"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                    />
                    <p v-if="passwordForm.errors.current_password" class="text-error text-xs mt-1">{{ passwordForm.errors.current_password }}</p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">New Password</label>
                    <input
                        v-model="passwordForm.password"
                        type="password"
                        placeholder="Min. 8 characters"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                    />
                    <p v-if="passwordForm.errors.password" class="text-error text-xs mt-1">{{ passwordForm.errors.password }}</p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5">Confirm New Password</label>
                    <input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        placeholder="••••••••"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3 px-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                    />
                </div>

                <div>
                    <button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="px-5 py-2.5 text-sm font-bold bg-primary text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                    >
                        Update Password
                    </button>
                </div>

            </form>
        </div>

    </div>
</template>
