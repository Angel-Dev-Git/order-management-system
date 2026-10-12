
<script setup>
import { onMounted, ref, watch } from 'vue'
import api from '../services/api'

const logs = ref([])
const loading = ref(false)
const errorMessage = ref('')
const search = ref('')
const action = ref('')
const startDate = ref('')
const endDate = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const expandedLog = ref(null)

let searchTimer

async function fetchLogs() {
    loading.value = true
    errorMessage.value = ''
    try {
        const response = await api.get('/admin/audit-logs', {
            params: {
                search: search.value || undefined,
                action: action.value || undefined,
                start_date: startDate.value || undefined,
                end_date: endDate.value || undefined,
                page: page.value,
                per_page: 10,
            },
        })
        const result = response.data.data
        logs.value = result.data ?? []
        page.value = result.current_page ?? 1
        lastPage.value = result.last_page ?? 1
        total.value = result.total ?? 0
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            'Unable to load audit logs.'
    } finally {
        loading.value = false
    }
}

function applyFilters() {
    page.value = 1
    fetchLogs()
}

function clearFilters() {
    search.value = ''
    action.value = ''
    startDate.value = ''
    endDate.value = ''
    page.value = 1
    fetchLogs()
}

function changePage(nextPage) {
    if (nextPage < 1 || nextPage > lastPage.value) return
    page.value = nextPage
    fetchLogs()
}

function formatDate(value) {
    if (!value) return '—'

    return new Date(value).toLocaleString('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    })
}

function formatModel(value) {
    if (!value) return '—'
    return value.split('\\').pop()
}

function formatValues(value) {
    if (value === null || value === undefined) return 'No data'
    return JSON.stringify(value, null, 2)
}

watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        page.value = 1
        fetchLogs()
    }, 350)
})

onMounted(fetchLogs)
</script>

<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">
                Audit Logs
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Track system activity and record changes.
            </p>
        </div>

        <div class="mb-5 grid gap-3 rounded-xl bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-4">
            <input
                v-model="search"
                type="search"
                placeholder="Search action, model, or ID..."
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />

            <select
                v-model="action"
                @change="applyFilters"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
                <option value="">All actions</option>
                <option value="create">Create</option>
                <option value="update">Update</option>
                <option value="delete">Delete</option>
                <option value="assign">Assign</option>
                <option value="update_status">Update status</option>
            </select>

            <input
                v-model="startDate"
                type="date"
                @change="applyFilters"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />

            <input
                v-model="endDate"
                type="date"
                @change="applyFilters"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />

            <button
                type="button"
                @click="clearFilters"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50 md:col-span-2 xl:col-span-4 xl:justify-self-end"
            >
                Clear filters
            </button>
        </div>

        <div
            v-if="errorMessage"
            role="alert"
            class="mb-4 rounded-lg bg-red-50 p-4 text-red-700"
        >
            {{ errorMessage }}
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <div v-if="loading" class="p-8 text-center text-slate-500">
                Loading audit logs...
            </div>

            <table v-else class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-5 py-3 font-semibold">User</th>
                        <th class="px-5 py-3 font-semibold">Action</th>
                        <th class="px-5 py-3 font-semibold">Record</th>
                        <th class="px-5 py-3 font-semibold">Date</th>
                        <th class="px-5 py-3 font-semibold">Details</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <template v-for="log in logs" :key="log.id">
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-medium text-slate-900">
                                    {{ log.user?.name ?? 'Deleted user / System' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ log.user?.email ?? '—' }}
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium">
                                    {{ log.action }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <div class="font-medium">
                                    {{ formatModel(log.model_type) }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    ID: {{ log.model_id }}
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">
                                {{ formatDate(log.created_at) }}
                            </td>

                            <td class="px-5 py-4">
                                <button
                                    type="button"
                                    @click="expandedLog = expandedLog === log.id ? null : log.id"
                                    class="font-medium text-blue-600 hover:text-blue-800"
                                >
                                    {{ expandedLog === log.id ? 'Hide Details' : 'View changes' }}
                                </button>
                            </td>
                        </tr>

                        <tr v-if="expandedLog === log.id">
                            <td colspan="5" class="bg-slate-50 px-5 py-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <h3 class="mb-2 font-semibold text-slate-700">
                                            Previous values
                                        </h3>
                                        <pre class="overflow-x-auto whitespace-pre-wrap break-words rounded-lg bg-white p-3 text-xs">{{ formatValues(log.old_values) }}</pre>
                                    </div>

                                    <div>
                                        <h3 class="mb-2 font-semibold text-slate-700">
                                            New values
                                        </h3>
                                        <pre class="overflow-x-auto whitespace-pre-wrap break-words rounded-lg bg-white p-3 text-xs">{{ formatValues(log.new_values) }}</pre>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr v-if="!logs.length">
                        <td colspan="5" class="px-5 py-10 text-center text-slate-500">
                            {{ loading ? 'Loading...' : 'No audit logs found.' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
                Total records: {{ total }}
            </p>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    :disabled="page <= 1 || loading"
                    @click="changePage(page - 1)"
                    class="rounded-lg border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Previous
                </button>

                <span class="text-sm text-slate-600">
                    Page {{ page }} of {{ lastPage }}
                </span>

                <button
                    type="button"
                    :disabled="page >= lastPage || loading"
                    @click="changePage(page + 1)"
                    class="rounded-lg border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
