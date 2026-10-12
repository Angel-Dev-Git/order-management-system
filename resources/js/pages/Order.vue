
<script setup>
import { ref, onMounted,watch,computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const canViewAllOrders = computed(() => authStore.hasPermission('orders.view_all'))

const canViewAssignedOrders = computed(() =>authStore.hasPermission('orders.view_assigned'))

const canCreateOrder = computed(() =>authStore.hasPermission('orders.create'))
const orders = ref([])
const loading = ref(false)
const errorMessage = ref('')

const search = ref('')
const status = ref('')
const priority = ref('')
const sortBy = ref('order_date')
const sortDirection = ref('desc')

const currentPage = ref(1)
const lastPage = ref(1)
const totalOrders = ref(0)

let searchTimer = null

async function fetchOrders() {
    if(!canViewAllOrders.value && !canViewAssignedOrders.value) {
        orders.value = []
        totalOrders.value = 0
        errorMessage.value = 'You do not have permission to view orders.'
        return
    }
    loading.value = true
    errorMessage.value = ''

    try {
        const response = await api.get('/orders', {
            params: {
                search: search.value || undefined,
                status: status.value || undefined,
                priority: priority.value || undefined,
                sort_by: sortBy.value,
                sort_direction: sortDirection.value,
                page: currentPage.value,
            },
        })

        // Laravel paginated response is usually nested under data.data.
        const result = response.data.data

        orders.value = result.data ?? []
        currentPage.value = result.current_page ?? 1
        lastPage.value = result.last_page ?? 1
        totalOrders.value = result.total ?? orders.value.length
    } catch (error) {
        console.error('Orders API error:', error.response?.data ?? error)

        errorMessage.value =
            error.response?.data?.message ??
            'Unable to load orders. Please try again.'
    } finally {
        loading.value = false
    }
}

function applyFilters() {
    currentPage.value = 1
    fetchOrders()
}

function changePage(page) {
    if (page < 1 || page > lastPage.value) return

    currentPage.value = page
    fetchOrders()
}

function changeSorting(field) {
    if (sortBy.value === field) {
        sortDirection.value =
            sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortBy.value = field
        sortDirection.value = 'asc'
    }

    currentPage.value = 1
    fetchOrders()
}

function viewOrder(order) {
    if(!canViewAllOrders.value && !canViewAssignedOrders.value) return
    router.push(`/orders/${order.id}`)
}

watch(search, () => {
    clearTimeout(searchTimer)

    searchTimer = setTimeout(() => {
        applyFilters()
    }, 400)
})

onMounted(fetchOrders)
</script>

<template>
    <div v-if="!canViewAllOrders && !canViewAssignedOrders" class="rounded-xl bg-white p-6 text-sm text-red-600">
        You do not have permission to view orders.
    </div>
    <div v-if="canViewAllOrders || canViewAssignedOrders"  class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Orders</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Search, filter, and manage customer orders.
                </p>
            </div>

            <button
                type="button"
                v-if="canCreateOrder"
                @click="router.push('/orders/create')"
                class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
                + Create Order
            </button>
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search order, customer, product..."
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
                />

                <select
                    v-model="status"
                    @change="applyFilters"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="ready">Ready</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>

                <select
                    v-model="priority"
                    @change="applyFilters"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">All priorities</option>
                    <option value="low">Low</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>

                <select
                    v-model="sortBy"
                    @change="applyFilters"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="order_date">Order date</option>
                    <option value="total">Total amount</option>
                    <option value="status">Status</option>
                    <option value="priority">Priority</option>
                    <option value="customer">Customer</option>
                </select>
            </div>
        </div>

        <div
            v-if="errorMessage"
            role="alert"
            class="rounded-lg bg-red-50 p-4 text-sm text-red-700"
        >
            {{ errorMessage }}
            <button
                type="button"
                @click="fetchOrders"
                class="ml-2 font-semibold underline"
            >
                Retry
            </button>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <div v-if="loading" class="p-10 text-center text-slate-500">
                Loading orders...
            </div>

            <div v-else-if="!errorMessage && orders.length === 0"
                 class="p-10 text-center text-slate-500">
                No orders found.
            </div>

            <div v-else-if="!errorMessage" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Order number</th>
                            <th class="px-5 py-4">Customer</th>
                            <th class="px-5 py-4">Date</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Priority</th>
                            <th class="px-5 py-4">Total</th>
                            <th class="px-5 py-4">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="order in orders"
                            :key="order.id"
                            class="hover:bg-slate-50"
                        >
                            <td class="whitespace-nowrap px-5 py-4 font-medium text-blue-700">
                                {{ order.order_number }}
                            </td>

                            <td class="px-5 py-4">
                                {{ order.customer?.name ?? '—' }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4">
                                {{ order.order_date ?? '—' }}
                            </td>

                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium">
                                    {{ order.status }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                {{ order.priority }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 font-medium">
                                ₹{{ Number(order.total ?? 0).toLocaleString('en-IN') }}
                            </td>

                            <td class="px-5 py-4">
                                <button
                                    type="button"
                                    @click="viewOrder(order)"
                                    class="font-medium text-blue-600 hover:underline"
                                >
                                    View
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                <p class="text-sm text-slate-500">
                    Total orders: {{ totalOrders }}
                </p>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="changePage(currentPage - 1)"
                        :disabled="currentPage <= 1 || loading"
                        class="rounded-lg border px-3 py-2 text-sm disabled:opacity-40"
                    >
                        Previous
                    </button>

                    <span class="text-sm text-slate-600">
                        Page {{ currentPage }} of {{ lastPage }}
                    </span>

                    <button
                        type="button"
                        @click="changePage(currentPage + 1)"
                        :disabled="currentPage >= lastPage || loading"
                        class="rounded-lg border px-3 py-2 text-sm disabled:opacity-40"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
