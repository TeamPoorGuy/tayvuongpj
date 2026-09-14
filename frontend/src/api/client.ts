import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  headers: { Accept: 'application/json' },
  withCredentials: true,
  withXSRFToken: true,
})

export async function initializeCsrf(): Promise<void> {
  await axios.get(import.meta.env.VITE_CSRF_URL ?? '/sanctum/csrf-cookie', {
    withCredentials: true,
  })
}

const MUTATING_METHODS = new Set(['post', 'put', 'patch', 'delete'])
let csrfPrimed = false

api.interceptors.request.use(async (config) => {
  if (!csrfPrimed && config.method && MUTATING_METHODS.has(config.method)) {
    await initializeCsrf()
    csrfPrimed = true
  }
  return config
})

/** Laravel multipart method-spoofing: PUT/PATCH via FormData must go through POST + _method. */
export function withMethodOverride(data: FormData, method: 'PUT' | 'PATCH'): FormData {
  data.append('_method', method)
  return data
}
