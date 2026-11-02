<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({
    backUrl: {
        type: String,
        default: '/dashboard',
    },
})
</script>

<template>
    <Head title="Forbidden" />

    <main class="min-h-screen bg-background font-sans text-on-surface">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 py-8">
            <header class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="/images/site-delivery-hub-mark.png" alt="" class="h-11 w-11 object-contain" />
                    <span class="font-headline font-extrabold text-on-surface">Site Delivery Hub</span>
                </div>
                <span class="rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-bold text-primary">
                    403
                </span>
            </header>

            <section class="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[1fr_0.85fr]">
                <div class="max-w-2xl">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-outline/40 bg-surface-bright px-3 py-1.5 text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                        <span class="material-symbols-outlined text-[16px] text-primary">lock</span>
                        Access restricted
                    </div>

                    <h1 class="font-headline text-4xl font-extrabold tracking-tight text-on-surface sm:text-5xl">
                        This area is outside your current permissions.
                    </h1>

                    <p class="mt-4 max-w-xl text-base leading-7 text-on-surface-variant">
                        Site Delivery Hub keeps every pipeline stage permission-aware. Your account does not have access to this page or the record may have moved to a stage your role can no longer view.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link
                            href="/dashboard"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-3 text-sm font-bold text-white shadow-sm transition-all hover:brightness-110 active:scale-[0.99]"
                        >
                            <span class="material-symbols-outlined text-[18px]">dashboard</span>
                            Back to Dashboard
                        </Link>
                        <Link
                            :href="backUrl"
                            class="inline-flex items-center gap-2 rounded-lg border border-outline/40 bg-surface-bright px-5 py-3 text-sm font-bold text-on-surface-variant transition-colors hover:bg-surface-container"
                        >
                            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                            Go Back
                        </Link>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-xl border border-outline/10 bg-surface-bright p-6 shadow-sm">
                    <div class="absolute right-0 top-0 h-24 w-24 rounded-bl-full bg-primary/10"></div>
                    <div class="relative">
                        <div class="mb-8 flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                            <span class="material-symbols-outlined text-[30px] text-primary">shield_lock</span>
                        </div>

                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Permission check</p>
                        <div class="mt-4 space-y-3">
                            <div class="flex items-center gap-3 rounded-lg bg-surface-container-low px-4 py-3">
                                <span class="material-symbols-outlined text-[18px] text-primary">verified_user</span>
                                <span class="text-sm font-semibold text-on-surface">Role access verified</span>
                            </div>
                            <div class="flex items-center gap-3 rounded-lg bg-surface-container-low px-4 py-3">
                                <span class="material-symbols-outlined text-[18px] text-primary">account_tree</span>
                                <span class="text-sm font-semibold text-on-surface">Pipeline stage checked</span>
                            </div>
                            <div class="flex items-center gap-3 rounded-lg bg-surface-container-low px-4 py-3">
                                <span class="material-symbols-outlined text-[18px] text-primary">block</span>
                                <span class="text-sm font-semibold text-on-surface">Access not available</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
