import { createContext, useCallback, useContext, useState, type ReactNode } from 'react'
import { api, cachedUser, clearSession, getToken, setSession } from './api'
import type { User } from './types'

interface AuthState {
  user: User | null
  isAdmin: boolean
  login: (login: string, password: string) => Promise<void>
  register: (payload: RegisterPayload) => Promise<void>
  logout: () => Promise<void>
}

export interface RegisterPayload {
  company_name: string
  name: string
  email: string
  username?: string
  password: string
}

const AuthContext = createContext<AuthState | null>(null)

interface AuthResponse {
  token: string
  user: User
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(() => (getToken() ? cachedUser<User>() : null))

  const login = useCallback(async (loginName: string, password: string) => {
    const data = await api<AuthResponse>('/auth/login', {
      method: 'POST',
      json: { login: loginName, password },
    })
    setSession(data.token, data.user)
    setUser(data.user)
  }, [])

  const register = useCallback(async (payload: RegisterPayload) => {
    const data = await api<AuthResponse>('/auth/register', { method: 'POST', json: payload })
    setSession(data.token, data.user)
    setUser(data.user)
  }, [])

  const logout = useCallback(async () => {
    try {
      await api('/auth/logout', { method: 'POST' })
    } catch {
      // token zaten geçersiz olabilir; oturumu yine de temizle
    }
    clearSession()
    setUser(null)
  }, [])

  return (
    <AuthContext.Provider
      value={{ user, isAdmin: user?.role === 'admin', login, register, logout }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth, AuthProvider içinde kullanılmalı')
  return ctx
}
