
<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'

const router = useRouter()

const notifications = ref([])
const unreadCount = ref(0)
const loading = ref(false)
const errorMessage = ref('')
const isOpen = ref(false)
const page = ref(1)
const lastPage = ref(1)

async function fetchNotifications() {
    loading.value = true
    errorMessage.value = ''

    try {
        const response = await api.get('/notifications', {
            params: { page: page.value, per_page: 10 },
        })

        const result = response.data.data

        notifications.value = result.data ?? []
        page.value = result.current_page ?? 1
        lastPage.value = result.last_page ?? 1
        unreadCount.value = response.data.unread_count ?? 0
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            'Unable to load notifications.'
    } finally {
        loading.value = false
    }
}

async function fetchUnreadCount() {
    try {
        const response = await api.get('/notifications/unread-count')
        unreadCount.value = response.data.unread_count ?? 0
    } catch {
        // The dropdown can still be used if the count request fails.
    }
}

async function markAsRead(notification) {
    if (!notification.read_at) {
        try {
            await api.patch(`/notifications/${notification.id}/read`)
            notification.read_at = new Date().toISOString()
            unreadCount.value = Math.max(0, unreadCount.value - 1)
        } catch (error) {
            errorMessage.value =
                error.response?.data?.message ??
                'Unable to mark notification as read.'
            return
        }
    }

    if (notification.data?.url) {
        isOpen.value = false
        router.push(notification.data.url)
    }
}

async function markAllAsRead() {
    try {
        await api.patch('/notifications/read-all')

        notifications.value.forEach(notification => {
            if (!notification.read_at) {
                notification.read_at = new Date().toISOString()
            }
        })

        unreadCount.value = 0
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            'Unable to mark all notifications as read.'
    }
}

async function changePage(nextPage) {
    if (nextPage < 1 || nextPage > lastPage.value) return

    page.value = nextPage
    await fetchNotifications()
}

function toggleDropdown() {
    isOpen.value = !isOpen.value

    if (isOpen.value) {
        page.value = 1
        fetchNotifications()
    }
}

function formatDate(value) {
    if (!value) return ''

    return new Date(value).toLocaleString('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    })
}

function handleOutsideClick(event) {
    if (!event.target.closest('[data-notification-root]')) {
        isOpen.value = false
    }
}

onMounted(() => {
    fetchUnreadCount()
    document.addEventListener('click', handleOutsideClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick)
})
</script>

<template>
    <div class="relative" data-notification-root>
        <button
            type="button"
            @click="toggleDropdown"
            aria-label="Notifications"
            :aria-expanded="isOpen"
            class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                class="h-6 w-6"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"
                />
            </svg>

            <span
                v-if="unreadCount > 0"
                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <div
            v-if="isOpen"
            class="absolute right-0 z-50 mt-3 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl sm:w-96"
        >
            <div class="flex items-center justify-between border-b border-slate-100 p-4">
                <div>
                    <h2 class="font-semibold text-slate-900">
                        Notifications
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{ unreadCount }} unread
                    </p>
                </div>

                <button
                    v-if="unreadCount > 0"
                    type="button"
                    @click="markAllAsRead"
                    class="text-xs font-medium text-blue-600 hover:text-blue-800"
                >
                    Mark all as read
                </button>
            </div>

            <div
                v-if="errorMessage"
                role="alert"
                class="p-3 text-sm text-red-600"
            >
                {{ errorMessage }}
            </div>

            <div
                v-if="loading"
                class="p-6 text-center text-sm text-slate-500"
            >
                Loading notifications...
            </div>

            <div
                v-else-if="notifications.length === 0"
                class="p-8 text-center text-sm text-slate-500"
            >
                You're all caught up!
            </div>

            <div v-else class="max-h-96 overflow-y-auto">
                <button
                    v-for="notification in notifications"
                    :key="notification.id"
                    type="button"
                    @click="markAsRead(notification)"
                    class="block w-full border-b border-slate-100 p-4 text-left transition hover:bg-slate-50"
                    :class="{ 'bg-blue-50/50': !notification.read_at }"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                            :class="notification.read_at ? 'bg-slate-300' : 'bg-blue-600'"
                        ></span>

                        <div class="min-w-0 flex-1">
                            <p class="break-words text-sm text-slate-800">
                                {{ notification.data?.message ?? 'New notification' }}
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                {{ formatDate(notification.created_at) }}
                            </p>
                        </div>
                    </div>
                </button>
            </div>

            <div
                v-if="!loading && lastPage > 1"
                class="flex items-center justify-between border-t border-slate-100 p-3"
            >
                <button
                    type="button"
                    :disabled="page <= 1"
                    @click="changePage(page - 1)"
                    class="text-sm text-blue-600 disabled:text-slate-300"
                >
                    Previous
                </button>

                <span class="text-xs text-slate-500">
                    Page {{ page }} of {{ lastPage }}
                </span>

                <button
                    type="button"
                    :disabled="page >= lastPage"
                    @click="changePage(page + 1)"
                    class="text-sm text-blue-600 disabled:text-slate-300"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
