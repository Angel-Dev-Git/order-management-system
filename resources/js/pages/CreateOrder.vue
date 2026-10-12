
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'

const router = useRouter()

const step = ref(1)
const loading = ref(false)
const saving = ref(false)
const errorMessage = ref('')

const customers = ref([])
const products = ref([])

const form = ref({
    customer_id: '',
    status: 'draft',
    priority: 'normal',
    discount: 0,
    tax: 0,
    items: [
        {
            product_id: '',
            quantity: 1,
        },
    ],
})

const steps = [
    'Customer',
    'Products',
    'Pricing',
    'Assignment',
    'Review',
]

const selectedCustomer = computed(() =>
    customers.value.find(
        customer => customer.id === Number(form.value.customer_id)
    )
)

const selectedProducts = computed(() =>
    form.value.items.map(item => {
        const product = products.value.find(
            product => product.id === Number(item.product_id)
        )

        const quantity = Number(item.quantity) || 0
        const unitPrice = Number(product?.price) || 0

        return {
            product_id: item.product_id,
            product,
            quantity,
            unit_price: unitPrice,
            lineTotal: unitPrice * quantity,
        }
    })
)

const subtotal = computed(() =>
    selectedProducts.value.reduce(
        (sum, item) => sum + item.lineTotal,
        0
    )
)

const discountAmount = computed(() =>
    Math.min(
        subtotal.value,
        Math.max(0, Number(form.value.discount) || 0)
    )
)

const taxAmount = computed(() =>
    Math.max(0, Number(form.value.tax) || 0)
)

const total = computed(() =>
    Math.max(0, subtotal.value - discountAmount.value + taxAmount.value)
)

async function loadFormData() {
    loading.value = true
    errorMessage.value = ''

    try {
        const [customerResponse, productResponse] = await Promise.all([
            api.get('/customers', {
                params: { per_page: 100 },
            }),
            api.get('/products', {
                params: { per_page: 100 },
            }),
        ])

        const customerData = customerResponse.data.data
        const productData = productResponse.data.data

        customers.value = Array.isArray(customerData)
            ? customerData
            : customerData?.data ?? []

        products.value = Array.isArray(productData)
            ? productData
            : productData?.data ?? []
    } catch (error) {
        errorMessage.value =
            error.response?.data?.message ??
            'Unable to load customers and products.'
    } finally {
        loading.value = false
    }
}

function addItem() {
    form.value.items.push({
        product_id: '',
        quantity: 1,
    })
}

function removeItem(index) {
    if (form.value.items.length > 1) {
        form.value.items.splice(index, 1)
    }
}

function validateStep() {
    errorMessage.value = ''

    if (step.value === 1) {
        if (!form.value.customer_id) {
            errorMessage.value = 'Please select a customer.'
            return false
        }
    }

    if (step.value === 2) {
        if (!form.value.items.length) {
            errorMessage.value = 'Please add at least one product.'
            return false
        }

        for (const item of form.value.items) {
            const product = products.value.find(
                product => product.id === Number(item.product_id)
            )

            if (!product) {
                errorMessage.value = 'Please select a valid product.'
                return false
            }

            if (product.status !== 'active') {
                errorMessage.value = `${product.name} is not active.`
                return false
            }

            if (
                !Number.isInteger(Number(item.quantity)) ||
                Number(item.quantity) < 1
            ) {
                errorMessage.value =
                    'Product quantity must be a positive whole number.'
                return false
            }

            if (
                Number(item.quantity) >
                Number(product.stock_quantity)
            ) {
                errorMessage.value =
                    `Insufficient stock for ${product.name}.`
                return false
            }
        }

        const productIds = form.value.items.map(
            item => Number(item.product_id)
        )

        if (new Set(productIds).size !== productIds.length) {
            errorMessage.value =
                'Please remove duplicate products or combine their quantities.'
            return false
        }
    }

    if (step.value === 3) {
        const discount = Number(form.value.discount)
        const tax = Number(form.value.tax)

        if (!Number.isFinite(discount) || discount < 0) {
            errorMessage.value = 'Discount cannot be negative.'
            return false
        }

        if (discount > subtotal.value) {
            errorMessage.value =
                'Discount cannot exceed the subtotal.'
            return false
        }

        if (!Number.isFinite(tax) || tax < 0) {
            errorMessage.value = 'Tax cannot be negative.'
            return false
        }
    }

    return true
}

function nextStep() {
    if (validateStep() && step.value < steps.length) {
        step.value++
    }
}

function previousStep() {
    errorMessage.value = ''

    if (step.value > 1) {
        step.value--
    }
}

