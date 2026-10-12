
<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()

const canViewCustomers = computed(() =>authStore.hasPermission('customers.view'))

const canCreateCustomer = computed(() =>authStore.hasPermission('customers.create'))

const canUpdateCustomer = computed(() =>authStore.hasPermission('customers.update'))

const canDeleteCustomer = computed(() =>authStore.hasPermission('customers.delete'))
const customers = ref([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const error = ref('')
const validationErrors = ref({})
const showModal = ref(false)
const editingCustomer = ref(null)

const currentPage = ref(1)
const lastPage = ref(1)
const totalCustomers = ref(0)

const emptyForm = () => ({
  name: '',
  email: '',
  phone: '',
  address: '',
})

const form = ref(emptyForm())

async function fetchCustomers(page = 1) {
    if (!canViewCustomers.value) return
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/customers', {
      params: {
        search: search.value.trim(),
        page,
      },
    })

    // Controller response: { status: true, data: paginator }
    const paginator = response.data.data

    customers.value = paginator.data ?? []
    currentPage.value = paginator.current_page ?? 1
    lastPage.value = paginator.last_page ?? 1
    totalCustomers.value = paginator.total ?? customers.value.length
  } catch (err) {
    error.value =
      err.response?.data?.message || 'Unable to load customers.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
    if (!canCreateCustomer.value) return
  editingCustomer.value = null
  form.value = emptyForm()
  validationErrors.value = {}
  error.value = ''
  showModal.value = true
}

function openEditModal(customer) {
    if (!canUpdateCustomer.value) return
  editingCustomer.value = customer

  form.value = {
    name: customer.name ?? '',
    email: customer.email ?? '',
    phone: customer.phone ?? '',
    address: customer.address ?? '',
  }

  validationErrors.value = {}
  error.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingCustomer.value = null
  validationErrors.value = {}
}

function fieldError(field) {
  return validationErrors.value[field]?.[0] ?? ''
}

async function saveCustomer() {
    if(editingCustomer.value && !canUpdateCustomer.value) return
   if(!editingCustomer.value && !canCreateCustomer.value) return
  saving.value = true
  error.value = ''
  validationErrors.value = {}

  const payload = {
    name: form.value.name.trim(),
    email: form.value.email.trim() || null,
    phone: form.value.phone.trim() || null,
    address: form.value.address.trim() || null,
  }

  try {
    if (editingCustomer.value) {
      await api.put(
        `/customers/${editingCustomer.value.id}`,
        payload
      )
    } else {
      await api.post('/customers', payload)
    }

    closeModal()
    await fetchCustomers(
      editingCustomer.value ? currentPage.value : 1
    )
  } catch (err) {
    if (err.response?.status === 422) {
      validationErrors.value = err.response.data.errors ?? {}
      error.value =
        err.response.data.message || 'Please correct the validation errors.'
    } else {
      error.value =
        err.response?.data?.message || 'Unable to save the customer.'
    }
  } finally {
    saving.value = false
  }
}

async function deleteCustomer(customer) {
    if(!canDeleteCustomer.value) return
  error.value = ''

  try {
    await api.delete(`/customers/${customer.id}`)

    const nextPage =
      customers.value.length === 1 && currentPage.value > 1
        ? currentPage.value - 1
        : currentPage.value

    await fetchCustomers(nextPage)
  } catch (err) {
    error.value =
      err.response?.data?.message || 'Unable to delete the customer.'
  }
}

onMounted(() => {
    if (canViewCustomers.value) {
        fetchCustomers()
    }
})
</script>

