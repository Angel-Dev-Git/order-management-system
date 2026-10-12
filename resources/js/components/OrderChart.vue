
<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
} from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps({
    ordersByStatus: {
        type: Object,
        default: () => ({}),
    },
})

const chartData = computed(() => ({
    labels: Object.keys(props.ordersByStatus).map(
        status => status.charAt(0).toUpperCase() + status.slice(1)
    ),
    datasets: [{
        data: Object.values(props.ordersByStatus).map(Number),
    }],
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
}
</script>

<template>
    <div class="h-72">
        <Doughnut
            v-if="chartData.labels.length"
            :data="chartData"
            :options="chartOptions"
        />
        <p v-else class="py-10 text-center text-slate-500">
            No order data available.
        </p>
    </div>
</template>
