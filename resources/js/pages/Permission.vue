
<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()

const canManagePermissions = computed(() =>authStore.hasPermission('permissions.manage'))
const roles = ref([])
const permissionsByModule = ref({})

const selectedRoleId = ref('')
const selectedPermissionIds = ref([])
const originalPermissionIds = ref([])

const loadingRoles = ref(false)
const loadingPermissions = ref(false)
const saving = ref(false)

const error = ref('')
const success = ref('')
const search = ref('')

const modules = computed(() => {
    const query = search.value.trim().toLowerCase()

    return Object.entries(permissionsByModule.value)
        .map(([module, permissions]) => ({
            name: module,
            permissions: permissions.filter(permission => {
                if (!query) return true

                return (
                    permission.name.toLowerCase().includes(query) ||
                    (permission.description || '').toLowerCase().includes(query) ||
                    module.toLowerCase().includes(query)
                )
            }),
        }))
        .filter(module => module.permissions.length > 0)
})

const selectedRole = computed(() =>
    roles.value.find(role => String(role.id) === String(selectedRoleId.value))
)

const isAdminRole = computed(() =>
    selectedRole.value?.name?.toLowerCase() === 'admin'
)

const selectedCount = computed(() => selectedPermissionIds.value.length)

const totalPermissions = computed(() =>
    Object.values(permissionsByModule.value)
        .reduce((total, permissions) => total + permissions.length, 0)
)

const hasChanges = computed(() => {
    const current = [...selectedPermissionIds.value].sort((a, b) => a - b)
    const original = [...originalPermissionIds.value].sort((a, b) => a - b)

    return JSON.stringify(current) !== JSON.stringify(original)
})

const getErrorMessage = err =>
    err.response?.data?.message || 'Something went wrong. Please try again.'

const loadRoles = async () => {
    if (!canManagePermissions.value) return
    loadingRoles.value = true
    error.value = ''

    try {
        // Uses the existing role-list endpoint.
        const response = await api.get('/admin/roles', {
            params: { per_page: 100 },
        })

        const result = response.data.data
        roles.value = result.data || []

        if (roles.value.length > 0) {
            selectedRoleId.value = String(roles.value[0].id)
        }
    } catch (err) {
        error.value = getErrorMessage(err)
    } finally {
        loadingRoles.value = false
    }
}

const loadPermissions = async () => {
    if (!canManagePermissions.value) return
    loadingPermissions.value = true
    error.value = ''

    try {
        const response = await api.get('/admin/permissions')
        permissionsByModule.value = response.data.data || {}
    } catch (err) {
        error.value = getErrorMessage(err)
    } finally {
        loadingPermissions.value = false
    }
}

const loadRolePermissions = async () => {
    if (!canManagePermissions.value) return
    if (!selectedRoleId.value) {
        selectedPermissionIds.value = []
        originalPermissionIds.value = []
        return
    }

    loadingPermissions.value = true
    error.value = ''
    success.value = ''

    try {
        const response = await api.get(
            `/admin/roles/${selectedRoleId.value}/permissions`
        )

        const ids = response.data.data.permission_ids || []

        selectedPermissionIds.value = ids.map(Number)
        originalPermissionIds.value = [...selectedPermissionIds.value]
    } catch (err) {
        error.value = getErrorMessage(err)
        selectedPermissionIds.value = []
        originalPermissionIds.value = []
    } finally {
        loadingPermissions.value = false
    }
}

const initialize = async () => {
    if (!canManagePermissions.value) return
    loadingRoles.value = true

    await Promise.all([
        loadRoles(),
        loadPermissions(),
    ])

    if (selectedRoleId.value) {
        await loadRolePermissions()
    }
}

const changeRole = async () => {
    if (!canManagePermissions.value) return
    selectedPermissionIds.value = []
    originalPermissionIds.value = []
    await loadRolePermissions()
}

const isPermissionSelected = id =>
    selectedPermissionIds.value.includes(Number(id))

const togglePermission = (permissionId, checked) => {
    if (!canManagePermissions.value || isAdminRole.value) return
    const id = Number(permissionId)

    if (checked && !isPermissionSelected(id)) {
        selectedPermissionIds.value = [
            ...selectedPermissionIds.value,
            id,
        ]
    } else if (!checked) {
        selectedPermissionIds.value =
            selectedPermissionIds.value.filter(item => item !== id)
    }
}

const isModuleFullySelected = module => {
    const ids = module.permissions.map(permission => Number(permission.id))

    return ids.length > 0 &&
        ids.every(id => isPermissionSelected(id))
}

const toggleModule = (module, checked) => {
    if (!canManagePermissions.value || isAdminRole.value) return
    const ids = module.permissions.map(permission => Number(permission.id))

    if (checked) {
        selectedPermissionIds.value = [
            ...new Set([...selectedPermissionIds.value, ...ids]),
        ]
    } else {
        selectedPermissionIds.value =
            selectedPermissionIds.value.filter(id => !ids.includes(id))
    }
}

const resetChanges = () => {
    if (!canManagePermissions.value) return
    selectedPermissionIds.value = [...originalPermissionIds.value]
    error.value = ''
    success.value = ''
}

const savePermissions = async () => {
    if(!canManagePermissions.value || !selectedRole.value || isAdminRole.value) return

    saving.value = true
    error.value = ''
    success.value = ''

    try {
        const response = await api.put(
            `/admin/roles/${selectedRoleId.value}/permissions`,
            {
                permission_ids: selectedPermissionIds.value,
            }
        )

        const savedIds = (
            response.data.data.permission_ids || []
        ).map(Number)

        selectedPermissionIds.value = savedIds
        originalPermissionIds.value = [...savedIds]

        success.value =
            response.data.message || 'Permissions saved successfully.'
    } catch (err) {
        error.value = getErrorMessage(err)

        const validationErrors = err.response?.data?.errors

        if (validationErrors?.permission_ids?.length) {
            error.value = validationErrors.permission_ids[0]
        } else if (validationErrors?.role?.length) {
            error.value = validationErrors.role[0]
        }
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    if (!canManagePermissions.value) return
    initialize()
})
</script>

