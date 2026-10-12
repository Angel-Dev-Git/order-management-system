
<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'
const authStore = useAuthStore()

const canManageUsers = computed(() =>authStore.hasPermission('users.manage'))
const users = ref([])
const roles = ref([])
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const showPassword = ref(false)
const search = ref('')
const roleFilter = ref('')

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 10,
})

const showModal = ref(false)
const editingUser = ref(null)
const validationErrors = ref({})

const emptyForm = () => ({
    name: '',
    email: '',
    password: '',
    role_id: '',
    // status: 'active',
})

const form = reactive(emptyForm())

function clearMessages() {
    errorMessage.value = ''
    successMessage.value = ''
    validationErrors.value = {}
}

function getErrorMessage(error) {
    return error.response?.data?.message
        || error.message
        || 'Something went wrong.'
}

async function fetchUsers(page = 1) {
    if(!canManageUsers.value) return
    loading.value = true
    errorMessage.value = ''

    try {
        const response = await api.get('/admin/users', {
            params: {
                page,
                search: search.value || undefined,
                role_id: roleFilter.value || undefined,
                // status: statusFilter.value || undefined,
            },
        })

        const result = response.data.data

        // Laravel paginate() returns a paginator with a data array.
        users.value = Array.isArray(result) ? result : (result?.data ?? [])

        if (result && !Array.isArray(result)) {
            pagination.current_page = result.current_page ?? 1
            pagination.last_page = result.last_page ?? 1
            pagination.total = result.total ?? users.value.length
            pagination.per_page = result.per_page ?? 10
        }
    } catch (error) {
        errorMessage.value = getErrorMessage(error)
    } finally {
        loading.value = false
    }
}

async function fetchRoles() {
    if(!canManageUsers.value) return
    try {
        const response = await api.get('/admin/users/roles')
        roles.value = response.data.data ?? []
    } catch (error) {
        errorMessage.value = getErrorMessage(error)
    }
}

function applyFilters() {
    fetchUsers(1)
}

function resetFilters() {
    search.value = ''
    roleFilter.value = ''
    // statusFilter.value = ''
    fetchUsers(1)
}

function openCreateModal() {
    if(!canManageUsers.value) return
    clearMessages()
    editingUser.value = null
    Object.assign(form, emptyForm())
    showModal.value = true
    showPassword.value = false
}

function openEditModal(user) {
    if (!canManageUsers.value) return
    clearMessages()
    editingUser.value = user

    Object.assign(form, {
        name: user.name ?? '',
        email: user.email ?? '',
        password: '',
        role_id: String(user.role_id ?? user.role?.id ?? ''),
        // status: user.status ?? 'active',
    })
    showPassword.value = false
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    editingUser.value = null
    validationErrors.value = {}
}

async function saveUser() {
    if (!canManageUsers.value) return
    saving.value = true
    clearMessages()

    const payload = {
        name: form.name.trim(),
        email: form.email.trim(),
        role_id: Number(form.role_id),
         // status: form.status,
    }

    if (form.password) {
        payload.password = form.password
    }

    try {
        if (editingUser.value) {
            await api.put(
                `/admin/users/${editingUser.value.id}`,
                payload
            )
            successMessage.value = 'User updated successfully.'
        } else {
            payload.password = form.password

            await api.post('/admin/users', payload)
            successMessage.value = 'User created successfully.'
        }

        closeModal()
        await fetchUsers(pagination.current_page)
    } catch (error) {
        if (error.response?.status === 422) {
            validationErrors.value = error.response.data.errors ?? {}
            errorMessage.value = error.response.data.message
                ?? 'Please correct the highlighted fields.'
        } else {
            errorMessage.value = getErrorMessage(error)
        }
    } finally {
        saving.value = false
    }
}

// async function toggleStatus(user) {
//     clearMessages()

//     const newStatus = user.status === 'active' ? 'inactive' : 'active'

//     try {
//         await api.put(`/admin/users/${user.id}`, {
//             status: newStatus,
//         })

//         successMessage.value = `User ${newStatus === 'active' ? 'activated' : 'deactivated'} successfully.`
//         await fetchUsers(pagination.current_page)
//     } catch (error) {
//         errorMessage.value = getErrorMessage(error)
//     }
// }

function fieldError(field) {
    return validationErrors.value[field]?.[0] ?? ''
}

onMounted(async () => {
    if(!canManageUsers.value) return
    await Promise.all([fetchRoles(), fetchUsers()])
})
</script>

