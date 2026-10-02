import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ApiError } from '../api'
import { useAuth } from '../auth'

function fieldError(errors: Record<string, string[]>, key: string): string | undefined {
  return errors[key]?.[0]
}

export function RegisterPage() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setErrors({})
    setBusy(true)
    const data = new FormData(event.currentTarget)
    try {
      await register({
        company_name: String(data.get('company_name')),
        name: String(data.get('name')),
        email: String(data.get('email')),
        username: String(data.get('username') || '') || undefined,
        password: String(data.get('password')),
      })
      navigate('/')
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message)
        setErrors(err.errors)
      } else {
        setError('Kayıt oluşturulamadı.')
      }
    } finally {
      setBusy(false)
    }
  }

  const input =
    'w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-emerald-500 focus:outline-none'
  const label = 'mb-1 block font-medium'

  return (
    <div className="flex min-h-screen items-center justify-center px-4 py-8">
      <div className="w-full max-w-md">
        <h1 className="mb-1 text-center text-2xl font-bold">
          ☕ <span className="text-emerald-700">StokTakip</span>
        </h1>
        <p className="mb-6 text-center text-sm text-gray-500">
          İşletmenizi kaydedin; yönetici hesabı sizin olacak
        </p>

        <form
          onSubmit={onSubmit}
          className="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
        >
          {error && (
            <div className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>
          )}
          <label className="block text-sm">
            <span className={label}>Şirket / Dükkan Adı</span>
            <input name="company_name" required className={input} />
            {fieldError(errors, 'company_name') && (
              <span className="text-xs text-red-600">{fieldError(errors, 'company_name')}</span>
            )}
          </label>
          <div className="grid grid-cols-2 gap-3">
            <label className="block text-sm">
              <span className={label}>Ad Soyad</span>
              <input name="name" required className={input} />
            </label>
            <label className="block text-sm">
              <span className={label}>Kullanıcı Adı (opsiyonel)</span>
              <input name="username" className={input} />
            </label>
          </div>
          <label className="block text-sm">
            <span className={label}>E-posta</span>
            <input name="email" type="email" required className={input} />
            {fieldError(errors, 'email') && (
              <span className="text-xs text-red-600">{fieldError(errors, 'email')}</span>
            )}
          </label>
          <label className="block text-sm">
            <span className={label}>Şifre (en az 8 karakter, harf + rakam)</span>
            <input name="password" type="password" required className={input} />
            {fieldError(errors, 'password') && (
              <span className="text-xs text-red-600">{fieldError(errors, 'password')}</span>
            )}
          </label>
          <button
            type="submit"
            disabled={busy}
            className="w-full rounded-lg bg-emerald-600 py-2 font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
          >
            {busy ? 'Kayıt oluşturuluyor…' : 'İşletmeyi Kaydet'}
          </button>
          <p className="text-center text-sm text-gray-500">
            Zaten hesabınız var mı?{' '}
            <Link to="/login" className="font-medium text-emerald-700 hover:underline">
              Giriş yapın
            </Link>
          </p>
        </form>
      </div>
    </div>
  )
}
