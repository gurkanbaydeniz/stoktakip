// API tipleri — Laravel Resource çıktılarıyla birebir

export interface Company {
  id: number
  name: string
  created_at?: string
}

export interface User {
  id: number
  name: string
  email: string
  username: string | null
  role: 'admin' | 'staff'
  company?: Company
}

export interface Product {
  id: number
  name: string
  barcode: string | null
  unit: string
  critical_stock_level: number
  stock_quantity: number
  is_below_critical_stock: boolean
  notes: string | null
  batches_count?: number
  batches?: Batch[]
}

export interface Batch {
  id: number
  product?: Product
  product_id: number
  batch_code: string | null
  expiry_date: string | null
  days_until_expiry: number | null
  is_expired: boolean
  quantity: number
  remaining_quantity: number
  unit_cost: number | null
  supplier_name: string | null
  received_at: string | null
}

export interface StockMovement {
  id: number
  type: 'in' | 'out' | 'adjustment'
  quantity: number
  product?: Product
  product_id: number
  batch_id: number | null
  user?: User
  waybill_number: string | null
  note: string | null
  created_at: string
}

export interface Alerts {
  days: number
  expiring_batches: Batch[]
  expired_batches: Batch[]
  low_stock_products: Product[]
}

export interface Attachment {
  id: number
  kind: string
  original_name: string
  mime_type: string | null
  size: number
  url: string
  batch_id: number | null
  uploader?: User
  ocr_status: 'pending' | 'processing' | 'completed' | 'failed'
  created_at: string
}