<template>
    <div v-if="canManageUsers" class="space-y-6 p-4 sm:p-6">
        <!-- Page heading -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    User Management
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    Manage users, roles and account status.
                </p>
            </div>

            <button
                type="button"
                v-if="canManageUsers"
                @click="openCreateModal"
                class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
            >
                + Add User
            </button>
        </div>

        <!-- Feedback messages -->
        <div
            v-if="successMessage"
            class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
            role="status"
        >
            {{ successMessage }}
        </div>

        <div
            v-if="errorMessage"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            role="alert"
        >
            {{ errorMessage }}
        </div>

        <!-- Filters -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <form
                class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search name or email..."
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                />

                <select
                    v-model="roleFilter"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-indigo-500"
                >
                    <option value="">All roles</option>
                    <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="String(role.id)"
                    >
                        {{ role.name }}
                    </option>
                </select>

                <!-- <select
                    v-model="statusFilter"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-indigo-500"
                >
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select> -->

                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="flex-1 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                    >
                        Search
                    </button>

                    <button
                        type="button"
                        @click="resetFilters"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Users table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-4 sm:px-6">
                <h2 class="font-semibold text-gray-900">Users</h2>
                <span class="text-sm text-gray-500">
                    {{ pagination.total }} total
                </span>
            </div>

            <div v-if="loading" class="p-10 text-center text-sm text-gray-500">
                Loading users...
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                User
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Role
                            </th>
                            <!-- <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Status
                            </th> -->
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="font-medium text-gray-900">
                                    {{ user.name }}
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    {{ user.email }}
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                                    {{ user.role?.name ?? 'Unassigned' }}
                                </span>
                            </td>

                            <!-- <td class="whitespace-nowrap px-5 py-4">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="user.status === 'active'
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ user.status }}
                                </span>
                            </td> -->

                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <button
                                    type="button"
                                    v-if="canManageUsers"
                                    @click="openEditModal(user)"
                                    class="mr-3 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    Edit
                                </button>

                                <!-- <button
                                    type="button"
                                    @click="toggleStatus(user)"
                                    class="text-sm font-medium"
                                    :class="user.status === 'active'
                                        ? 'text-red-600 hover:text-red-800'
                                        : 'text-green-600 hover:text-green-800'"
                                >
                                    {{ user.status === 'active' ? 'Deactivate' : 'Activate' }}
                                </button> -->
                            </td>
                        </tr>

                        <tr v-if="users.length === 0">
                            <td
                                colspan="4"
                                class="px-5 py-12 text-center text-sm text-gray-500"
                            >
                                No users found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="!loading && pagination.last_page > 1"
                class="flex flex-col gap-3 border-t border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-gray-500">
                    Page {{ pagination.current_page }} of {{ pagination.last_page }}
                </p>

                <div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="pagination.current_page <= 1"
                        @click="fetchUsers(pagination.current_page - 1)"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Previous
                    </button>

                    <button
                        type="button"
                        :disabled="pagination.current_page >= pagination.last_page"
                        @click="fetchUsers(pagination.current_page + 1)"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- Create / Edit modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4"
            @click.self="closeModal"
        >
            <div class="my-8 w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ editingUser ? 'Edit User' : 'Add User' }}
                    </h2>

                    <button
                        type="button"
                        @click="closeModal"
                        class="text-2xl leading-none text-gray-400 hover:text-gray-700"
                        aria-label="Close modal"
                    >
                        &times;
                    </button>
                </div>

                <form class="space-y-4 p-6" @submit.prevent="saveUser">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Full Name
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="255"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-indigo-500"
                        />
                        <p v-if="fieldError('name')" class="mt-1 text-sm text-red-600">
                            {{ fieldError('name') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Email Address
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-indigo-500"
                        />
                        <p v-if="fieldError('email')" class="mt-1 text-sm text-red-600">
                            {{ fieldError('email') }}
                        </p>
                    </div>

                    
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            {{ editingUser ? 'New Password (optional)' : 'Password' }}
                        </label>

                        <div class="relative">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                :required="!editingUser"
                                autocomplete="new-password"
                                placeholder="Enter password"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-12 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
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

                        <p
                            v-if="fieldError('password')"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ fieldError('password') }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Role
                        </label>
                        <select
                            v-model="form.role_id"
                            required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-indigo-500"
                        >
                            <option value="" disabled>Select role</option>
                            <option
                                v-for="role in roles"
                                :key="role.id"
                                :value="String(role.id)"
                            >
                                {{ role.name }}
                            </option>
                        </select>
                        <p v-if="fieldError('role_id')" class="mt-1 text-sm text-red-600">
                            {{ fieldError('role_id') }}
                        </p>
                    </div>

                    <!-- <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Account Status
                        </label>
                        <select
                            v-model="form.status"
                            required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:border-indigo-500"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <p v-if="fieldError('status')" class="mt-1 text-sm text-red-600">
                            {{ fieldError('status') }}
                        </p>
                    </div> -->

                    <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="saving"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ saving ? 'Saving...' : (editingUser ? 'Update User' : 'Create User') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