<template>
    <div v-if="canManagePermissions" class="space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    Permission Management
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Configure which actions each role can perform.
                </p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs text-slate-500">Available permissions</p>
                <p class="mt-1 text-xl font-bold text-slate-800">
                    {{ totalPermissions }}
                </p>
            </div>
        </div>

        <!-- Alerts -->
        <div
            v-if="success"
            class="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700"
        >
            <span>{{ success }}</span>
            <button @click="success = ''" aria-label="Dismiss success">×</button>
        </div>

        <div
            v-if="error"
            class="flex items-center justify-between rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
            <span>{{ error }}</span>
            <button @click="error = ''" aria-label="Dismiss error">×</button>
        </div>

        <!-- Role selector and summary -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-5 md:grid-cols-2 md:items-end">
                <div>
                    <label
                        for="role-select"
                        class="mb-2 block text-sm font-semibold text-slate-700"
                    >
                        Select Role
                    </label>

                    <select
                        id="role-select"
                        v-model="selectedRoleId"
                        @change="changeRole"
                        :disabled="loadingRoles || loadingPermissions"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none"
                    >
                        <option value="" disabled>Select a role</option>

                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="String(role.id)"
                        >
                            {{ role.name }}
                        </option>
                    </select>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-slate-500">
                            Selected permissions
                        </p>
                        <p class="text-lg font-semibold text-slate-800">
                            {{ selectedCount }} / {{ totalPermissions }}
                        </p>
                    </div>

                    <span
                        v-if="isAdminRole"
                        class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800"
                    >
                        Protected role
                    </span>

                    <span
                        v-else-if="hasChanges"
                        class="rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-800"
                    >
                        Unsaved changes
                    </span>
                </div>
            </div>

            <p
                v-if="isAdminRole"
                class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800"
            >
                The built-in Admin role cannot be modified from this page.
            </p>
        </div>

        <!-- Search -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <input
                v-model="search"
                type="search"
                placeholder="Search permissions or modules..."
                class="w-full max-w-md rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm focus:border-indigo-500 focus:outline-none"
            />

            <span class="text-sm text-slate-500">
                {{ modules.length }} modules shown
            </span>
        </div>

        <!-- Loading -->
        <div
            v-if="loadingRoles || loadingPermissions"
            class="rounded-xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-500"
        >
            Loading permissions...
        </div>

        <!-- No role -->
        <div
            v-else-if="!selectedRole"
            class="rounded-xl border border-slate-200 bg-white p-12 text-center"
        >
            <h2 class="font-semibold text-slate-700">No role available</h2>
            <p class="mt-1 text-sm text-slate-500">
                Create a role before assigning permissions.
            </p>
        </div>

        <!-- Permission modules -->
        <div v-else-if="modules.length" class="space-y-5">
            <section
                v-for="module in modules"
                :key="module.name"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4"
                >
                    <div>
                        <h2 class="font-semibold text-slate-800">
                            {{ module.name }}
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ module.permissions.length }} permissions
                        </p>
                    </div>

                    <label
                        v-if="!isAdminRole"
                        class="flex cursor-pointer items-center gap-2 text-sm font-medium text-indigo-700"
                    >
                        <input
                            type="checkbox"
                            :checked="isModuleFullySelected(module)"
                            @change="toggleModule(module, $event.target.checked)"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        Select all
                    </label>
                </div>

                <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="permission in module.permissions"
                        :key="permission.id"
                        class="flex gap-3 rounded-lg border border-slate-200 p-4"
                        :class="isAdminRole
                            ? 'cursor-not-allowed bg-slate-50'
                            : 'cursor-pointer hover:border-indigo-300 hover:bg-indigo-50/40'"
                    >
                        <input
                            type="checkbox"
                            :checked="isPermissionSelected(permission.id)"
                            :disabled="isAdminRole"
                            @change="togglePermission(permission.id, $event.target.checked)"
                            class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />

                        <span class="min-w-0">
                            <span class="block break-words text-sm font-medium text-slate-800">
                                {{ permission.name }}
                            </span>

                            <span class="mt-1 block text-xs leading-5 text-slate-500">
                                {{ permission.description || 'No description available.' }}
                            </span>
                        </span>
                    </label>
                </div>
            </section>
        </div>

        <div
            v-else-if="!loadingPermissions"
            class="rounded-xl border border-slate-200 bg-white p-12 text-center"
        >
            <h2 class="font-semibold text-slate-700">
                No permissions found
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Run the PermissionSeeder and check your database.
            </p>
        </div>

        <!-- Actions -->
        <div
            v-if="selectedRole && !loadingPermissions && !loadingRoles"
            class="sticky bottom-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-lg"
        >
            <p class="text-sm text-slate-500">
                <template v-if="isAdminRole">
                    Admin permissions are protected.
                </template>
                <template v-else-if="hasChanges">
                    You have unsaved permission changes.
                </template>
                <template v-else>
                    All changes are saved.
                </template>
            </p>

            <div class="flex gap-3">
                <button
                    v-if="!isAdminRole"
                    @click="resetChanges"
                    :disabled="!hasChanges || saving"
                    class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Reset
                </button>

                <button
                    v-if="!isAdminRole"
                    @click="savePermissions"
                    :disabled="!hasChanges || saving"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ saving ? 'Saving...' : 'Save Permissions' }}
                </button>
            </div>
        </div>
    </div>
</template>
