<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import NotificationBell from '../components/NotificationIcon.vue'
import AppToast from '../components/ToastAlert.vue'
import ConfirmModal from '../components/ModalConfirm.vue'
const router = useRouter()
const authStore = useAuthStore()

const sidebarOpen = ref(false)
const toastRef = ref(null)
const confirmModalRef = ref(null)
const user = computed(() => authStore.user)

const role = computed(() => {
    const userRole = user.value?.role
    console.log(user.value.role.permissions);
    if (typeof userRole === 'string') {
        return userRole
    }

    return userRole?.name ?? 'Member'
})

const normalizedRole = computed(() => role.value.toLowerCase())

// Role-based sidebar navigation
const allMenuItems = [
    {label: 'Dashboard',path: '/dashboard',permissions: ['dashboard.view'],icon: 'dashboard'},
    {label: 'User Management',path: '/admin/users',permissions: ['users.manage'],icon: 'users'},
    {label: 'Customers',path: '/customers',permissions: ['customers.view'],icon: 'customers'},
    {label: 'Products',path: '/products',permissions: ['products.view'],icon: 'products'},
    {label: 'Orders',path: '/orders',permissions: ['orders.view_all', 'orders.view_assigned'],icon: 'orders'},
    {label: 'Role Management',path: '/admin/roles',permissions: ['roles.manage'],icon: 'roles'},
    {label: 'Permission Management',path: '/admin/permissions',permissions: ['permissions.manage'],icon: 'permissions'},
    {label: 'Audit Logs',path: '/admin/audit-logs',permissions: ['audit_logs.view'],icon: 'audit'},
]

// const menuItems = computed(() => {
//     // return allMenuItems.filter((item) =>
//     //     item.roles.includes(normalizedRole.value)
//     // )
//     allMenuItems.filter((item) =>
//         authStore.hasAnyPermission(item.permissions)
//     )
// })
const menuItems = computed(() => {
    console.log(authStore.hasAnyPermission(['dashboard.view']))
    return allMenuItems.filter(item =>
        authStore.hasAnyPermission(item.permissions)
    )
})

console.log(menuItems.value)

const closeSidebar = () => {
    sidebarOpen.value = false
}

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value
}

const handleResize = () => {
    if (window.innerWidth >= 1024) {
        closeSidebar()
    }
}
//signout handle
const handleLogout = async () => {
    const confirmed = await confirmModalRef.value?.open({
        title: 'Sign out?',
        message: 'Are you sure you want to sign out of your OMS account?',
        confirmText: 'Yes, sign out',
        cancelText: 'Stay logged in',
        variant: 'danger',
    })

    if (!confirmed) return

    closeSidebar()

    try {
        await authStore.logout()
        await router.push('/login')
    } catch (error) {
        console.error('Logout failed:', error)

        toastRef.value?.show(
            error.response?.data?.message || 'Unable to sign out. Please try again.',
            'error'
        )
    }
}


onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Mobile sidebar overlay -->
        <Transition name="fade">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden"
                @click="closeSidebar"
            ></div>
        </Transition>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col
                   bg-slate-950 text-white shadow-xl
                   transition-transform duration-300 ease-in-out"
            :class="
                sidebarOpen
                    ? 'translate-x-0'
                    : '-translate-x-full lg:translate-x-0'
            "
        >
            <!-- Logo -->
            <div
                class="flex h-20 shrink-0 items-center gap-3
                       border-b border-white/10 px-6"
            >
                <div
                    class="flex h-11 w-11 items-center justify-center
                           rounded-xl bg-blue-600 text-white
                           shadow-lg shadow-blue-600/20"
                >
                    <svg
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 12l9 4.5 9-4.5M3 16.5l9 4.5 9-4.5"
                        />
                    </svg>
                </div>

                <div>
                    <h1 class="text-xl font-bold tracking-wide">OMS</h1>
                    <p class="mt-0.5 text-xs text-slate-400">
                        Order Management
                    </p>
                </div>

                <!-- Mobile close button -->
                <button
                    type="button"
                    class="ml-auto flex h-8 w-8 items-center justify-center
                           rounded-lg text-slate-400 transition
                           hover:bg-white/10 hover:text-white lg:hidden"
                    aria-label="Close navigation"
                    @click="closeSidebar"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m6 6 12 12M18 6 6 18"
                        />
                    </svg>
                </button>
            </div>

            <!-- Navigation -->
            <div class="flex-1 overflow-y-auto px-4 py-6">
                <p
                    class="mb-3 px-3 text-[11px] font-semibold
                           uppercase tracking-[0.16em] text-slate-500"
                >
                    Panel
                </p>

                <nav class="space-y-1.5">
                    <RouterLink
                        v-for="item in menuItems"
                        :key="item.path"
                        :to="item.path"
                        class="group flex items-center gap-3 rounded-xl
                               px-3 py-2.5 text-sm font-medium
                               text-slate-300 transition-all duration-200
                               hover:bg-white/5 hover:text-white"
                        active-class="!bg-blue-600 !text-white shadow-md shadow-blue-950/30"
                        @click="closeSidebar"
                    >
                        <!-- Dashboard icon -->
                        <span
                            class="flex h-9 w-9 shrink-0 items-center
                                   justify-center rounded-lg bg-white/5
                                   transition group-hover:bg-white/10"
                        >
                            <svg
                                v-if="item.icon === 'dashboard'"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <rect
                                    x="3"
                                    y="3"
                                    width="7"
                                    height="7"
                                    rx="1.5"
                                />
                                <rect
                                    x="14"
                                    y="3"
                                    width="7"
                                    height="5"
                                    rx="1.5"
                                />
                                <rect
                                    x="14"
                                    y="12"
                                    width="7"
                                    height="9"
                                    rx="1.5"
                                />
                                <rect
                                    x="3"
                                    y="14"
                                    width="7"
                                    height="7"
                                    rx="1.5"
                                />
                            </svg>

                            <!-- Orders icon -->
                            <svg
                                v-else-if="item.icon === 'orders'"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <rect
                                    x="4"
                                    y="4"
                                    width="16"
                                    height="17"
                                    rx="2"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 4V2h6v2M8 10h8M8 14h8M8 18h4"
                                />
                            </svg>

                            <!-- Customers icon -->
                            <svg
                                v-else-if="item.icon === 'customers'"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"
                                />
                                <circle cx="10" cy="7" r="4" />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M20 8v6M23 11h-6"
                                />
                            </svg>

                            <!-- Products icon -->
                            <svg
                                v-else-if="item.icon === 'products'"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"
                                />
                            </svg>

                            <!-- Audit icon -->
                            <svg
                                v-else-if="item.icon === 'audit'"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle cx="12" cy="12" r="9" />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 7v5l3 2"
                                />
                            </svg>

                            <!-- Roles, permissions, users -->
                            <svg
                                v-else
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle cx="12" cy="8" r="4" />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 21v-2a8 8 0 0 1 16 0v2"
                                />
                            </svg>
                        </span>

                        <span class="min-w-0 flex-1 truncate">
                            {{ item.label }}
                        </span>

                        <svg
                            class="h-4 w-4 shrink-0 text-slate-500
                                   transition group-hover:translate-x-0.5
                                   group-hover:text-white"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m9 18 6-6-6-6"
                            />
                        </svg>
                    </RouterLink>
                </nav>

                <!-- Panel status -->
                <div
                    class="mt-8 rounded-2xl border border-white/10
                           bg-white/[0.04] p-4"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2 w-2 rounded-full bg-emerald-400"
                        ></span>
                        <p class="text-xs font-semibold text-slate-200">
                            Panel
                        </p>
                    </div>

                    <p class="mt-2 text-xs leading-5 text-slate-400">
                        Manage orders, customers, products, and team activity.
                    </p>
                </div>
            </div>

            <!-- User profile and logout -->
            <div class="shrink-0 border-t border-white/10 p-4">
                <div
                    class="flex items-center gap-3 rounded-xl
                           bg-white/5 p-3"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center
                               justify-center rounded-full bg-blue-600
                               text-sm font-bold uppercase text-white"
                    >
                        {{ user?.name?.charAt(0) || 'U' }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-white">
                            {{ user?.name || 'User' }}
                        </p>
                        <p class="mt-0.5 truncate text-xs text-slate-400">
                            {{ role }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="mt-3 flex w-full items-center justify-center
                           gap-2 rounded-xl border border-red-400/20
                           bg-red-500/10 px-4 py-2.5 text-sm
                           font-medium text-red-300 transition
                           hover:bg-red-500/20 hover:text-red-200"
                    @click="handleLogout"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"
                        />
                    </svg>
                    Sign out
                </button>
            </div>
        </aside>

        <!-- Main Panel -->
        <div class="min-h-screen pl-0 lg:pl-64">
            <!-- Top header -->
            <header
                class="sticky top-0 z-30 flex min-h-20 items-center
                       justify-between gap-3 border-b border-slate-200
                       bg-white/95 px-4 py-3 backdrop-blur sm:px-8"
            >
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <!-- Mobile menu toggle -->
                    <button
                        type="button"
                        class="flex h-10 w-10 shrink-0 items-center
                               justify-center rounded-xl border
                               border-slate-200 bg-white text-slate-700
                               transition hover:bg-slate-100 lg:hidden"
                        aria-label="Toggle navigation"
                        :aria-expanded="sidebarOpen"
                        @click="toggleSidebar"
                    >
                        <svg
                            v-if="!sidebarOpen"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>

                        <svg
                            v-else
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m6 6 12 12M18 6 6 18"
                            />
                        </svg>
                    </button>

                    <div class="min-w-0">
                        <p
                            class="hidden text-xs font-medium uppercase
                                   tracking-wider text-slate-400 sm:block"
                        >
                            Panel
                        </p>

                        <h2
                            class="mt-0.5 truncate text-sm font-bold
                                   text-slate-800 sm:mt-1 sm:text-lg"
                        >
                            Order Management System
                        </h2>
                    </div>
                </div>

                <!-- User information and notifications -->
                <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                    <div class="hidden text-right md:block">
                        <p class="text-sm font-semibold text-slate-700">
                            {{ user?.name }}
                        </p>
                        <p class="mt-0.5 max-w-48 truncate text-xs text-slate-400">
                            {{ user?.email }}
                        </p>
                    </div>

                    <div class="hidden h-8 w-px bg-slate-200 md:block"></div>

                    <div
                        class="flex items-center justify-center rounded-xl
                               border border-slate-200 bg-slate-50 p-1.5"
                    >
                        <NotificationBell />
                    </div>
                </div>
            </header>

            <!-- Routed page content -->
            <main class="p-4 sm:p-6 lg:p-8">
                <div class="mx-auto w-full max-w-[1600px]">
                    <RouterView />
                </div>
            </main>

            <!-- Footer -->
            <footer
                class="mx-4 border-t border-slate-200 py-5 text-center
                       text-xs text-slate-400 sm:mx-8"
            >
                OMS · Order Management System
            </footer>
        </div>
        <AppToast ref="toastRef" />
        <ConfirmModal ref="confirmModalRef" />
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>