import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

import Login from '../pages/Login.vue'
import Dashboard from '../pages/Dashboard.vue'
import Orders from '../pages/Order.vue'
import Customers from '../pages/Customers.vue'
import Products from '../pages/Products.vue'
import AppLayout from '../layouts/AppLayout.vue'
import OrderDetails from '../pages/OrderDetails.vue'
import CreateOrder from '../pages/CreateOrder.vue'
import UserManagement from '../pages/UserManagement.vue'
import RoleManagement from '../pages/Role.vue'
import PermissionManagement from '../pages/Permission.vue'
import AuditLogs from '../pages/AuditLogs.vue'
import Forbidden from '../pages/Forbidden.vue'

const routes = [
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: { guestOnly: true },
    },
    {
        path: '/',
        component: AppLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: '/dashboard',
            },
            {
                path: 'forbidden',
                name: 'forbidden',
                component: Forbidden,
            },
            {
                path: 'dashboard',
                name: 'dashboard',
                component: Dashboard,
                meta: { permission: 'dashboard.view' },
            },
            {
                path: 'admin/users',
                name: 'admin-users',
                component: UserManagement,
                meta: { permission: 'users.manage' },
            },
            {
                path: 'admin/roles',
                name: 'admin-roles',
                component: RoleManagement,
                meta: { permission: 'roles.manage' },
            },
            {
                path: 'admin/permissions',
                name: 'admin-permissions',
                component: PermissionManagement,
                meta: { permission: 'permissions.manage' },
            },
            {
                path: 'orders',
                name: 'orders',
                component: Orders,
                meta: {
                    permissions: [
                        'orders.view_all',
                        'orders.view_assigned',
                    ],
                    permissionMode: 'any',
                },
            },
            {
                path: 'orders/:id',
                name: 'order-details',
                component: OrderDetails,
                meta: {
                    permissions: [
                        'orders.view_all',
                        'orders.view_assigned',
                    ],
                    permissionMode: 'any',
                },
            },
            {
                path: 'orders/create',
                name: 'create-order',
                component: CreateOrder,
                meta: { permission: 'orders.create' },
            },
            {
                path: 'customers',
                name: 'customers',
                component: Customers,
                meta: { permission: 'customers.view' },
            },
            {
                path: 'products',
                name: 'products',
                component: Products,
                meta: { permission: 'products.view' },
            },
            {
                path: 'admin/audit-logs',
                name: 'admin-audit-logs',
                component: AuditLogs,
                meta: { permission: 'audit_logs.view' },
            },
        ],
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})


// router.beforeEach(async (to) => {
//     const authStore = useAuthStore()

//     // Restore the user session after a page refresh.
//     if (!authStore.initialized) {
//         await authStore.fetchUser()
//     }

//     if (to.meta.requiresAuth && !authStore.isAuthenticated) {
//         return { name: 'login' }
//     }
//     if (to.meta.roles && authStore.isAuthenticated) {
//         const role = authStore.user?.role?.name
//         if (!to.meta.roles.includes(role)) {
//             return { name: 'orders' }
//         }
//     }

//     if (to.meta.guestOnly && authStore.isAuthenticated) {
//         return { name: 'dashboard' }
//     }
//     return true
// })


router.beforeEach(async (to) => {
    const authStore = useAuthStore()

    // Restore authentication and permissions after a page refresh.
    if (!authStore.initialized) {
        await authStore.fetchUser()
    }

    // Authentication check.
    if (to.matched.some((record) => record.meta.requiresAuth)
        && !authStore.isAuthenticated) {
        return { name: 'login' }
    }

    // Redirect authenticated users away from the login page.
    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return { name: 'dashboard' }
    }

    // Single permission check.
    if (
        to.meta.permission &&
        !authStore.hasPermission(to.meta.permission)
    ) {
        return { name: 'forbidden' }
    }

    // Multiple permissions: require any or all.
    if (to.meta.permissions?.length) {
        const allowed = to.meta.permissionMode === 'all'
            ? authStore.hasAllPermissions(to.meta.permissions)
            : authStore.hasAnyPermission(to.meta.permissions)

        if (!allowed) {
            return { name: 'forbidden' }
        }
    }

    return true
})


export default router;