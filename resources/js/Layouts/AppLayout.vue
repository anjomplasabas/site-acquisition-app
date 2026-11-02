<script setup>
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import Toast from '@/Components/Toast.vue'
import NotificationDropdown from '@/Components/Notifications/NotificationDropdown.vue'

const page = usePage()
const auth = page.props.auth
const isSuperAdmin = computed(() => page.props.auth?.isSuperAdmin ?? false)
const accessibleModules = computed(() => new Set(page.props.auth?.modules?.map(module => module.value) ?? []))

const sidebarOpen = ref(false)
const globalSearch = ref('')

function submitGlobalSearch() {
    const search = globalSearch.value.trim()
    if (!search) return

    router.get('/pipelines', { search })
}

const logoutForm = useForm({})
function logout() {
    logoutForm.post('/logout')
}

const navItems = [
    { label: 'Dashboard', icon: 'dashboard', route: 'dashboard', href: '/dashboard', module: 'dashboard' },
    { label: 'Pipelines', icon: 'account_tree', route: 'pipelines', href: '/pipelines', module: 'pipelines' },
    { label: 'Projects', icon: 'folder_special', route: 'projects', href: '/projects', module: 'projects' },
    { label: 'Site Management', icon: 'folder', route: 'site-management', href: '/site-management', module: 'projects' },
    { label: 'Site Planning', icon: 'engineering', route: 'site-planning', href: '/site-planning', module: 'site-planning' },
    { label: 'Network', icon: 'lan', route: 'network', href: '/network', module: 'network' },
    { label: 'Users', icon: 'group', route: 'users', href: '/users', module: 'users' },
    { label: 'Role Management', icon: 'shield', route: 'roles', href: '/roles', module: 'roles' },
    { label: 'Settings', icon: 'settings', route: 'settings', href: '/settings/approvals', module: 'settings' },
    { label: 'Audit Logs', icon: 'policy', route: 'audit-logs', href: '/audit-logs', superAdminOnly: true },
]

const visibleNavItems = computed(() =>
    navItems.filter(item => {
        if (item.superAdminOnly) return isSuperAdmin.value
        return isSuperAdmin.value || accessibleModules.value.has(item.module)
    })
)

function roleLabel(name) {
    return name?.replace(/_/g, ' ') ?? '-'
}

function isActive(routeName) {
    const map = {
        projects: 'Projects/',
        'site-management': 'SiteManagement/',
        'site-planning': 'SitePlanning/',
        'audit-logs': 'AuditLogs/',
    }
    if (map[routeName]) {
        return page.component.startsWith(map[routeName])
    }
    const componentName = routeName.charAt(0).toUpperCase() + routeName.slice(1)
    return page.component === componentName || page.component.startsWith(componentName + '/')
}
</script>

<template>
    <div class="min-h-screen flex bg-background font-sans text-on-surface">

        <Toast />

        <!-- Mobile overlay -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-black/40 lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- Sidebar -->
        <aside
            class="fixed lg:sticky lg:top-0 lg:h-screen inset-y-0 left-0 z-40 w-52 flex-none flex flex-col bg-surface-bright border-r border-outline/20 transition-transform duration-200 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <!-- Logo -->
            <div class="flex items-center gap-3 px-5 py-6 shrink-0">
                <img
                    src="/images/site-delivery-hub-mark.png"
                    alt=""
                    class="h-10 w-10 object-contain shrink-0"
                />
                <span class="font-headline text-sm font-extrabold leading-tight text-on-surface">Site Delivery<br><span class="text-primary">Hub</span></span>
                <button
                    class="lg:hidden ml-auto p-1 text-on-surface-variant hover:text-on-surface transition-colors rounded"
                    @click="sidebarOpen = false"
                >
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-3 flex flex-col gap-0.5 mt-2 overflow-y-auto">
                <template v-for="item in visibleNavItems" :key="item.label">
                    <a
                        :href="item.href"
                        @click="sidebarOpen = false"
                        class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium transition-colors"
                        :class="isActive(item.route)
                            ? 'bg-primary/8 text-primary border-l-2 border-primary rounded-r-lg'
                            : 'rounded-lg text-on-surface-variant hover:bg-surface-variant hover:text-on-surface'"
                    >
                        <span class="material-symbols-outlined text-[20px]">{{ item.icon }}</span>
                        {{ item.label }}
                    </a>
                </template>
            </nav>

        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0 lg:pl-0">

            <!-- Top bar -->
            <header class="flex items-center gap-3 px-4 sm:px-6 lg:px-8 py-4 bg-surface-bright border-b border-outline/20 sticky top-0 z-20">

                <!-- Hamburger (mobile) -->
                <button
                    class="lg:hidden p-2 -ml-1 text-on-surface-variant hover:text-on-surface transition-colors rounded-lg"
                    @click="sidebarOpen = true"
                >
                    <span class="material-symbols-outlined text-[22px]">menu</span>
                </button>

                <!-- Search (hidden on mobile) -->
                <div class="hidden sm:flex flex-1 relative max-w-lg">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <span class="material-symbols-outlined text-[18px]">search</span>
                    </div>
                    <input
                        v-model="globalSearch"
                        type="text"
                        placeholder="Search enterprise resources..."
                        class="w-full bg-surface-container-low border border-outline/40 rounded-lg py-2.5 pl-10 pr-4 text-sm text-on-surface placeholder:text-slate-400 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                        @keyup.enter="submitGlobalSearch"
                    />
                </div>

                <!-- Spacer on mobile -->
                <div class="flex-1 sm:hidden" />

                <div class="flex items-center gap-2 sm:gap-3 ml-auto">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="hidden sm:block text-right">
                            <p class="text-sm font-bold text-on-surface leading-tight">{{ auth.user?.full_name ?? 'Admin User' }}</p>
                            <p class="text-xs text-on-surface-variant">{{ auth.user?.email }}</p>
                            <p v-if="auth.user?.role_name" class="text-[10px] font-bold uppercase tracking-widest text-primary">{{ roleLabel(auth.user.role_name) }}</p>
                        </div>
                        <NotificationDropdown />
                        <a href="/profile" class="w-9 h-9 bg-primary/10 rounded-full flex items-center justify-center shrink-0 hover:bg-primary/20 transition-colors" title="My Profile">
                            <span class="material-symbols-outlined text-primary text-[20px]">person</span>
                        </a>
                        <button @click="logout" class="p-2 text-on-surface-variant hover:text-primary transition-colors" title="Log out">
                            <span class="material-symbols-outlined text-[20px]">logout</span>
                        </button>
                    </div>
                </div>

            </header>

            <!-- Page content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 flex flex-col gap-6 lg:gap-8">
                <slot />
            </main>

        </div>

    </div>
</template>
