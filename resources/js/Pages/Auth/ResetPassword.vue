<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({
    token: String,
    email: String,
})

const showPassword = ref(false)
const showConfirm = ref(false)

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post('/reset-password')
}
</script>

<template>
    <Head title="Reset Password" />
    <div class="bg-background font-sans text-on-background min-h-screen flex items-center justify-center p-6 selection:bg-primary/20">

        <div class="w-full max-w-md bg-surface-bright rounded-xl border border-outline/20 shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-10">

            <!-- Logo -->
            <div class="inline-flex items-center gap-3 mb-10">
                <img src="/images/site-delivery-hub-mark.png" alt="" class="h-10 w-10 object-contain" />
                <span class="font-headline font-bold text-lg text-on-surface">Site Delivery Hub</span>
            </div>

            <h2 class="text-2xl font-headline font-bold text-on-surface mb-2">Set a new password</h2>
            <p class="text-on-surface-variant text-sm mb-8">Choose a strong password you don't use elsewhere.</p>

            <form @submit.prevent="submit" class="flex flex-col gap-5">

                <div class="flex flex-col gap-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-3.5 px-4 text-sm text-on-surface outline-none"
                        readonly
                    />
                    <p v-if="form.errors.email" class="text-error text-xs ml-1">{{ form.errors.email }}</p>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">New Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <span class="material-symbols-outlined text-[18px]">lock</span>
                        </div>
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="Min. 8 characters"
                            class="w-full bg-white border border-outline/60 rounded-lg py-3.5 pl-11 pr-11 text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary transition-all outline-none text-sm"
                        />
                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-on-surface">
                            <span class="material-symbols-outlined text-[18px]">{{ showPassword ? 'visibility_off' : 'visibility' }}</span>
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="text-error text-xs ml-1">{{ form.errors.password }}</p>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Confirm Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <span class="material-symbols-outlined text-[18px]">lock</span>
                        </div>
                        <input
                            v-model="form.password_confirmation"
                            :type="showConfirm ? 'text' : 'password'"
                            placeholder="••••••••"
                            class="w-full bg-white border border-outline/60 rounded-lg py-3.5 pl-11 pr-11 text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary transition-all outline-none text-sm"
                        />
                        <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-on-surface">
                            <span class="material-symbols-outlined text-[18px]">{{ showConfirm ? 'visibility_off' : 'visibility' }}</span>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3.5 text-sm font-bold bg-primary text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                >
                    Reset Password
                </button>

                <a href="/" class="text-center text-sm text-on-surface-variant hover:text-primary transition-colors">
                    Back to login
                </a>

            </form>

        </div>
    </div>
</template>
