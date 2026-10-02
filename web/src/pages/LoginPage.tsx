import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ApiError } from '../api'
import { useAuth } from '../auth'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setBusy(true)
    const data = new FormData(event.currentTarget)
    try {
      await login(String(data.get('login')), String(data.get('password')))
      navigate('/')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Giriş yapılamadı.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-sm">
        <h1 className="mb-1 text-center text-2xl font-bold">
          ☕ <span className="text-emerald-700">StokTakip</span>
        </h1>
        <p className="mb-6 text-center text-sm text-gray-500">Stok ve SKT takip sistemine giriş</p>

        <form
          onSubmit={onSubmit}
          className="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
        >
          {error && (
            <div className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>
          )}
          <label className="block text-sm">
            <span className="mb-1 block font-medium">E-posta veya kullanıcı adı</span>
            <input
              name="login"
              required
              autoComplete="username"
              className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-emerald-500 focus:outline-none"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block font-medium">Şifre</span>
            <input
              name="password"
              type="password"
              required
              autoComplete="current-password"
              className="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-emerald-500 focus:outline-none"
            />
          </label>
          <button
            type="submit"
            disabled={busy}
            className="w-full rounded-lg bg-emerald-600 py-2 font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
          >
            {busy ? 'Giriş yapılıyor…' : 'Giriş Yap'}
          </button>
          <p className="text-center text-sm text-gray-500">
            Hesabınız yok mu?{' '}
            <Link to="/kayit" className="font-medium text-emerald-700 hover:underline">
              İşletme kaydı açın
            </Link>
          </p>
        </form>
      </div>
    </div>
  )
}
