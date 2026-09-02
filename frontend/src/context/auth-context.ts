import { createContext, useContext } from 'react'
import type { User } from '../types/api'

export interface AuthContextValue {
  user: User | null
  isLoading: boolean
  login: (payload: { email: string; password: string; remember?: boolean }) => Promise<User>
  register: (payload: Record<string, unknown>) => Promise<User>
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth() {
  const value = useContext(AuthContext)
  if (!value) throw new Error('useAuth must be used inside AuthProvider')
  return value
}
