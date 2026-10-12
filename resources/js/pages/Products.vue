
<script setup>
import { ref, onMounted,computed } from 'vue'
import api from '../services/api'
import AppToast from '../components/ToastAlert.vue'
import ConfirmModal from '../components/ModalConfirm.vue'
import { useAuthStore } from '../stores/auth'
const authStore = useAuthStore();

const canViewProducts = computed(() =>authStore.hasPermission('products.view'))

const canCreateProduct = computed(() =>authStore.hasPermission('products.create'))

const canUpdateProduct = computed(() =>authStore.hasPermission('products.update'))

const canDeleteProduct = computed(() =>authStore.hasPermission('products.delete'))
const products = ref([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const error = ref('')
const validationErrors = ref({})
const showModal = ref(false)
const editingProduct = ref(null)

const currentPage = ref(1)
const lastPage = ref(1)
const totalProducts = ref(0)
const toastRef = ref(null)
const confirmModalRef = ref(null)
const emptyForm = () => ({
  name: '',
  sku: '',
  description: '',
  price: '',
  stock_quantity: 0,
  status: 'active',
})

const form = ref(emptyForm())

async function fetchProducts(page = 1) {
    if(!canViewProducts.value) return
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/products', {
      params: {
        search: search.value.trim(),
        page,
      },
    })

    // Laravel returns { success: true, data: paginator }
    const paginator = response.data.data

    products.value = paginator.data ?? []
    currentPage.value = paginator.current_page ?? 1
    lastPage.value = paginator.last_page ?? 1
    totalProducts.value = paginator.total ?? products.value.length
  } catch (err) {
    error.value =
      err.response?.data?.message || 'Unable to load products.'
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
    if(!canCreateProduct) return
  editingProduct.value = null
  form.value = emptyForm()
  validationErrors.value = {}
  error.value = ''
  showModal.value = true
}

function openEditModal(product) {
    if(!canUpdateProduct) return
  editingProduct.value = product

  form.value = {
    name: product.name ?? '',
    sku: product.sku ?? '',
    description: product.description ?? '',
    price: product.price ?? '',
    stock_quantity: product.stock_quantity ?? 0,
    status: product.status ?? 'active',
  }

  validationErrors.value = {}
  error.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingProduct.value = null
  validationErrors.value = {}
}

function fieldError(field) {
  return validationErrors.value[field]?.[0] ?? ''
}

async function saveProduct() {
    if (editingProduct.value && !canUpdateProduct.value) return
    if (!editingProduct.value && !canCreateProduct.value) return
  saving.value = true
  error.value = ''
  validationErrors.value = {}

  const payload = {
    name: form.value.name.trim(),
    sku: form.value.sku.trim(),
    description: form.value.description.trim() || null,
    price: Number(form.value.price),
    stock_quantity: Number(form.value.stock_quantity),
    status: form.value.status,
  }

  try {
    if (editingProduct.value) {
      await api.put(
        `/products/${editingProduct.value.id}`,
        payload
      )
    } else {
      await api.post('/products', payload)
    }

    closeModal()
    await fetchProducts(currentPage.value)
  } catch (err) {
    if (err.response?.status === 422) {
      validationErrors.value = err.response.data.errors ?? {}
      error.value = err.response.data.message || 'Please correct the errors.'
    } else {
      error.value =
        err.response?.data?.message || 'Unable to save the product.'
    }
  } finally {
    saving.value = false
  }
}

async function deleteProduct(product) {
    if (!canDeleteProduct.value) return
  const confirmed = await confirmModalRef.value.open({
        title: 'Delete product?',
        message: `Are you sure you want to delete "${product.name}"? This action cannot be undone.`,
        confirmText: 'Delete product',
        variant: 'danger',
    })

    if (!confirmed) return

    try {
        await api.delete(`/products/${product.id}`)

        toastRef.value.show('Product deleted successfully.', 'success')

    const nextPage =
      products.value.length === 1 && currentPage.value > 1
        ? currentPage.value - 1
        : currentPage.value

    await fetchProducts(nextPage)
  } catch (err) {
    const message =error.response?.data?.message || 'Unable to delete the product.'
    toastRef.value.show(message, 'error')
  }
}

function formatPrice(price) {
  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 2,
  }).format(Number(price ?? 0))
}

onMounted(() => fetchProducts())
</script>

