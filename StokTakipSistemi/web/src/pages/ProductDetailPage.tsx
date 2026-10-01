import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { useParams } from 'react-router-dom'
import { ApiError, api } from '../api'
import { useAuth } from '../auth'
import type { Product } from '../types'

export function ProductDetailPage() {
  const { id } = useParams<{ id: string }>()
  const { isAdmin } = useAuth()
  const [product, setProduct] = useState<Product | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(() => {
    api<{ data: Product }>(`/products/${id}`)
      .then((res) => setProduct(res.data))
      .catch((err) => setError(err.message))
  }, [id])

  useEffect(load, [load])

  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-700">{error}</div>
  if (!product) return <p className="text-gray-500">Yükleniyor…</p>

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold">
            {product.name}
            {product.is_below_critical_stock && (
              <span className="ml-2 rounded-full bg-orange-100 px-2 py-0.5 align-middle text-xs font-medium text-orange-700">
                kritik stok
              </span>
            )}
          </h1>
          <p className="text-sm text-gray-500">
            {product.barcode ? `Barkod: ${product.barcode} · ` : ''}
            Birim: {product.unit} · Kritik seviye: {product.critical_stock_level}
          </p>
        </div>
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-right">
          <div className="text-2xl font-bold text-emerald-700">
            {product.stock_quantity} <span className="text-sm font-medium">{product.unit}</span>
          </div>
          <div className="text-xs text-emerald-600">güncel stok</div>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        {isAdmin && <StockInForm productId={product.id} onDone={load} />}
        <StockOutForm product={product} onDone={load} />
      </div>

      <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <h2 className="border-b border-gray-100 px-4 py-3 font-semibold">Partiler (SKT sıralı)</h2>
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th className="px-4 py-3">Parti / Lot</th>
              <th className="px-4 py-3">SKT</th>
              <th className="px-4 py-3 text-right">Giriş</th>
              <th className="px-4 py-3 text-right">Kalan</th>
              <th className="px-4 py-3">Tedarikçi</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(product.batches ?? []).map((batch) => (
              <tr key={batch.id}>
                <td className="px-4 py-3 font-medium">{batch.batch_code ?? `#${batch.id}`}</td>
                <td className="px-4 py-3">
                  <span className={batch.is_expired ? 'font-semibold text-red-600' : batch.days_until_expiry !== null && batch.days_until_expiry <= 30 ? 'font-semibold text-amber-600' : ''}>
                    {batch.expiry_date ?? '—'}
                  </span>
                  {batch.days_until_expiry !== null && (
                    <span className="ml-2 text-xs text-gray-400">
                      {batch.is_expired ? 'geçti' : `${batch.days_until_expiry} gün`}
                    </span>
                  )}
                </td>
                <td className="px-4 py-3 text-right text-gray-500">{batch.quantity}</td>
                <td className="px-4 py-3 text-right font-semibold">{batch.remaining_quantity}</td>
                <td className="px-4 py-3 text-gray-500">{batch.supplier_name ?? '—'}</td>
              </tr>
            ))}
            {(product.batches ?? []).length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-gray-500">
                  Henüz parti girişi yok.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </section>
    </div>
  )
}

const input =
  'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none'

