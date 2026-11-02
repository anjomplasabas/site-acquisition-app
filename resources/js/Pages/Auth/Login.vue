<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

const showPassword = ref(false)

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post('/')
}
</script>

<template>
    <Head title="Sign In" />
    <div class="bg-background font-sans text-on-background min-h-screen flex items-center justify-center p-6 selection:bg-primary/20">

        <!-- Card -->
        <div class="w-full max-w-250 grid grid-cols-1 md:grid-cols-2 bg-surface-bright rounded-xl overflow-hidden shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-outline/20">

            <!-- Left: Brand panel -->
            <div class="hidden md:flex flex-col items-center justify-center p-12 bg-gradient-to-br from-primary-container to-secondary-container border-r border-outline/10">
                <img src="/images/site-delivery-hub-mark.png" alt="Site Delivery Hub" class="h-28 w-28 object-contain mb-5" />
                <p class="font-headline text-2xl font-extrabold text-primary tracking-tight text-center mb-2">Site Delivery Hub</p>
                <h1 class="font-headline text-2xl font-bold text-on-surface tracking-tight text-center leading-snug mb-3">
                    Site Acquisition CRM &amp; Project Operations
                </h1>
                <p class="text-on-surface-variant text-sm text-center leading-relaxed max-w-xs">
                    Manage your site pipeline from prospecting to deployment, all in one place.
                </p>
            </div>

            <!-- Right: Login form -->
            <div class="p-10 md:p-14 flex flex-col justify-center">

                <div class="mb-10 text-center md:text-left">
                    <h2 class="text-2xl font-headline font-bold text-on-surface mb-2">Welcome back</h2>
                    <p class="text-on-surface-variant text-sm">Please enter your credentials to continue.</p>
                </div>

                <form @submit.prevent="submit" class="flex flex-col gap-5">

                    <!-- Email -->
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

                    <!-- Password -->
                    <div class="flex flex-col gap-2">
                        <div class="flex justify-between items-center ml-1">
                            <label class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Password</label>
                            <a href="/forgot-password" class="text-xs font-bold text-primary hover:opacity-80">Forgot Password?</a>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <span class="material-symbols-outlined text-[18px]">lock</span>
                            </div>
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="••••••••"
                                autocomplete="current-password"
                                class="w-full bg-white border border-outline/60 rounded-lg py-3.5 pl-11 pr-11 text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary transition-all outline-none text-sm"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-on-surface"
                            >
                                <span class="material-symbols-outlined text-[18px]">{{ showPassword ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-error text-xs ml-1">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center gap-3 px-1 pt-1">
                        <input
                            v-model="form.remember"
                            id="remember"
                            type="checkbox"
                            class="w-4 h-4 rounded-sm border-outline text-primary focus:ring-primary/20"
                        />
                        <label for="remember" class="text-xs text-on-surface-variant font-medium cursor-pointer">Keep me logged in for 30 days</label>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-primary text-white font-bold py-4 rounded-lg shadow-sm hover:brightness-110 active:scale-[0.99] transition-all duration-200 mt-2 disabled:opacity-60"
                    >
                        Sign In
                    </button>

                </form>

                <div class="mt-10 pt-10 border-t border-outline/10 text-center">

                </div>

            </div>
        </div>

    </div>
</template>
