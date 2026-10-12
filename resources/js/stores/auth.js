
import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'
import api from '../services/api'

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null)
    const isAuthenticated = ref(false)
    const initialized = ref(false)

   
async function fetchUser() {
    try {
        const response = await api.get('/user')

        console.log('User response:', response.data)

        user.value = response.data.data.user
        isAuthenticated.value = true

        console.log('Authenticated:', isAuthenticated.value)
        console.log('User role:', user.value.role?.name)

        return user.value
    } catch (error) {
        console.error(
            'Fetch user failed:',
            error.response?.status,
            error.response?.data || error.message
        )

        user.value = null
        isAuthenticated.value = false

        return null
    } finally {
        initialized.value = true
    }
}


    // async function login(credentials) {
    //     await axios.get('/sanctum/csrf-cookie', {
    //         withCredentials: true,
    //         withXSRFToken: true,
    //     })

    //     await axios.post('/api/login', credentials, {
    //         withCredentials: true,
    //         withXSRFToken: true,
    //         headers: { Accept: 'application/json' },
    //     })

    //     await fetchUser()

    //     if (!isAuthenticated.value) {
    //         throw new Error('Unable to load authenticated user.')
    //     }
    // }
    
async function login(credentials) {
    const response = await api.post('/login', credentials)
    console.log('Login response:', response.data)
    const token = response.data.data.user?.token
    if (!token) {
        throw new Error('Login response did not contain an authentication token.')
    }
    sessionStorage.setItem('auth_token', token)
    await fetchUser()
    if (!isAuthenticated.value) {
        sessionStorage.removeItem('auth_token')
        throw new Error('Authentication verification failed.')
    }
}


  
async function logout() {
    const token = sessionStorage.getItem('auth_token')

    try {
        await api.post('/logout', {}, {
            headers: {
                Accept: 'application/json',
                Authorization: token ? `Bearer ${token}` : '',
            },
        })
    } catch (error) {
        console.error(
            'Logout failed:',
            error.response?.status,
            error.response?.data
        )
    } finally {
        sessionStorage.removeItem('auth_token')

        user.value = null
        isAuthenticated.value = false
        initialized.value = true
    }
}
// function getPermissionNames(user) {
//     const permissions =
//         user?.permissions ??
//         user?.role?.permissions ??
//         []

//     return permissions.map((permission) =>
//         typeof permission === 'string'
//             ? permission
//             : permission.name
//     ).filter(Boolean)
// }

// function hasPermission(permission) {
//     return getPermissionNames(this.user).includes(permission)
// }

// function hasAnyPermission(permissions = []) {
//     return permissions.some((permission) =>
//         hasPermission.call(this, permission)
//     )
// }

// function hasAllPermissions(permissions = []) {
//     return permissions.every((permission) =>
//         hasPermission.call(this, permission)
//     )
// }
function getPermissionNames(user) {
    const permissions = user?.role?.permissions ?? user?.permissions ?? []

    return permissions
        .map(permission =>
            typeof permission === 'string'
                ? permission
                : permission.name
        )
        .filter(Boolean)
}

function hasPermission(permission) {
    return getPermissionNames(this.user).includes(permission)
}

function hasAnyPermission(permissions = []) {
    return permissions.some(permission =>hasPermission.call(this, permission))
}
function hasAllPermissions(permissions = []) {
    return permissions.every(permission =>
        hasPermission(permission)
    )
}


    return {
        user,
        isAuthenticated,
        initialized,
        fetchUser,
        login,
        logout,
        getPermissionNames,
        hasPermission,
        hasAnyPermission,
        hasAllPermissions
    }
})
