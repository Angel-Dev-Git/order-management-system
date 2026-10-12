
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'
const route = useRoute()
const router = useRouter()
// for permissions 
const authStore = useAuthStore()

const canViewAllOrders = computed(() =>authStore.hasPermission('orders.view_all'))

const canViewAssignedOrders = computed(() =>authStore.hasPermission('orders.view_assigned'))

const canChangeStatus = computed(() =>authStore.hasPermission('orders.change_status'))

const canAssignOrders = computed(() =>
    authStore.hasPermission('orders.assign')
)

const canAddNotes = computed(() =>
    authStore.hasPermission('orders.add_notes')
)
const order = ref(null)
const history = ref([])
const activity = ref([])
const loading = ref(true)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const newStatus = ref('')
const statusNote = ref('')
const newNote = ref('')
const assignedTo = ref('')
const employees = ref([])

const canCancel = computed(() =>
    order.value &&
    !['completed', 'cancelled'].includes(order.value.status)
)

async function fetchOrder() {
    loading.value = true
    errorMessage.value = ''

    try {
        const response = await api.get(`/orders/${route.params.id}`)
        order.value = response.data.data.order ?? response.data.data

        newStatus.value = order.value.status
        assignedTo.value = order.value.assigned_to ?? ''

        await Promise.all([
            fetchHistory(),
            fetchActivity(),
        ])
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            'Unable to load order details.'
    } finally {
        loading.value = false
    }
}

async function fetchHistory() {
    try {
        const response = await api.get(
            `/orders/${route.params.id}/history`
        )

        history.value = response.data.data.history
            ?? response.data.data
            ?? []
    } catch (error) {
        console.error('Unable to load status history:', error)
    }
}

async function fetchActivity() {
    try {
        const response = await api.get(
            `/orders/${route.params.id}/activity`
        )

        activity.value = response.data.data ?? []
    } catch (error) {
        console.error('Unable to load activity:', error)
    }
}

async function updateStatus() {
    if (!canChangeStatus.value) return
    if (!newStatus.value || newStatus.value === order.value.status) {
        return
    }

    saving.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await api.patch(`/orders/${route.params.id}/status`, {
            status: newStatus.value,
            note: statusNote.value,
        })

        successMessage.value = 'Order status updated successfully.'
        statusNote.value = ''

        await fetchOrder()
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            'Unable to update order status.'
        newStatus.value = order.value.status
    } finally {
        saving.value = false
    }
}

async function addNote() {
    if (!canAddNotes.value) return
    if (!newNote.value.trim()) return

    saving.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await api.post(`/orders/${route.params.id}/notes`, {
            note: newNote.value.trim(),
        })

        newNote.value = ''
        successMessage.value = 'Note added successfully.'

        await Promise.all([fetchOrder(), fetchActivity()])
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            'Unable to add note.'
    } finally {
        saving.value = false
    }
}

async function fetchEmployees() {
    try {
        const response = await api.get('/staff') // Assuming role_id 3 is for staff
        employees.value = response.data.data.user
            ?? response.data.data
            ?? []
    } catch (error) {
        console.error('Unable to load employees:', error)
    }
}

async function assignOrder() {
    if (!canAssignOrders.value) return
    if (!assignedTo.value) return

    saving.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await api.patch(`/orders/${route.params.id}/assign`, {
            assigned_to: assignedTo.value,
        })

        successMessage.value = 'Order assigned successfully.'
        await fetchOrder()
        await fetchActivity()
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            'Unable to assign order.'
    } finally {
        saving.value = false
    }
}

async function cancelOrder() {
    if (!canCancel.value) return

    const confirmed = window.confirm(
        'Are you sure you want to cancel this order?'
    )

    if (!confirmed) return

    newStatus.value = 'cancelled'
    await updateStatus()
}

function formatCurrency(value) {
    return Number(value ?? 0).toLocaleString('en-IN', {
        style: 'currency',
        currency: 'INR',
    })
}

