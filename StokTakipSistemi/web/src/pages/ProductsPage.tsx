import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { ApiError, api } from '../api'
import { useAuth } from '../auth'
import type { Product } from '../types'

export function ProductsPage() {
  const { isAdmin } = useAuth()
  const [products, setProducts] = useState<Product[]>([])
  const [search, setSearch] = useState('')
  const [lowOnly, setLowOnly] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [showForm, setShowForm] = useState(false)

  const load = useCallback(() => {
    api<{ data: Product[] }>('/products', { query: { search, low_stock: lowOnly || undefined } })
      .then((res) => setProducts(res.data))
      .catch((err) => setError(err.message))
  }, [search, lowOnly])

  useEffect(load, [load])

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl font-bold">Ürünler</h1>
        <div className="flex flex-wrap items-center gap-2">
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Ürün adı veya barkod ara…"
            className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-emerald-500 focus:outline-none"
          />
          <label className="flex items-center gap-1.5 text-sm text-gray-600">
            <input type="checkbox" checked={lowOnly} onChange={(event) => setLowOnly(event.target.checked)} />
            Yalnızca kritik stok
          </label>
          {isAdmin && (
            <button
              onClick={() => setShowForm((value) => !value)}
              className="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
            >
              {showForm ? 'Vazgeç' : '+ Yeni Ürün'}
            </button>
          )}
        </div>
      </div>

      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</div>}
      {showForm && isAdmin && <ProductForm onDone={() => { setShowForm(false); load() }} />}

      <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th className="px-4 py-3">Ürün</th>
              <th className="px-4 py-3">Barkod</th>
              <th className="px-4 py-3 text-right">Stok</th>
              <th className="px-4 py-3 text-right">Kritik</th>
              <th className="px-4 py-3 text-right">Parti</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {products.map((product) => (
              <tr key={product.id} className="hover:bg-gray-50">
                <td className="px-4 py-3">
                  <Link to={`/urunler/${product.id}`} className="font-medium text-emerald-700 hover:underline">
                    {product.name}
                  </Link>
                  {product.is_below_critical_stock && (
                    <span className="ml-2 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
                      kritik
                    </span>
                  )}
                </td>
                <td className="px-4 py-3 text-gray-500">{product.barcode ?? '—'}</td>
                <td className="px-4 py-3 text-right font-semibold">
                  {product.stock_quantity} {product.unit}
                </td>
                <td className="px-4 py-3 text-right text-gray-500">{product.critical_stock_level}</td>
                <td className="px-4 py-3 text-right text-gray-500">{product.batches_count ?? '—'}</td>
              </tr>
            ))}
            {products.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-gray-500">
                  Ürün bulunamadı.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}

function ProductForm({ onDone }: { onDone: () => void }) {
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setBusy(true)
    const data = new FormData(event.currentTarget)
    try {
      await api('/products', {
        method: 'POST',
        json: {
          name: String(data.get('name')),
          barcode: String(data.get('barcode') || '') || undefined,
          unit: String(data.get('unit') || 'adet'),
          critical_stock_level: Number(data.get('critical_stock_level') || 0),
        },
      })
      onDone()
    } catch (err) {
      setError(err instanceof ApiError ? Object.values(err.errors)[0]?.[0] ?? err.message : 'Ürün eklenemedi.')
    } finally {
      setBusy(false)
    }
  }

  const input =
    'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none'

  return (
    <form onSubmit={onSubmit} className="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 sm:grid-cols-5">
      {error && <div className="sm:col-span-5 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
      <label className="text-sm sm:col-span-2">
        <span className="mb-1 block font-medium">Ürün adı</span>
        <input name="name" required className={input} />
      </label>
      <label className="text-sm">
        <span className="mb-1 block font-medium">Barkod</span>
        <input name="barcode" className={input} />
      </label>
      <label className="text-sm">
        <span className="mb-1 block font-medium">Birim</span>
        <select name="unit" className={input} defaultValue="adet">
          {['adet', 'kg', 'lt', 'pk', 'çuval'].map((unit) => (
            <option key={unit}>{unit}</option>
          ))}
        </select>
      </label>
      <label className="text-sm">
        <span className="mb-1 block font-medium">Kritik stok</span>
        <input name="critical_stock_level" type="number" min="0" step="0.001" defaultValue={0} className={input} />
      </label>
      <button
        type="submit"
        disabled={busy}
        className="sm:col-span-5 rounded-lg bg-emerald-600 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
      >
        {busy ? 'Kaydediliyor…' : 'Ürünü Kaydet'}
      </button>
    </form>
  )
}
