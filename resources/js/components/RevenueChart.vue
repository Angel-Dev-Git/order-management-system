
<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Legend,
} from 'chart.js'

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Legend
)

const props = defineProps({
    revenueByMonth: {
        type: Object,
        default: () => ({}),
    },
})

const chartData = computed(() => ({
    labels: Object.keys(props.revenueByMonth),
    datasets: [{
        label: 'Revenue (₹)',
        data: Object.values(props.revenueByMonth).map(Number),
        tension: 0.3,
    }],
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
}
</script>

<template>
    <div class="h-72">
        <Line
            v-if="chartData.labels.length"
            :data="chartData"
            :options="chartOptions"
        />
        <p v-else class="py-10 text-center text-slate-500">
            No revenue data available.
        </p>
    </div>
</template>
