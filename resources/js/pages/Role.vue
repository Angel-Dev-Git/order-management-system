
<script setup>
import { ref, onMounted,computed } from 'vue'
import api from '../services/api'
import {useAuthStore} from '../stores/auth'
const authStore = useAuthStore()
const canManageRole = computed(()=>authStore.hasPermission('roles.manage'));
const roles = ref([])
const search = ref('')
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')

const currentPage = ref(1)
const lastPage = ref(1)

const showModal = ref(false)
const showDeleteModal = ref(false)
const editingRole = ref(null)
const selectedRole = ref(null)
const roleName = ref('')

const builtInRoles = ['admin', 'manager', 'staff']

const isBuiltIn = (role) =>
    builtInRoles.includes((role?.name || '').toLowerCase())

const getErrorMessage = (err) => {
    return err.response?.data?.message || 'Something went wrong. Please try again.'
}

const loadRoles = async (page = 1) => {
    if(!canManageRole) return
    loading.value = true
    error.value = ''

    try {
        const response = await api.get('/admin/roles', {
            params: {
                search: search.value.trim(),
                page,
            },
        })

        const result = response.data.data

        roles.value = result.data || []
        currentPage.value = result.current_page || 1
        lastPage.value = result.last_page || 1
    } catch (err) {
        error.value = getErrorMessage(err)
    } finally {
        loading.value = false
    }
}

const openCreateModal = () => {
    if(!canManageRole.value) return
    editingRole.value = null
    roleName.value = ''
    error.value = ''
    showModal.value = true
}

const openEditModal = (role) => {
    if(!canManageRole.value) return
    if (isBuiltIn(role)) return

    editingRole.value = role
    roleName.value = role.name
    error.value = ''
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    editingRole.value = null
    roleName.value = ''
}

const saveRole = async () => {
    if(!canManageRole.value) return
    const name = roleName.value.trim()

    if (!name) {
        error.value = 'Role name is required.'
        return
    }

    saving.value = true
    error.value = ''
    success.value = ''

    try {
        if (editingRole.value) {
            await api.put(`/admin/roles/${editingRole.value.id}`, { name })
            success.value = 'Role updated successfully.'
        } else {
            await api.post('/admin/roles', { name })
            success.value = 'Role created successfully.'
        }

        closeModal()
        await loadRoles(currentPage.value)
    } catch (err) {
        error.value = getErrorMessage(err)

        const validationErrors = err.response?.data?.errors
        if (validationErrors?.name?.length) {
            error.value = validationErrors.name[0]
        }
    } finally {
        saving.value = false
    }
}

const openDeleteModal = (role) => {
    if(!canManageRole.value) return 
    if (isBuiltIn(role)) return

    selectedRole.value = role
    error.value = ''
    showDeleteModal.value = true
}

const deleteRole = async () => {
    if(!canManageRole.value) return
    if (!selectedRole.value) return

    saving.value = true
    error.value = ''

    try {
        await api.delete(`/admin/roles/${selectedRole.value.id}`)

        showDeleteModal.value = false
        selectedRole.value = null
        success.value = 'Role deleted successfully.'

        const page = roles.value.length === 1 && currentPage.value > 1
            ? currentPage.value - 1
            : currentPage.value

        await loadRoles(page)
    } catch (err) {
        error.value = getErrorMessage(err)
        showDeleteModal.value = false
    } finally {
        saving.value = false
    }
}

onMounted(() => {
if(!canManageRole.value) return 
loadRoles()
}
)
</script>

