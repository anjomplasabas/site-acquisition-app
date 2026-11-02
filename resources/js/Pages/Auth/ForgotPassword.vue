<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const status = computed(() => usePage().props.flash?.status)

const form = useForm({ email: '' })

function submit() {
    form.post('/forgot-password')
}
</script>

<template>
    <Head title="Forgot Password" />
    <div class="bg-background font-sans text-on-background min-h-screen flex items-center justify-center p-6 selection:bg-primary/20">

        <div class="w-full max-w-md bg-surface-bright rounded-xl border border-outline/20 shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-10">

            <!-- Logo -->
            <div class="inline-flex items-center gap-3 mb-10">
                <img src="/images/site-delivery-hub-mark.png" alt="" class="h-10 w-10 object-contain" />
                <span class="font-headline font-bold text-lg text-on-surface">Site Delivery Hub</span>
            </div>

            <h2 class="text-2xl font-headline font-bold text-on-surface mb-2">Forgot your password?</h2>
            <p class="text-on-surface-variant text-sm mb-8">Enter your email and we'll send you a reset link.</p>

            <!-- Status -->
            <div v-if="status" class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm mb-6">
                <span class="material-symbols-outlined text-green-500 text-[20px]">check_circle</span>
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-5">

                <div class="flex flex-col gap-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Work Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <span class="material-symbols-outlined text-[18px]">mail</span>
                        </div>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="e.g. admin@example.test"
                            autocomplete="email"
                            class="w-full bg-white border border-outline/60 rounded-lg py-3.5 pl-11 pr-4 text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary transition-all outline-none text-sm"
                        />
                    </div>
                    <p v-if="form.errors.email" class="text-error text-xs ml-1">{{ form.errors.email }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3.5 text-sm font-bold bg-primary text-white rounded-lg hover:brightness-110 active:scale-[0.99] transition-all disabled:opacity-60"
                >
                    Send Reset Link
                </button>

                <a href="/" class="text-center text-sm text-on-surface-variant hover:text-primary transition-colors">
                    Back to login
                </a>

            </form>

        </div>
    </div>
</template>