onMounted(async () => {
    await Promise.all([fetchOrder(), fetchEmployees()])
})
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <button
                    type="button"
                    @click="router.push('/orders')"
                    class="mb-3 text-sm text-blue-600 hover:underline"
                >
                    ← Back to Orders
                </button>

                <h1 class="text-2xl font-bold text-slate-900">
                    Order Details
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    View order information and manage its workflow.
                </p>
            </div>

            <button
                v-if="canChangeStatus && canCancel"
                type="button"
                @click="cancelOrder"
                :disabled="saving"
                class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
            >
                Cancel Order
            </button>
        </div>

        <div v-if="loading" class="rounded-xl bg-white p-10 text-center">
            Loading order details...
        </div>

        <div
            v-else-if="errorMessage && !order"
            role="alert"
            class="rounded-xl bg-red-50 p-5 text-red-700"
        >
            {{ errorMessage }}
            <button @click="fetchOrder" class="ml-2 underline">
                Retry
            </button>
        </div>

        <template v-else-if="order">
            <div
                v-if="errorMessage"
                role="alert"
                class="rounded-lg bg-red-50 p-3 text-sm text-red-700"
            >
                {{ errorMessage }}
            </div>

            <div
                v-if="successMessage"
                role="status"
                class="rounded-lg bg-green-50 p-3 text-sm text-green-700"
            >
                {{ successMessage }}
            </div>

            <div class="grid gap-5 lg:grid-cols-3">
                <section class="rounded-xl bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <p class="text-sm text-slate-500">Order number</p>
                            <h2 class="mt-1 text-xl font-bold">
                                {{ order.order_number }}
                            </h2>
                        </div>

                        <span class="h-fit rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700">
                            {{ order.status }}
                        </span>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-sm text-slate-500">Order date</p>
                            <p class="mt-1 font-medium">{{ order.order_date }}</p>
                        </div>

                        <div>
                            <p class="text-sm text-slate-500">Priority</p>
                            <p class="mt-1 font-medium">{{ order.priority }}</p>
                        </div>

                        <div>
                            <p class="text-sm text-slate-500">Total amount</p>
                            <p class="mt-1 text-xl font-bold">
                                {{ formatCurrency(order.total) }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl bg-white p-6 shadow-sm">
                    <h2 class="font-semibold">Customer Information</h2>

                    <p class="mt-4 font-medium">
                        {{ order.customer?.name ?? '—' }}
                    </p>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ order.customer?.email ?? 'No email' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ order.customer?.phone ?? 'No phone' }}
                    </p>
                </section>
            </div>

            <section class="overflow-hidden rounded-xl bg-white shadow-sm">
                <div class="border-b p-5">
                    <h2 class="font-semibold">Order Items</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Product</th>
                                <th class="px-5 py-3">SKU</th>
                                <th class="px-5 py-3">Quantity</th>
                                <th class="px-5 py-3">Unit Price</th>
                                <th class="px-5 py-3">Discount</th>
                                <th class="px-5 py-3">Tax</th>
                                <th class="px-5 py-3">Total</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y">
                            <tr
                                v-for="item in order.items ?? []"
                                :key="item.id"
                            >
                                <td class="px-5 py-4">
                                    {{ item.product_name }}
                                </td>
                                <td class="px-5 py-4">{{ item.sku }}</td>
                                <td class="px-5 py-4">{{ item.quantity }}</td>
                                <td class="px-5 py-4">
                                    {{ formatCurrency(item.unit_price) }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ formatCurrency(item.discount) }}
                                </td>
                                <td class="px-5 py-4">
                                    {{ formatCurrency(item.tax) }}
                                </td>
                                <td class="px-5 py-4 font-medium">
                                    {{ formatCurrency(item.total) }}
                                </td>
                            </tr>

                            <tr v-if="!order.items?.length">
                                <td colspan="7" class="p-6 text-center text-slate-500">
                                    No order items found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-2 border-t p-5 text-right text-sm">
                    <p>Subtotal: {{ formatCurrency(order.subtotal) }}</p>
                    <p>Discount: {{ formatCurrency(order.discount) }}</p>
                    <p>Tax: {{ formatCurrency(order.tax) }}</p>
                    <p class="text-lg font-bold">
                        Total: {{ formatCurrency(order.total) }}
                    </p>
                </div>
            </section>

            <div class="grid gap-5 lg:grid-cols-2">
                <section class="rounded-xl bg-white p-6 shadow-sm"  v-if="canChangeStatus">
                    
                    <h2 class="font-semibold">Update Status</h2>

                    <form class="mt-4 space-y-4" @submit.prevent="updateStatus">
                        <div>
                            <label class="mb-1 block text-sm font-medium">
                                New status
                            </label>

                            <select
                                v-model="newStatus"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >
                                <option value="draft">Draft</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="processing">Processing</option>
                                <option value="ready">Ready</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">
                                Remarks (optional)
                            </label>

                            <textarea
                                v-model="statusNote"
                                rows="2"
                                maxlength="1000"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                placeholder="Add a reason for this status change"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="saving || newStatus === order.status"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            Save Status
                        </button>
                    </form>
                </section>

                <section  v-if="canAssignOrders" class="rounded-xl bg-white p-6 shadow-sm">
                    <h2 class="font-semibold">Assign Order</h2>

                    <form class="mt-4 space-y-4" @submit.prevent="assignOrder">
                        <select
                            v-model="assignedTo"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                            <option value="">Select employee</option>
                            <option
                                v-for="employee in employees"
                                :key="employee.id"
                                :value="employee.id"
                            >
                                {{ employee.name }}
                            </option>
                        </select>

                        <button
                            type="submit"
                            :disabled="saving || !assignedTo"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            Assign Employee
                        </button>
                    </form>
                </section>
            </div>

            <section v-if="canAddNotes" class="rounded-xl bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Add Note</h2>

                <form class="mt-4 space-y-3" @submit.prevent="addNote">
                    <textarea
                        v-model="newNote"
                        rows="3"
                        maxlength="1000"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        placeholder="Write an internal order note..."
                    />

                    <button
                        type="submit"
                        :disabled="saving || !newNote.trim()"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        Add Note
                    </button>
                </form>
            </section>

            <section class="rounded-xl bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Status History</h2>

                <div v-if="history.length" class="mt-5 space-y-4">
                    <div
                        v-for="entry in history"
                        :key="entry.id"
                        class="border-l-2 border-blue-200 pl-4"
                    >
                        <p class="font-medium">
                            {{ entry.old_status ?? 'Created' }}
                            →
                            {{ entry.new_status ?? entry.status }}
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            {{ entry.user?.name ?? 'System' }}
                            · {{ entry.created_at }}
                        </p>

                        <p v-if="entry.note" class="mt-1 text-sm">
                            {{ entry.note }}
                        </p>
                    </div>
                </div>

                <p v-else class="mt-4 text-sm text-slate-500">
                    No status history available.
                </p>
            </section>

            <section class="rounded-xl bg-white p-6 shadow-sm">
                <h2 class="font-semibold">Activity Timeline</h2>

                <div v-if="activity.length" class="mt-5 space-y-4">
                    <div
                        v-for="(entry, index) in activity"
                        :key="entry.id ?? `${entry.type}-${index}`"
                        class="border-l-2 border-slate-200 pl-4"
                    >
                        <p class="font-medium">
                            {{ entry.action ?? entry.type }}
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            {{ entry.user ?? 'System' }}
                            · {{ entry.created_at }}
                        </p>

                        <p v-if="entry.details" class="mt-1 text-sm text-slate-600">
                            {{ typeof entry.details === 'string'
                                ? entry.details
                                : JSON.stringify(entry.details) }}
                        </p>
                    </div>
                </div>

                <p v-else class="mt-4 text-sm text-slate-500">
                    No activity recorded yet.
                </p>
            </section>
        </template>
    </div>
</template>
