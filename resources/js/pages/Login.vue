
<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-100 px-4">
        <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-lg">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold text-slate-800">
                    Order Management
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    Sign in to access your panel
                </p>
            </div>

            <form class="space-y-5" @submit.prevent="handleLogin">
                <div>
                    <label
                        for="email"
                        class="mb-1 block text-sm font-medium text-slate-700"
                    >
                        Email address
                    </label>

                    <input
                        id="email"
                        v-model.trim="email"
                        type="email"
                        autocomplete="username"
                        required
                        class="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        placeholder="Enter your email"
                    />
                </div>

                <div>
                    <label
                        for="password"
                        class="mb-1 block text-sm font-medium text-slate-700"
                    >
                        Password
                    </label>
                     <div class="relative">
                    <input
                        id="password"
                        v-model="password"
                         :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        placeholder="Enter your password"
                    />
                     <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-sm text-gray-500 hover:text-indigo-600"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            >
                                <svg
                                    v-if="showPassword"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M3 3l18 18" />
                                    <path d="M10.6 10.6a2 2 0 002.8 2.8" />
                                    <path d="M9.9 5.2A10.8 10.8 0 0112 5c5 0 9 5 9 7a9.7 9.7 0 01-2.1 3.1" />
                                    <path d="M6.2 6.2C3.7 7.8 2 10.2 2 12c0 2 4 7 10 7 1 0 2-.2 2.9-.5" />
                                </svg>

                                <svg
                                    v-else
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                     </div>
                </div>

                <div
                    v-if="errorMessage"
                    role="alert"
                    class="rounded-lg bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ errorMessage }}
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ loading ? 'Signing in...' : 'Sign In' }}
                </button>
            </form>
        </div>
    </div>
</template>
<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const errorMessage = ref('')
const showPassword = ref(false)

async function handleLogin() {
    errorMessage.value = ''

    if (!email.value || !password.value) {
        errorMessage.value = 'Please enter your email and password.'
        return
    }

    loading.value = true

    try {
        await authStore.login({
            email: email.value,
            password: password.value,
        })

        const role = authStore.user?.role?.name

        if (role === 'Staff') {
            await router.push('/orders')
        } else {
            await router.push('/dashboard')
        }
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ||
            error.message ||
            'Login failed. Please check your credentials.'
    } finally {
        loading.value = false
    }
}
</script>

