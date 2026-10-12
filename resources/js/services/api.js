

import axios from 'axios'

const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
})

// Attach the token to every API request.
api.interceptors.request.use((config) => {
    const token = sessionStorage.getItem('auth_token')

    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }

    return config
})

export default api
