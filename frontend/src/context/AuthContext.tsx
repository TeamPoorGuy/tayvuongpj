import { useQuery, useQueryClient } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { api, initializeCsrf } from '../api/client'
import type { ApiEnvelope, User } from '../types/api'
import { AuthContext } from './auth-context'

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const { data: user = null, isLoading } = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: async () => {
      try {
        return (await api.get<ApiEnvelope<User>>('/auth/me')).data.data
      } catch {
        return null
      }
    },
    retry: false,
  })

  const authenticate = async (path: string, payload: Record<string, unknown>) => {
    await initializeCsrf()
    const nextUser = (await api.post<ApiEnvelope<User>>(path, payload)).data.data
    queryClient.setQueryData(['auth', 'me'], nextUser)
    return nextUser
  }

  const logout = async () => {
    await initializeCsrf()
    await api.post('/auth/logout')
    queryClient.setQueryData(['auth', 'me'], null)
    queryClient.clear()
  }

  return <AuthContext.Provider value={{ user, isLoading, login: (payload) => authenticate('/auth/login', payload), register: (payload) => authenticate('/auth/register', payload), logout }}>{children}</AuthContext.Provider>
}
