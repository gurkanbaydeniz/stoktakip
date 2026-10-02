import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api'
import type { Alerts } from '../types'

export function DashboardPage() {
  const [alerts, setAlerts] = useState<Alerts | null>(null)
  const [days, setDays] = useState(30)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api<{ data: Alerts }>('/alerts', { query: { days } })
      .then((res) => setAlerts(res.data))
      .catch((err) => setError(err.message))
  }, [days])

  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-700">{error}</div>
  if (!alerts) return <p className="text-gray-500">Yükleniyor…</p>

  const cards = [
    {
      title: 'Yaklaşan SKT',
      count: alerts.expiring_batches.length,
      tone: 'bg-amber-50 text-amber-800 border-amber-200',
    },
    {
      title: 'Geçmiş SKT',
      count: alerts.expired_batches.length,
      tone: 'bg-red-50 text-red-800 border-red-200',
    },
    {
      title: 'Kritik Stok',
      count: alerts.low_stock_products.length,
      tone: 'bg-orange-50 text-orange-800 border-orange-200',
    },
  ]

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-xl font-bold">Uyarı Paneli</h1>
        <label className="flex items-center gap-2 text-sm text-gray-600">
          SKT penceresi:
          <select
            value={days}
            onChange={(event) => setDays(Number(event.target.value))}
            className="rounded-lg border border-gray-300 px-2 py-1"
          >
            {[7, 15, 30, 60, 90].map((d) => (
              <option key={d} value={d}>{d} gün</option>
            ))}
          </select>
        </label>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        {cards.map((card) => (
          <div key={card.title} className={`rounded-2xl border p-5 ${card.tone}`}>
            <div className="text-3xl font-bold">{card.count}</div>
            <div className="mt-1 text-sm font-medium">{card.title}</div>
          </div>
        ))}
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <section className="rounded-2xl border border-gray-200 bg-white p-5">
          <h2 className="mb-3 font-semibold">⏳ Yaklaşan / Geçmiş SKT'li Partiler</h2>
          <BatchList batches={[...alerts.expired_batches, ...alerts.expiring_batches]} emptyText="Pencere içinde SKT'si dolan parti yok." />
        </section>

        <section className="rounded-2xl border border-gray-200 bg-white p-5">
          <h2 className="mb-3 font-semibold">📉 Kritik Seviye Altındaki Ürünler</h2>
          {alerts.low_stock_products.length === 0 ? (
            <p className="text-sm text-gray-500">Tüm ürünler kritik seviyenin üzerinde.</p>
          ) : (
            <ul className="divide-y divide-gray-100">
              {alerts.low_stock_products.map((product) => (
                <li key={product.id} className="flex items-center justify-between py-2 text-sm">
                  <Link to={`/urunler/${product.id}`} className="font-medium text-emerald-700 hover:underline">
                    {product.name}
                  </Link>
                  <span className="text-gray-600">
                    {product.stock_quantity} / kritik {product.critical_stock_level} {product.unit}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  )
}

function BatchList({ batches, emptyText }: { batches: Alerts['expiring_batches']; emptyText: string }) {
  if (batches.length === 0) return <p className="text-sm text-gray-500">{emptyText}</p>
  return (
    <ul className="divide-y divide-gray-100">
      {batches.map((batch) => (
        <li key={batch.id} className="flex items-center justify-between gap-3 py-2 text-sm">
          <div>
            <span className="font-medium">{batch.product?.name}</span>
            <span className="ml-2 text-gray-400">#{batch.batch_code ?? batch.id}</span>
          </div>
          <div className="text-right">
            <span className={`font-semibold ${batch.is_expired ? 'text-red-600' : 'text-amber-600'}`}>
              {batch.expiry_date}
            </span>
            <span className="ml-2 text-gray-500">
              {batch.is_expired ? 'geçti' : `${batch.days_until_expiry} gün`} · kalan {batch.remaining_quantity}
            </span>
          </div>
        </li>
      ))}
    </ul>
  )
}