<template>
  <div v-if="canViewCustomers" class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Customers</h1>
        <p class="mt-1 text-sm text-gray-500">
          Manage customer information and contact details.
        </p>
      </div>

      <button
        type="button"
         v-if="canCreateCustomer"
        @click="openCreateModal"
        class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
      >
        + Add Customer
      </button>
    </div>

    <!-- Error -->
    <div
      v-if="error && !showModal"
      role="alert"
      class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
    >
      {{ error }}
    </div>

    <!-- Search -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <form
        @submit.prevent="fetchCustomers(1)"
        class="flex w-full gap-2 sm:max-w-lg"
      >
        <input
          v-model="search"
          type="search"
          placeholder="Search name, email, or phone..."
          class="min-w-0 flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
        />

        <button
          type="submit"
          class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50"
        >
          Search
        </button>
      </form>

      <p class="text-sm text-gray-500">
        Total customers: <strong>{{ totalCustomers }}</strong>
      </p>
    </div>

    <!-- Customer table -->
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                Customer
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                Email
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                Phone
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                Address
              </th>
              <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                Actions
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500">
                Loading customers...
              </td>
            </tr>

            <tr v-else-if="customers.length === 0">
              <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500">
                No customers found. Try another search or add a customer.
              </td>
            </tr>

            <tr
              v-for="customer in customers"
              :key="customer.id"
              class="hover:bg-gray-50"
            >
              <td class="whitespace-nowrap px-5 py-4">
                <p class="font-medium text-gray-900">{{ customer.name }}</p>
                <p class="mt-1 text-xs text-gray-500">
                  Customer #{{ customer.id }}
                </p>
              </td>

              <td class="px-5 py-4 text-sm text-gray-600">
                {{ customer.email || '—' }}
              </td>

              <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">
                {{ customer.phone || '—' }}
              </td>

              <td class="max-w-xs px-5 py-4 text-sm text-gray-600">
                <span class="block truncate">
                  {{ customer.address || '—' }}
                </span>
              </td>

              <td class="whitespace-nowrap px-5 py-4 text-right">
                <button
                  type="button"
                  v-if="canUpdateCustomer"
                  @click="openEditModal(customer)"
                  class="mr-3 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                  Edit
                </button>

                <button
                  type="button"
                  v-if="canDeleteCustomer"
                  @click="deleteCustomer(customer)"
                  class="text-sm font-medium text-red-600 hover:text-red-800"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-500">
          Page {{ currentPage }} of {{ lastPage }}
        </p>

        <div class="flex gap-2">
          <button
            type="button"
            :disabled="currentPage <= 1 || loading"
            @click="fetchCustomers(currentPage - 1)"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-40"
          >
            Previous
          </button>

          <button
            type="button"
            :disabled="currentPage >= lastPage || loading"
            @click="fetchCustomers(currentPage + 1)"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-40"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4"
      @click.self="closeModal"
    >
      <div class="my-auto w-full max-w-xl rounded-xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">
            {{ editingCustomer ? 'Edit Customer' : 'Add Customer' }}
          </h2>

          <button
            type="button"
            @click="closeModal"
            aria-label="Close"
            class="text-2xl leading-none text-gray-400 hover:text-gray-700"
          >
            &times;
          </button>
        </div>

        <form @submit.prevent="saveCustomer" class="space-y-5 p-6">
          <div
            v-if="error"
            role="alert"
            class="rounded-lg bg-red-50 p-3 text-sm text-red-700"
          >
            {{ error }}
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">
              Customer Name *
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              maxlength="255"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            />
            <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600">
              {{ fieldError('name') }}
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">
              Email
            </label>
            <input
              v-model="form.email"
              type="email"
              maxlength="255"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            />
            <p v-if="fieldError('email')" class="mt-1 text-xs text-red-600">
              {{ fieldError('email') }}
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">
              Phone
            </label>
            <input
              v-model="form.phone"
              type="tel"
              maxlength="30"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            />
            <p v-if="fieldError('phone')" class="mt-1 text-xs text-red-600">
              {{ fieldError('phone') }}
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">
              Address
            </label>
            <textarea
              v-model="form.address"
              rows="3"
              class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
            ></textarea>
            <p v-if="fieldError('address')" class="mt-1 text-xs text-red-600">
              {{ fieldError('address') }}
            </p>
          </div>

          <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
            <button
              type="button"
              @click="closeModal"
              class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </button>

            <button
              type="submit"
              :disabled="saving"
              class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{
                saving
                  ? 'Saving...'
                  : editingCustomer
                    ? 'Update Customer'
                    : 'Create Customer'
              }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
