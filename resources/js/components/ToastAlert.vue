```vue
<script setup>
import { ref, onBeforeUnmount } from 'vue'

const visible = ref(false)
const message = ref('')
const type = ref('success')

let timer = null

const show = (text, toastType = 'success', duration = 3000) => {
    message.value = text
    type.value = toastType
    visible.value = true

    if (timer) {
        clearTimeout(timer)
    }

    timer = setTimeout(() => {
        visible.value = false
    }, duration)
}

const hide = () => {
    visible.value = false

    if (timer) {
        clearTimeout(timer)
        timer = null
    }
}

defineExpose({ show, hide })

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer)
})
</script>

<template>
    <Transition name="toast">
        <div
            v-if="visible"
            class="fixed right-4 top-20 z-[100] flex w-[calc(100%-2rem)]
                   max-w-sm items-start gap-3 rounded-xl border bg-white
                   p-4 shadow-xl sm:right-6"
            :class="{
                'border-emerald-200': type === 'success',
                'border-red-200': type === 'error',
                'border-amber-200': type === 'warning',
                'border-blue-200': type === 'info',
            }"
            role="status"
            aria-live="polite"
        >
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                :class="{
                    'bg-emerald-100 text-emerald-600': type === 'success',
                    'bg-red-100 text-red-600': type === 'error',
                    'bg-amber-100 text-amber-600': type === 'warning',
                    'bg-blue-100 text-blue-600': type === 'info',
                }"
            >
                <svg
                    v-if="type === 'success'"
                    class="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m5 12 4 4L19 6"
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
                    <circle cx="12" cy="12" r="9" />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 8v4m0 4h.01"
                    />
                </svg>
            </div>

            <div class="min-w-0 flex-1 pt-0.5">
                <p class="text-sm font-semibold text-slate-800">
                    {{
                        type === 'success' ? 'Success'
                        : type === 'error' ? 'Something went wrong'
                        : type === 'warning' ? 'Warning'
                        : 'Information'
                    }}
                </p>
                <p class="mt-1 break-words text-sm text-slate-600">
                    {{ message }}
                </p>
            </div>

            <button
                type="button"
                class="rounded-lg p-1 text-slate-400 hover:bg-slate-100
                       hover:text-slate-700"
                aria-label="Dismiss notification"
                @click="hide"
            >
                <span aria-hidden="true">✕</span>
            </button>
        </div>
    </Transition>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
```