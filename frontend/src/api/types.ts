export type SalesFilters = {
  branch?: string
  category?: string
  payment_method?: string
  from?: string
  to?: string
  page?: number
}

export type SaleRow = {
  id: number
  sale_id: string
  branch: string
  sale_date: string
  product_name: string
  category: string | null
  quantity: number
  unit_price: string
  discount_pct: string
  payment_method: string
  salesperson: string
}

export type PaginatedSales = {
  current_page: number
  data: SaleRow[]
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export type ImportSummaryResponse = {
  status: string
  import_id: string
  total: number
  inserted: number
  skipped_duplicate: number
  skipped_invalid: number
  error_log_url: string | null
}

export type SalesSummaryResponse = {
  total_revenue: number
  total_quantity: number
  total_rows: number
  average_order_value: number
  top_products: { product_name: string; revenue: number; quantity: number }[]
  branch_breakdown: {
    branch: string
    rows: number
    revenue: number
    quantity: number
  }[]
}

export type ExportQueuedResponse = {
  status: string
  job_id: string
  row_count: number
  status_url: string
}

export type ExportStatusResponse = {
  id: string
  status: 'pending' | 'processing' | 'completed' | 'failed'
  format: string
  row_count: number | null
  file_size_bytes: number | null
  error: string | null
  download_url: string | null
  created_at?: string
  started_at?: string | null
  completed_at?: string | null
}

export type HealthResponse = {
  status: string
  app: string
  time: string
}
