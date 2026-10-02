import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { ApiError, api } from '../api'
import { useAuth } from '../auth'
import type { User } from '../types'

export function StaffPage() {
  const { user } = useAuth()
  const [staff, setStaff] = useState<User[]>([])
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null)

  const load = useCallback(() => {
    api<{ data: User[] }>('/company/staff')
      .then((res) => setStaff(res.data))
      .catch((err) => setMessage({ ok: false, text: err.message }))
  }, [])

  useEffect(load, [load])

  async function remove(target: User): Promise<void> {
    if (!window.confirm(`${target.name} hesabını silmek istediğinize emin misiniz?`)) return
    try {
      await api(`/company/staff/${target.id}`, { method: 'DELETE' })
      setMessage({ ok: true, text: 'Hesap silindi.' })
      load()
    } catch (err) {
      setMessage({ ok: false, text: err instanceof ApiError ? err.message : 'Silinemedi.' })
    }
  }

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold">Çalışanlar</h1>
      {message && (
        <div className={`rounded-lg px-3 py-2 text-sm ${message.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`}>
          {message.text}
        </div>
      )}

      <StaffForm onDone={load} />

      <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th className="px-4 py-3">Ad</th>
              <th className="px-4 py-3">E-posta</th>
              <th className="px-4 py-3">Kullanıcı adı</th>
              <th className="px-4 py-3">Rol</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {staff.map((member) => (
              <tr key={member.id}>
                <td className="px-4 py-3 font-medium">
                  {member.name}
                  {member.id === user?.id && <span className="ml-2 text-xs text-gray-400">(siz)</span>}
                </td>
                <td className="px-4 py-3 text-gray-500">{member.email}</td>
                <td className="px-4 py-3 text-gray-500">{member.username ?? '—'}</td>
                <td className="px-4 py-3">
                  <span
                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                      member.role === 'admin' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'
                    }`}
                  >
                    {member.role === 'admin' ? 'İşletme Sahibi' : 'Çalışan'}
                  </span>
                </td>
                <td className="px-4 py-3 text-right">
                  {member.id !== user?.id && (
                    <button
                      onClick={() => void remove(member)}
                      className="font-medium text-rose-600 hover:underline"
                    >
                      Sil
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

function StaffForm({ onDone }: { onDone: () => void }) {
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setBusy(true)
    const data = new FormData(event.currentTarget)
    try {
      await api('/company/staff', {
        method: 'POST',
        json: {
          name: String(data.get('name')),
          email: String(data.get('email')),
          username: String(data.get('username') || '') || undefined,
          password: String(data.get('password')),
        },
      })
      event.currentTarget.reset()
      onDone()
    } catch (err) {
      setError(err instanceof ApiError ? Object.values(err.errors)[0]?.[0] ?? err.message : 'Çalışan eklenemedi.')
    } finally {
      setBusy(false)
    }
  }

  const input =
    'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none'

  return (
    <form onSubmit={onSubmit} className="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 sm:grid-cols-5">
      {error && <div className="sm:col-span-5 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
      <label className="text-sm">
        <span className="mb-1 block font-medium">Ad Soyad</span>
        <input name="name" required className={input} />
      </label>
      <label className="text-sm">
        <span className="mb-1 block font-medium">E-posta</span>
        <input name="email" type="email" required className={input} />
      </label>
      <label className="text-sm">
        <span className="mb-1 block font-medium">Kullanıcı adı</span>
        <input name="username" className={input} />
      </label>
      <label className="text-sm">
        <span className="mb-1 block font-medium">Başlangıç şifresi</span>
        <input name="password" type="password" required minLength={6} className={input} />
      </label>
      <button
        type="submit"
        disabled={busy}
        className="self-end rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
      >
        {busy ? 'Ekleniyor…' : 'Çalışan Ekle'}
      </button>
    </form>
  )
}
