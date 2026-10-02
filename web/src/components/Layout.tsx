import { NavLink, Navigate, Outlet, useNavigate } from 'react-router-dom'
import type { ReactNode } from 'react'
import { useAuth } from '../auth'

export function RequireAuth({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  if (!user) return <Navigate to="/login" replace />
  return <>{children}</>
}

export function RequireAdmin({ children }: { children: ReactNode }) {
  const { user, isAdmin } = useAuth()
  if (!user) return <Navigate to="/login" replace />
  if (!isAdmin) {
    return (
      <div className="p-8 text-center text-gray-500">
        Bu sayfa için yönetici yetkisi gerekir.
      </div>
    )
  }
  return <>{children}</>
}

const linkClass = ({ isActive }: { isActive: boolean }) =>
  `rounded-lg px-3 py-1.5 text-sm font-medium transition ${
    isActive ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-100'
  }`

export function Layout() {
  const { user, isAdmin, logout } = useAuth()
  const navigate = useNavigate()

  return (
    <div className="min-h-screen">
      <header className="border-b border-gray-200 bg-white">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-3">
          <span className="text-lg font-bold text-emerald-700">☕ StokTakip</span>
          {user?.company && (
            <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
              {user.company.name}
            </span>
          )}
          <nav className="flex flex-1 flex-wrap items-center gap-1">
            <NavLink to="/" end className={linkClass}>Panel</NavLink>
            <NavLink to="/urunler" className={linkClass}>Ürünler</NavLink>
            {isAdmin && <NavLink to="/irsaliye" className={linkClass}>İrsaliye Yükle</NavLink>}
            {isAdmin && <NavLink to="/calisanlar" className={linkClass}>Çalışanlar</NavLink>}
          </nav>
          <div className="flex items-center gap-3">
            <div className="text-right text-xs leading-tight">
              <div className="font-semibold">{user?.name}</div>
              <div className="text-gray-500">{isAdmin ? 'İşletme Sahibi' : 'Çalışan'}</div>
            </div>
            <button
              onClick={() => void logout().then(() => navigate('/login'))}
              className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            >
              Çıkış
            </button>
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-6xl px-4 py-6">
        <Outlet />
      </main>
    </div>
  )
}
