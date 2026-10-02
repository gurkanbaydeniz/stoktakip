import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError, api } from '../api'
import type { Attachment, Batch } from '../types'

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf']
const MAX_SIZE = 10 * 1024 * 1024 // 10 MB (API ile aynı)

export function UploadPage() {
  const [file, setFile] = useState<File | null>(null)
  const [kind, setKind] = useState('waybill')
  const [batchId, setBatchId] = useState('')
  const [batches, setBatches] = useState<Batch[]>([])
  const [attachments, setAttachments] = useState<Attachment[]>([])
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null)
  const [dragging, setDragging] = useState(false)
  const [busy, setBusy] = useState(false)
  const inputRef = useRef<HTMLInputElement>(null)

  const loadLists = useCallback(() => {
    api<{ data: Batch[] }>('/batches').then((res) => setBatches(res.data)).catch(() => {})
    api<{ data: Attachment[] }>('/attachments')
      .then((res) => setAttachments(res.data))
      .catch(() => {})
  }, [])

  useEffect(loadLists, [loadLists])

  function pickFile(candidate: File | null | undefined): void {
    setMessage(null)
    if (!candidate) return
    if (!ACCEPTED_TYPES.includes(candidate.type)) {
      setMessage({ ok: false, text: 'Desteklenmeyen dosya türü. jpg, png, webp, heic veya pdf yükleyin.' })
      return
    }
    if (candidate.size > MAX_SIZE) {
      setMessage({ ok: false, text: 'Dosya 10 MB sınırını aşıyor.' })
      return
    }
    setFile(candidate)
  }

  async function upload(): Promise<void> {
    if (!file) return
    setBusy(true)
    setMessage(null)
    try {
      const form = new FormData()
      form.append('file', file)
      form.append('kind', kind)
      if (batchId) form.append('batch_id', batchId)
      await api('/attachments', { method: 'POST', form })
      setMessage({ ok: true, text: `"${file.name}" yüklendi.` })
      setFile(null)
      if (inputRef.current) inputRef.current.value = ''
      loadLists()
    } catch (err) {
      setMessage({
        ok: false,
        text: err instanceof ApiError ? Object.values(err.errors)[0]?.[0] ?? err.message : 'Yükleme başarısız.',
      })
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-xl font-bold">İrsaliye / Fotoğraf Yükleme</h1>

      <div
        onPaste={(event) => {
          const pasted = Array.from(event.clipboardData.items)
            .map((item) => (item.kind === 'file' ? item.getAsFile() : null))
            .find(Boolean)
          if (pasted) {
            event.preventDefault()
            pickFile(pasted)
            setMessage({ ok: true, text: 'Panodan yapıştırıldı. Yüklemek için onaylayın.' })
          }
        }}
        onDragOver={(event) => {
          event.preventDefault()
          setDragging(true)
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={(event) => {
          event.preventDefault()
          setDragging(false)
          pickFile(event.dataTransfer.files?.[0])
        }}
        className={`rounded-2xl border-2 border-dashed p-10 text-center transition ${
          dragging ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300 bg-white'
        }`}
      >
        <p className="text-4xl">🧾</p>
        <p className="mt-2 font-medium">Dosyayı buraya sürükleyin veya panodan yapıştırın (Ctrl+V)</p>
        <p className="mt-1 text-sm text-gray-500">jpg · png · webp · heic · pdf — en fazla 10 MB</p>
        <button
          onClick={() => inputRef.current?.click()}
          className="mt-4 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50"
        >
          Dosya Seç
        </button>
        <input
          ref={inputRef}
          type="file"
          accept={ACCEPTED_TYPES.join(',')}
          className="hidden"
          onChange={(event) => pickFile(event.target.files?.[0])}
        />
        {file && (
          <p className="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            Seçildi: <strong>{file.name}</strong> ({Math.ceil(file.size / 1024)} KB)
          </p>
        )}
      </div>

      <div className="flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4">
        <label className="text-sm">
          <span className="mb-1 block font-medium">Tür</span>
          <select value={kind} onChange={(event) => setKind(event.target.value)} className="rounded-lg border border-gray-300 px-3 py-2">
            <option value="waybill">İrsaliye</option>
            <option value="product_photo">Ürün fotoğrafı</option>
            <option value="other">Diğer</option>
          </select>
        </label>
        <label className="text-sm">
          <span className="mb-1 block font-medium">İlgili parti (opsiyonel)</span>
          <select value={batchId} onChange={(event) => setBatchId(event.target.value)} className="rounded-lg border border-gray-300 px-3 py-2">
            <option value="">Bağlamayacağım</option>
            {batches.map((batch) => (
              <option key={batch.id} value={batch.id}>
                {batch.product?.name} · {batch.batch_code ?? `#${batch.id}`} · {batch.expiry_date ?? 'SKT yok'}
              </option>
            ))}
          </select>
        </label>
        <button
          onClick={() => void upload()}
          disabled={!file || busy}
          className="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
        >
          {busy ? 'Yükleniyor…' : 'Yükle'}
        </button>
        {message && (
          <p className={`text-sm ${message.ok ? 'text-emerald-700' : 'text-red-600'}`}>{message.text}</p>
        )}
      </div>

      <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <h2 className="border-b border-gray-100 px-4 py-3 font-semibold">Son Yüklenen Ekler</h2>
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th className="px-4 py-3">Dosya</th>
              <th className="px-4 py-3">Tür</th>
              <th className="px-4 py-3">OCR</th>
              <th className="px-4 py-3">Tarih</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {attachments.map((attachment) => (
              <tr key={attachment.id}>
                <td className="px-4 py-3 font-medium">{attachment.original_name}</td>
                <td className="px-4 py-3 text-gray-500">{attachment.kind}</td>
                <td className="px-4 py-3">
                  <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                    {attachment.ocr_status}
                  </span>
                </td>
                <td className="px-4 py-3 text-gray-500">
                  {new Date(attachment.created_at).toLocaleString('tr-TR')}
                </td>
                <td className="px-4 py-3 text-right">
                  <a
                    href={attachment.url}
                    target="_blank"
                    rel="noreferrer"
                    className="font-medium text-emerald-700 hover:underline"
                  >
                    Görüntüle
                  </a>
                </td>
              </tr>
            ))}
            {attachments.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-gray-500">Henüz ek yüklenmedi.</td>
              </tr>
            )}
          </tbody>
        </table>
      </section>
    </div>
  )
}