async function submitOrder() {
    errorMessage.value = ''

    // Validate every step before submitting.
    const originalStep = step.value

    for (let currentStep = 1; currentStep <= 3; currentStep++) {
        step.value = currentStep

        if (!validateStep()) {
            return
        }
    }

    step.value = originalStep

    saving.value = true

    try {
        const payload = {
            customer_id: Number(form.value.customer_id),
            status: form.value.status,
            priority: form.value.priority,
            discount: Number(form.value.discount),
            tax: Number(form.value.tax),
            items: form.value.items.map(item => {
                const product = products.value.find(
                    product => product.id === Number(item.product_id)
                )

                const quantity = Number(item.quantity)
                const unitPrice = Number(product?.price ?? 0)

                return {
                    product_id: Number(item.product_id),
                    product_name: product.name,
                    sku: product.sku,
                    quantity,
                    unit_price: unitPrice,
                    total: Number((unitPrice * quantity).toFixed(2)),
                }
            }),

        }

        console.log('Create order payload:', payload)

        const response = await api.post('/orders', payload)

        const responseData = response.data.data

        const createdOrder =
            responseData?.order ?? responseData

        if (createdOrder?.id) {
            await router.push(`/orders/${createdOrder.id}`)
        } else {
            await router.push('/orders')
        }
    } catch (error) {
        console.error(
            'Create order error:',
            error.response?.data ?? error
        )

        const validationErrors = error.response?.data?.errors

        if (validationErrors) {
            errorMessage.value = Object.values(validationErrors)
                .flat()
                .join(' ')
        } else {
            errorMessage.value =
                error.response?.data?.message ??
                'Unable to create order. Please try again.'
        }
    } finally {
        saving.value = false
    }
}

function formatCurrency(amount) {
    return Number(amount ?? 0).toLocaleString('en-IN', {
        style: 'currency',
        currency: 'INR',
    })
}