<template>
  <div v-if="canViewProducts" class="space-y-6">
    <!-- Page heading -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Products</h1>
        <p class="mt-1 text-sm text-gray-500">
          Manage your product catalogue, pricing, and stock.
        </p>
      </div>

      <button
        type="button"
        v-if="canCreateProduct"
        @click="openCreateModal"
        class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
      >
        + Add Product
      </button>
    </div>

    <!-- Error message -->
    <div
      v-if="error && !showModal"
      class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
      role="alert"
    >
      {{ error }}
    </div>

    <!-- Search and count -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <form
        @submit.prevent="fetchProducts(1)"
        class="flex w-full gap-2 sm:max-w-lg"
      >
        <input
          v-model="search"
          type="search"
          placeholder="Search by product name or SKU..."
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
        Total products: <strong>{{ totalProducts }}</strong>
      </p>
    </div>

    <!-- Product table -->
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                Product
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                SKU
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                Price
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                Stock
              </th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                Status
              </th>
              <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                Actions
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading">
              <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">
                Loading products...
              </td>
            </tr>

            <tr v-else-if="products.length === 0">
              <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">
                No products found. Try another search or add a product.
              </td>
            </tr>

            <tr
              v-for="product in products"
              :key="product.id"
              class="hover:bg-gray-50"
            >
              <td class="px-5 py-4">
                <p class="font-medium text-gray-900">{{ product.name }}</p>
                <p class="mt-1 max-w-xs truncate text-xs text-gray-500">
                  {{ product.description || 'No description' }}
                </p>
              </td>

              <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">
                {{ product.sku }}
              </td>

              <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-900">
                {{ formatPrice(product.price) }}
              </td>

              <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">
                <span
                  :class="Number(product.stock_quantity) <= 5
                    ? 'font-semibold text-red-600'
                    : 'text-gray-700'"
                >
                  {{ product.stock_quantity }}
                </span>
                <span
                  v-if="Number(product.stock_quantity) <= 5"
                  class="ml-1 text-xs text-red-600"
                >
                  Low stock
                </span>
              </td>

              <td class="whitespace-nowrap px-5 py-4">
                <span
                  class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                  :class="product.status === 'active'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-gray-100 text-gray-600'"
                >
                  {{ product.status }}
                </span>
              </td>

              <td class="whitespace-nowrap px-5 py-4 text-right">
                <button
                  type="button"
                  v-if="canUpdateProduct"
                  @click="openEditModal(product)"
                  class="mr-3 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                  Edit
                </button>

                <button
                  type="button"
                  v-if="canDeleteProduct"
                  @click="deleteProduct(product)"
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
            @click="fetchProducts(currentPage - 1)"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-40"
          >
            Previous
          </button>

          <button
            type="button"
            :disabled="currentPage >= lastPage || loading"
            @click="fetchProducts(currentPage + 1)"
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
      <div class="my-auto w-full max-w-2xl rounded-xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">
            {{ editingProduct ? 'Edit Product' : 'Add Product' }}
          </h2>

          <button
            type="button"
            @click="closeModal"
            class="text-2xl leading-none text-gray-400 hover:text-gray-700"
            aria-label="Close"
          >
            &times;
          </button>
        </div>

        <form @submit.prevent="saveProduct" class="space-y-5 p-6">
          <div
            v-if="error"
            class="rounded-lg bg-red-50 p-3 text-sm text-red-700"
            role="alert"
          >
            {{ error }}
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700">
                Product Name *
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
                SKU *
              </label>
              <input
                v-model="form.sku"
                type="text"
                required
                maxlength="100"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              />
              <p v-if="fieldError('sku')" class="mt-1 text-xs text-red-600">
                {{ fieldError('sku') }}
              </p>
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700">
                Price (₹) *
              </label>
              <input
                v-model="form.price"
                type="number"
                min="0"
                step="0.01"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              />
              <p v-if="fieldError('price')" class="mt-1 text-xs text-red-600">
                {{ fieldError('price') }}
              </p>
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700">
                Stock Quantity *
              </label>
              <input
                v-model.number="form.stock_quantity"
                type="number"
                min="0"
                step="1"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              />
              <p v-if="fieldError('stock_quantity')" class="mt-1 text-xs text-red-600">
                {{ fieldError('stock_quantity') }}
              </p>
            </div>

            <div class="sm:col-span-2">
              <label class="mb-1.5 block text-sm font-medium text-gray-700">
                Description
              </label>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              ></textarea>
              <p v-if="fieldError('description')" class="mt-1 text-xs text-red-600">
                {{ fieldError('description') }}
              </p>
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700">
                Status *
              </label>
              <select
                v-model="form.status"
                required
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
              >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
              <p v-if="fieldError('status')" class="mt-1 text-xs text-red-600">
                {{ fieldError('status') }}
              </p>
            </div>
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
              {{ saving ? 'Saving...' : editingProduct ? 'Update Product' : 'Create Product' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
