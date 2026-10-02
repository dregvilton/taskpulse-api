import { session } from './session.js'

export class ApiError extends Error {
  constructor(status, message, fields = {}, requestId = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fields = fields
    this.requestId = requestId
  }
}

function errorFromResponse(status, data, requestId) {
  if (Array.isArray(data)) {
    const fields = {}
    for (const item of data) {
      if (item?.field && item?.message) {
        fields[item.field] = item.message
      }
    }
    return new ApiError(status, 'Проверьте заполненные поля.', fields, requestId)
  }

  const fallback = status === 403 ? 'Нет доступа к этому действию.' : 'Не удалось выполнить запрос.'
  return new ApiError(status, data?.message || fallback, {}, requestId)
}

export function createApiClient({
  baseUrl = '',
  getToken = () => null,
  onUnauthorized = () => {},
  transport = (...args) => fetch(...args),
} = {}) {
  const base = baseUrl.replace(/\/+$/, '')

  async function request(path, { method = 'GET', query, body, skipUnauthorized = false } = {}) {
    const params = new URLSearchParams()
    for (const [key, value] of Object.entries(query || {})) {
      if (value !== null && value !== undefined && value !== '') {
        params.set(key, String(value))
      }
    }
    const suffix = params.size ? `?${params}` : ''
    const headers = { Accept: 'application/json' }
    const token = getToken()
    if (token) headers.Authorization = `Bearer ${token}`
    if (body !== undefined) headers['Content-Type'] = 'application/json'

    let response
    try {
      response = await transport(`${base}${path}${suffix}`, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
      })
    } catch {
      throw new ApiError(0, 'Не удалось связаться с API. Проверьте подключение.')
    }

    const requestId = response.headers.get('X-Request-Id')
    const data = response.status === 204 ? null : await response.json().catch(() => null)
    if (!response.ok) {
      if (response.status === 401 && !skipUnauthorized) onUnauthorized()
      throw errorFromResponse(response.status, data, requestId)
    }

    return { data, headers: response.headers }
  }

  return {
    login: async (credentials) =>
      (
        await request('/auth/login', {
          method: 'POST',
          body: credentials,
          skipUnauthorized: true,
        })
      ).data,
    registerUser: async (body) => (await request('/users', { method: 'POST', body })).data,
    logout: async () =>
      (await request('/auth/logout', { method: 'POST', skipUnauthorized: true })).data,
    listTasks: async (query) => {
      const { data, headers } = await request('/tasks', { query })
      return {
        items: data?.items || [],
        pagination: {
          page: Number(headers.get('X-Pagination-Current-Page') || 1),
          pages: Number(headers.get('X-Pagination-Page-Count') || 1),
          total: Number(headers.get('X-Pagination-Total-Count') || 0),
        },
      }
    },
    getTask: async (id) => (await request(`/tasks/${id}`)).data,
    createTask: async (body) => (await request('/tasks', { method: 'POST', body })).data,
    updateTask: async (id, body) => (await request(`/tasks/${id}`, { method: 'PATCH', body })).data,
    deleteTask: async (id) => (await request(`/tasks/${id}`, { method: 'DELETE' })).data,
    listUsers: async () => (await request('/users')).data,
    getAnalytics: async (query) => (await request('/analytics/tasks', { query })).data,
  }
}

let unauthorizedHandler = () => {}

export function setUnauthorizedHandler(handler) {
  unauthorizedHandler = handler
}

export const api = createApiClient({
  baseUrl: import.meta.env.VITE_API_BASE_URL || '',
  getToken: () => session.token,
  onUnauthorized: () => unauthorizedHandler(),
})
