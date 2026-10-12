
<template>
    <div class="p-8 text-center">
        <h1 class="text-2xl font-bold text-red-600">
            403 - Access Forbidden
        </h1>

        <p class="mt-3 text-gray-600">
            You do not have permission to access this page.
        </p>

        <button
            @click="$router.push({ name: 'dashboard' })"
            class="mt-5 rounded bg-blue-600 px-4 py-2 text-white"
        >
            Back to Dashboard
        </button>
    </div>
</template>
