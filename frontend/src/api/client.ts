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
