
<script setup>
import { onMounted, ref } from 'vue'
import api from '../services/api'
import OrderChart from '../components/OrderChart.vue'
import RevenueChart from '../components/RevenueChart.vue'
const dashboard = ref(null)
const loading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
    try {
        const response = await api.get('/dashboard')
        dashboard.value = response.data.data
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            'Unable to load dashboard data.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">
                Overview of your order management system
            </p>
        </div>

        <div v-if="loading" class="py-10 text-center text-slate-500">
            Loading dashboard...
        </div>

        <div
            v-else-if="errorMessage"
            role="alert"
            class="rounded-lg bg-red-50 p-4 text-red-700"
        >
            {{ errorMessage }}
        </div>

        <div v-else-if="dashboard" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-500">Total Orders</p>
                <p class="mt-2 text-3xl font-bold">
                    {{ dashboard.total_orders ?? 0 }}
                </p>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-500">Active Orders</p>
                <p class="mt-2 text-3xl font-bold text-blue-600">
                    {{ dashboard.active_orders ?? 0 }}
                </p>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-500">Completed Orders</p>
                <p class="mt-2 text-3xl font-bold text-green-600">
                    {{ dashboard.completed_orders ?? 0 }}
                </p>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-500">Cancelled Orders</p>
                <p class="mt-2 text-3xl font-bold text-red-600">
                    {{ dashboard.cancelled_orders ?? 0 }}
                </p>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm sm:col-span-2">
                <p class="text-sm text-slate-500">Total Revenue</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">
                    ₹{{ Number(dashboard.total_revenue ?? 0).toLocaleString('en-IN') }}
                </p>
            </div>
        </div>


        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-semibold text-slate-900">
                    Orders by Status
                </h2>

                <OrderChart
                    :orders-by-status="dashboard?.orders_by_status ?? {}"
                />

               
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <h2 class="mb-5 text-lg font-semibold text-slate-900">
                    Revenue — Last 6 Months
                </h2>

                 <RevenueChart
                    :revenue-by-month="dashboard?.revenue_last_6_months"
                />
            </div>
        </div>
    </div>
</template>