function StockInForm({ productId, onDone }: { productId: number; onDone: () => void }) {
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null)
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setMessage(null)
    setBusy(true)
    const data = new FormData(event.currentTarget)
    const form = event.currentTarget
    try {
      await api('/batches', {
        method: 'POST',
        json: {
          product_id: productId,
          quantity: Number(data.get('quantity')),
          expiry_date: String(data.get('expiry_date') || '') || undefined,
          batch_code: String(data.get('batch_code') || '') || undefined,
          supplier_name: String(data.get('supplier_name') || '') || undefined,
          waybill_number: String(data.get('waybill_number') || '') || undefined,
        },
      })
      setMessage({ ok: true, text: 'Stok girişi kaydedildi.' })
      form.reset()
      onDone()
    } catch (err) {
      setMessage({
        ok: false,
        text: err instanceof ApiError ? Object.values(err.errors)[0]?.[0] ?? err.message : 'Giriş yapılamadı.',
      })
    } finally {
      setBusy(false)
    }
  }

  return (
    <form onSubmit={onSubmit} className="space-y-3 rounded-2xl border border-gray-200 bg-white p-4">
      <h2 className="font-semibold text-emerald-700">📥 Stok Girişi (Parti)</h2>
      {message && (
        <div className={`rounded-lg px-3 py-2 text-sm ${message.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`}>
          {message.text}
        </div>
      )}
      <div className="grid grid-cols-2 gap-3">
        <label className="text-sm">
          <span className="mb-1 block font-medium">Miktar</span>
          <input name="quantity" type="number" min="0.001" step="0.001" required className={input} />
        </label>
        <label className="text-sm">
          <span className="mb-1 block font-medium">SKT</span>
          <input name="expiry_date" type="date" className={input} />
        </label>
        <label className="text-sm">
          <span className="mb-1 block font-medium">Lot / Parti no</span>
          <input name="batch_code" className={input} />
        </label>
        <label className="text-sm">
          <span className="mb-1 block font-medium">İrsaliye no</span>
          <input name="waybill_number" className={input} />
        </label>
        <label className="col-span-2 text-sm">
          <span className="mb-1 block font-medium">Tedarikçi</span>
          <input name="supplier_name" className={input} />
        </label>
      </div>
      <button disabled={busy} className="w-full rounded-lg bg-emerald-600 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
        {busy ? 'Kaydediliyor…' : 'Partiyi Gir'}
      </button>
    </form>
  )
}

function StockOutForm({ product, onDone }: { product: Product; onDone: () => void }) {
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null)
  const [busy, setBusy] = useState(false)

  const openBatches = (product.batches ?? []).filter((batch) => batch.remaining_quantity > 0)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setMessage(null)
    setBusy(true)
    const form = event.currentTarget
    const data = new FormData(form)
    try {
      const result = await api<{ data: unknown[] }>('/stock-movements', {
        method: 'POST',
        json: {
          product_id: product.id,
          type: 'out',
          quantity: Number(data.get('quantity')),
          batch_id: String(data.get('batch_id') || '') || undefined,
          note: String(data.get('note') || '') || undefined,
        },
      })
      setMessage({ ok: true, text: `Stok çıkışı yapıldı (${result.data.length} parti hareketi).` })
      form.reset()
      onDone()
    } catch (err) {
      setMessage({
        ok: false,
        text: err instanceof ApiError ? Object.values(err.errors)[0]?.[0] ?? err.message : 'Çıkış yapılamadı.',
      })
    } finally {
      setBusy(false)
    }
  }

  return (
    <form onSubmit={onSubmit} className="space-y-3 rounded-2xl border border-gray-200 bg-white p-4">
      <h2 className="font-semibold text-rose-600">📤 Stok Çıkışı (FIFO)</h2>
      <p className="text-xs text-gray-500">
        Parti seçmezseniz SKT'si en yakın partiden otomatik düşülür.
      </p>
      {message && (
        <div className={`rounded-lg px-3 py-2 text-sm ${message.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'}`}>
          {message.text}
        </div>
      )}
      <div className="grid grid-cols-2 gap-3">
        <label className="text-sm">
          <span className="mb-1 block font-medium">Miktar</span>
          <input name="quantity" type="number" min="0.001" step="0.001" required className={input} />
        </label>
        <label className="text-sm">
          <span className="mb-1 block font-medium">Parti (opsiyonel)</span>
          <select name="batch_id" className={input} defaultValue="">
            <option value="">Otomatik (FIFO)</option>
            {openBatches.map((batch) => (
              <option key={batch.id} value={batch.id}>
                {batch.batch_code ?? `#${batch.id}`} · {batch.expiry_date ?? 'SKT yok'} · {batch.remaining_quantity} kaldı
              </option>
            ))}
          </select>
        </label>
        <label className="col-span-2 text-sm">
          <span className="mb-1 block font-medium">Not</span>
          <input name="note" className={input} placeholder="ör. sabah vardiyası tüketimi" />
        </label>
      </div>
      <button disabled={busy} className="w-full rounded-lg bg-rose-600 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-50">
        {busy ? 'İşleniyor…' : 'Stok Düş'}
      </button>
    </form>
  )
}
