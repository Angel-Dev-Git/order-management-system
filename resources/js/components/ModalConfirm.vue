<script setup>
import { onBeforeUnmount, ref } from 'vue'

const visible = ref(false)
const title = ref('Are you sure?')
const message = ref('Do you want to continue?')
const confirmText = ref('Confirm')
const cancelText = ref('Cancel')
const variant = ref('danger')
const loading = ref(false)

let resolvePromise = null

const open = (options = {}) => {
    title.value = options.title ?? 'Are you sure?'
    message.value = options.message ?? 'Do you want to continue?'
    confirmText.value = options.confirmText ?? 'Confirm'
    cancelText.value = options.cancelText ?? 'Cancel'
    variant.value = options.variant ?? 'danger'
    loading.value = false
    visible.value = true

    return new Promise((resolve) => {
        resolvePromise = resolve
    })
}

const close = (result) => {
    if (loading.value) return

    visible.value = false

    if (resolvePromise) {
        resolvePromise(result)
        resolvePromise = null
    }
}

const confirm = () => {
    close(true)
}

const cancel = () => {
    close(false)
}

const handleKeydown = (event) => {
    if (event.key === 'Escape' && visible.value && !loading.value) {
        cancel()
    }
}

const setLoading = (value) => {
    loading.value = value
}

defineExpose({ open, close, setLoading })

onBeforeUnmount(() => {
    if (resolvePromise) {
        resolvePromise(false)
        resolvePromise = null
    }
})
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="visible"
                class="fixed inset-0 z-[90] flex items-center
                       justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
                @click.self="cancel"
                @keydown.esc="handleKeydown"
            >
                <section
                    class="w-full max-w-md overflow-hidden rounded-2xl
                           border border-slate-200 bg-white shadow-2xl"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="confirm-modal-title"
                    aria-describedby="confirm-modal-message"
                >
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center
                                       justify-center rounded-xl"
                                :class="
                                    variant === 'danger'
                                        ? 'bg-red-100 text-red-600'
                                        : 'bg-blue-100 text-blue-600'
                                "
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
                                        d="M12 9v4m0 4h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3l-7.5-13.1a2 2 0 0 0-3.4 0Z"
                                    />
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <h2
                                    id="confirm-modal-title"
                                    class="text-lg font-bold text-slate-900"
                                >
                                    {{ title }}
                                </h2>

                                <p
                                    id="confirm-modal-message"
                                    class="mt-2 whitespace-pre-line text-sm
                                           leading-6 text-slate-600"
                                >
                                    {{ message }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col-reverse gap-3 border-t
                               border-slate-100 bg-slate-50 px-6 py-4
                               sm:flex-row sm:justify-end"
                    >
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200
                                   bg-white px-4 py-2.5 text-sm font-semibold
                                   text-slate-700 transition
                                   hover:bg-slate-100 disabled:opacity-50"
                            :disabled="loading"
                            @click="cancel"
                        >
                            {{ cancelText }}
                        </button>

                        <button
                            type="button"
                            class="rounded-xl px-4 py-2.5 text-sm font-semibold
                                   text-white transition disabled:opacity-50"
                            :class="
                                variant === 'danger'
                                    ? 'bg-red-600 hover:bg-red-700'
                                    : 'bg-blue-600 hover:bg-blue-700'
                            "
                            :disabled="loading"
                            @click="confirm"
                        >
                            {{ loading ? 'Please wait...' : confirmText }}
                        </button>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-active section,
.modal-leave-active section {
    transition: transform 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from section,
.modal-leave-to section {
    transform: translateY(8px) scale(0.98);
}
</style>