onMounted(loadFormData)
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <button
                type="button"
                @click="router.push('/orders')"
                class="mb-3 text-sm text-blue-600 hover:underline"
            >
                ← Back to Orders
            </button>

            <h1 class="text-2xl font-bold text-slate-900">
                Create Order
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Complete the steps to create a new order.
            </p>
        </div>

        <div class="grid grid-cols-5 gap-2">
            <button
                v-for="(label, index) in steps"
                :key="label"
                type="button"
                @click="index + 1 < step && (step = index + 1)"
                class="rounded-lg px-2 py-3 text-center text-xs font-medium sm:text-sm"
                :class="step === index + 1
                    ? 'bg-blue-600 text-white'
                    : step > index + 1
                        ? 'bg-blue-100 text-blue-800'
                        : 'bg-white text-slate-500'"
            >
                <span class="block">{{ index + 1 }}</span>
                {{ label }}
            </button>
        </div>

        <div
            v-if="loading"
            class="rounded-xl bg-white p-10 text-center text-slate-500"
        >
            Loading customers and products...
        </div>

        <template v-else>
            <div
                v-if="errorMessage"
                role="alert"
                class="rounded-lg bg-red-50 p-4 text-sm text-red-700"
            >
                {{ errorMessage }}
            </div>

            <section class="rounded-xl bg-white p-6 shadow-sm">
                <!-- Step 1: Customer -->
                <div v-if="step === 1">
                    <h2 class="text-lg font-semibold">
                        1. Customer Information
                    </h2>

                    <label class="mb-2 mt-5 block text-sm font-medium">
                        Select Customer *
                    </label>

                    <select
                        v-model="form.customer_id"
                        class="w-full rounded-lg border border-slate-300 px-3 py-3"
                    >
                        <option value="">Choose a customer</option>

                        <option
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="customer.id"
                        >
                            {{ customer.name }} —
                            {{ customer.email ?? customer.phone ?? '' }}
                        </option>
                    </select>

                    <div
                        v-if="selectedCustomer"
                        class="mt-4 rounded-lg bg-slate-50 p-4 text-sm"
                    >
                        <p class="font-medium">
                            {{ selectedCustomer.name }}
                        </p>
                        <p class="mt-1 text-slate-500">
                            {{ selectedCustomer.email ?? 'No email' }}
                        </p>
                        <p class="mt-1 text-slate-500">
                            {{ selectedCustomer.phone ?? 'No phone' }}
                        </p>
                    </div>
                </div>

                <!-- Step 2: Products -->
                <div v-else-if="step === 2">
                    <h2 class="text-lg font-semibold">
                        2. Products
                    </h2>

                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="mt-5 grid gap-3 border-b pb-5 sm:grid-cols-12"
                    >
                        <div class="sm:col-span-7">
                            <label class="mb-2 block text-sm font-medium">
                                Product *
                            </label>

                            <select
                                v-model="item.product_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-3"
                            >
                                <option value="">Choose product</option>

                                <option
                                    v-for="product in products"
                                    :key="product.id"
                                    :value="product.id"
                                    :disabled="
                                        product.status !== 'active' ||
                                        Number(product.stock_quantity) < 1
                                    "
                                >
                                    {{ product.name }} ({{ product.sku }}) —
                                    {{ formatCurrency(product.price) }}
                                    · Stock: {{ product.stock_quantity }}
                                </option>
                            </select>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="mb-2 block text-sm font-medium">
                                Quantity *
                            </label>

                            <input
                                v-model.number="item.quantity"
                                type="number"
                                min="1"
                                step="1"
                                :max="products.find(
                                    product =>
                                        product.id === Number(item.product_id)
                                )?.stock_quantity ?? 999999"
                                class="w-full rounded-lg border border-slate-300 px-3 py-3"
                            />
                        </div>

                        <div class="flex items-end sm:col-span-2">
                            <button
                                type="button"
                                @click="removeItem(index)"
                                :disabled="form.items.length === 1"
                                class="rounded-lg border border-red-200 px-3 py-3 text-sm text-red-600 disabled:opacity-40"
                            >
                                Remove
                            </button>
                        </div>

                        <div
                            v-if="item.product_id"
                            class="text-sm text-slate-500 sm:col-span-12"
                        >
                            Unit price:
                            {{
                                formatCurrency(
                                    products.find(
                                        product =>
                                            product.id === Number(item.product_id)
                                    )?.price
                                )
                            }}
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="addItem"
                        class="mt-5 rounded-lg border border-blue-200 px-4 py-2 text-sm font-medium text-blue-700"
                    >
                        + Add Product
                    </button>
                </div>

                <!-- Step 3: Pricing -->
                <div v-else-if="step === 3">
                    <h2 class="text-lg font-semibold">
                        3. Pricing
                    </h2>

                    <div class="mt-5 space-y-4">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Subtotal</span>
                            <strong>{{ formatCurrency(subtotal) }}</strong>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Discount Amount (₹)
                            </label>

                            <input
                                v-model.number="form.discount"
                                type="number"
                                min="0"
                                :max="subtotal"
                                step="0.01"
                                class="w-full rounded-lg border border-slate-300 px-3 py-3"
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium">
                                Tax Amount (₹)
                            </label>

                            <input
                                v-model.number="form.tax"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-lg border border-slate-300 px-3 py-3"
                            />
                        </div>

                        <div class="flex justify-between border-t pt-4 text-lg">
                            <span class="font-semibold">
                                Estimated Total
                            </span>
                            <strong>{{ formatCurrency(total) }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Assignment and Priority -->
                <div v-else-if="step === 4">
                    <h2 class="text-lg font-semibold">
                        4. Assignment & Priority
                    </h2>

                    <div class="mt-5">
                        <label class="mb-2 block text-sm font-medium">
                            Initial Status *
                        </label>

                        <select
                            v-model="form.status"
                            class="w-full rounded-lg border border-slate-300 px-3 py-3"
                        >
                            <option value="draft">Draft</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>

                    <div class="mt-5">
                        <label class="mb-2 block text-sm font-medium">
                            Priority *
                        </label>

                        <select
                            v-model="form.priority"
                            class="w-full rounded-lg border border-slate-300 px-3 py-3"
                        >
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <p class="mt-4 text-sm text-slate-500">
                        Employee assignment is not included because your
                        current frontend has no confirmed users API endpoint.
                    </p>
                </div>

                <!-- Step 5: Review -->
                <div v-else>
                    <h2 class="text-lg font-semibold">
                        5. Review & Submit
                    </h2>

                    <div class="mt-5 space-y-3 text-sm">
                        <p>
                            <strong>Customer:</strong>
                            {{ selectedCustomer?.name ?? 'Not selected' }}
                        </p>

                        <p>
                            <strong>Status:</strong>
                            {{ form.status }}
                        </p>

                        <p>
                            <strong>Priority:</strong>
                            {{ form.priority }}
                        </p>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b text-slate-500">
                                        <th class="py-3">Product</th>
                                        <th class="py-3">Quantity</th>
                                        <th class="py-3">Unit Price</th>
                                        <th class="py-3">Line Total</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="(item, index) in selectedProducts"
                                        :key="index"
                                        class="border-b"
                                    >
                                        <td class="py-3">
                                            {{ item.product?.name ?? 'Unknown' }}
                                        </td>
                                        <td class="py-3">
                                            {{ item.quantity }}
                                        </td>
                                        <td class="py-3">
                                            {{ formatCurrency(item.unit_price) }}
                                        </td>
                                        <td class="py-3">
                                            {{ formatCurrency(item.lineTotal) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="space-y-2 border-t pt-4 text-right">
                            <p>Subtotal: {{ formatCurrency(subtotal) }}</p>
                            <p>Discount: {{ formatCurrency(discountAmount) }}</p>
                            <p>Tax: {{ formatCurrency(taxAmount) }}</p>
                            <p class="text-lg font-bold">
                                Total: {{ formatCurrency(total) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex flex-wrap justify-between gap-3 border-t pt-5">
                    <button
                        type="button"
                        @click="previousStep"
                        :disabled="step === 1 || saving"
                        class="rounded-lg border px-5 py-2.5 text-sm font-medium disabled:opacity-40"
                    >
                        Previous
                    </button>

                    <button
                        v-if="step < steps.length"
                        type="button"
                        @click="nextStep"
                        class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Continue
                    </button>

                    <button
                        v-else
                        type="button"
                        @click="submitOrder"
                        :disabled="saving"
                        class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ saving ? 'Creating Order...' : 'Create Order' }}
                    </button>
                </div>
            </section>
        </template>
    </div>
</template>