<template>
    <div v-if="canManageRole" class="p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    Roles Management
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    Manage system roles and their assigned users.
                </p>
            </div>

            <button
                v-if="canManageRole"
                @click="openCreateModal"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
                + Create Role
            </button>
        </div>

        <div
            v-if="success"
            class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700"
        >
            {{ success }}
            <button
                @click="success = ''"
                class="ml-2 font-semibold"
                aria-label="Dismiss success message"
            >
                ×
            </button>
        </div>

        <div
            v-if="error && !showModal && !showDeleteModal"
            class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700"
        >
            {{ error }}
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <input
                v-model="search"
                @keyup.enter="loadRoles(1)"
                type="search"
                placeholder="Search roles..."
                class="w-full max-w-sm rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            />

            <button
                @click="loadRoles(1)"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50"
            >
                Search
            </button>

            <button
                @click="search = ''; loadRoles(1)"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50"
            >
                Clear
            </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div v-if="loading" class="p-10 text-center text-gray-500">
                Loading roles...
            </div>

            <div v-else-if="roles.length === 0" class="p-10 text-center">
                <p class="font-medium text-gray-700">No roles found</p>
                <p class="mt-1 text-sm text-gray-500">
                    Create a role or change your search.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-4">Role Name</th>
                            <th class="px-6 py-4">Assigned Users</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="role in roles"
                            :key="role.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="px-6 py-4 font-medium text-gray-800">
                                {{ role.name }}
                            </td>

                            <td class="px-6 py-4 text-gray-600">
                                {{ role.users_count ?? 0 }}
                            </td>

                            <td class="px-6 py-4">
                                <span
                                    v-if="isBuiltIn(role)"
                                    class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700"
                                >
                                    System role
                                </span>

                                <span
                                    v-else
                                    class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700"
                                >
                                    Custom role
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-3">
                                    <button
                                        v-if="!isBuiltIn(role) && canManageRole"
                                        @click="openEditModal(role)"
                                        class="font-medium text-indigo-600 hover:text-indigo-800"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        v-if="!isBuiltIn(role) && canManageRole"
                                        @click="openDeleteModal(role)"
                                        class="font-medium text-red-600 hover:text-red-800"
                                    >
                                        Delete
                                    </button>

                                    <span
                                        v-if="isBuiltIn(role)"
                                        class="text-xs text-gray-400"
                                    >
                                        Not Changeable
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between text-sm text-gray-600">
            <span>Page {{ currentPage }} of {{ lastPage }}</span>

            <div class="flex gap-2">
                <button
                    @click="loadRoles(currentPage - 1)"
                    :disabled="currentPage <= 1 || loading"
                    class="rounded-lg border px-3 py-2 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Previous
                </button>

                <button
                    @click="loadRoles(currentPage + 1)"
                    :disabled="currentPage >= lastPage || loading"
                    class="rounded-lg border px-3 py-2 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Next
                </button>
            </div>
        </div>

        <!-- Create / Edit modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="closeModal"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-800">
                    {{ editingRole ? 'Edit Role' : 'Create Role' }}
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Enter a unique role name.
                </p>

                <form @submit.prevent="saveRole" class="mt-5">
                    <label class="mb-2 block text-sm font-medium text-gray-700">
                        Role Name
                    </label>

                    <input
                        v-model="roleName"
                        type="text"
                        maxlength="100"
                        required
                        autofocus
                        placeholder="e.g. Supervisor"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:outline-none"
                    />

                    <p v-if="error" class="mt-2 text-sm text-red-600">
                        {{ error }}
                    </p>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="closeModal"
                            class="rounded-lg border px-4 py-2 text-sm"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="saving"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        >
                            {{ saving ? 'Saving...' : 'Save Role' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete confirmation modal -->
        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="showDeleteModal = false"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-800">
                    Delete Role
                </h2>

                <p class="mt-3 text-sm text-gray-600">
                    Are you sure you want to delete
                    <strong>{{ selectedRole?.name }}</strong>?
                </p>

                <p class="mt-2 text-sm text-gray-500">
                    Roles assigned to users cannot be deleted.
                </p>

                <p v-if="error" class="mt-3 text-sm text-red-600">
                    {{ error }}
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="showDeleteModal = false"
                        class="rounded-lg border px-4 py-2 text-sm"
                    >
                        Cancel
                    </button>

                    <button
                        @click="deleteRole"
                        :disabled="saving"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {{ saving ? 'Deleting...' : 'Delete Role' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
