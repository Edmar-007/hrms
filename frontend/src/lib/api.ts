import axios from 'axios'

// Request interceptor for FormData
const api = axios.create({
  baseURL: import.meta.env.MODE === 'development' ? '/api' : '/hrms/api',
  withCredentials: true,
})

api.interceptors.request.use((config) => {
  // Don't stringify FormData
  if (config.data instanceof FormData) {
    config.headers['Content-Type'] = 'multipart/form-data'
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const requestUrl = String(error.config?.url ?? '')
      const isAuthProbeRequest =
        requestUrl.includes('/me.php') || requestUrl.includes('/login.php')
      const isOnLoginRoute =
        window.location.pathname === '/login' ||
        window.location.pathname.endsWith('/hrms/login')

      if (!isAuthProbeRequest && !isOnLoginRoute) {
        window.location.href = import.meta.env.DEV ? '/login' : '/hrms/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api

