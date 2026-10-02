// Basit fetch istemcisi: Bearer token, JSON ve multipart desteği

const API_URL = import.meta.env.VITE_API_URL ?? '/api/v1'
const TOKEN_KEY = 'stoktakip_token'
const USER_KEY = 'stoktakip_user'

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setSession(token: string, user: unknown): void {
  localStorage.setItem(TOKEN_KEY, token)
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

export function clearSession(): void {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
}

export function cachedUser<T>(): T | null {
  const raw = localStorage.getItem(USER_KEY)
  try {
    return raw ? (JSON.parse(raw) as T) : null
  } catch {
    return null
  }
}

interface ApiOptions {
  method?: string
  json?: unknown
  form?: FormData
  query?: Record<string, string | number | boolean | undefined>
}

export async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const { method = 'GET', json, form, query } = options

  const url = new URL(API_URL + path, window.location.origin)
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value !== undefined && value !== '') url.searchParams.set(key, String(value))
    }
  }

  const headers: Record<string, string> = { Accept: 'application/json' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`
  if (json !== undefined) headers['Content-Type'] = 'application/json'

  // url.toString(): API_URL tam adres ise ona, göreliyse (dev proxy) SPA köküne gider
  const response = await fetch(url.toString(), {
    method,
    headers,
    body: json !== undefined ? JSON.stringify(json) : form,
  })

  const isJson = response.headers.get('content-type')?.includes('json')
  const body = isJson ? await response.json().catch(() => null) : null

  if (!response.ok) {
    throw new ApiError(
      response.status,
      body?.message ?? `İstek başarısız (${response.status})`,
      body?.errors ?? {},
    )
  }

  return body as T
}
