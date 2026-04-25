import {
  apiGetJson,
  apiUrl,
  ApiError,
  filenameFromContentDisposition,
  getApiBase,
} from './client'
import type {
  ExportQueuedResponse,
  ExportStatusResponse,
  HealthResponse,
  ImportSummaryResponse,
  PaginatedSales,
  SalesFilters,
  SalesSummaryResponse,
} from './types'

function filtersToSearchParams(f: SalesFilters): URLSearchParams {
  const p = new URLSearchParams()
  if (f.branch) p.set('branch', f.branch)
  if (f.category) p.set('category', f.category)
  if (f.payment_method) p.set('payment_method', f.payment_method)
  if (f.from) p.set('from', f.from)
  if (f.to) p.set('to', f.to)
  if (f.page != null && f.page > 1) p.set('page', String(f.page))
  return p
}

export function getHealth(): Promise<HealthResponse> {
  return apiGetJson<HealthResponse>('/health')
}

export function getSales(filters: SalesFilters): Promise<PaginatedSales> {
  return apiGetJson<PaginatedSales>('/sales', filtersToSearchParams(filters))
}

export function getSalesSummary(
  filters: Omit<SalesFilters, 'page'>,
): Promise<SalesSummaryResponse> {
  return apiGetJson<SalesSummaryResponse>('/sales/summary', filtersToSearchParams(filters))
}

export async function postImport(file: File): Promise<ImportSummaryResponse> {
  const fd = new FormData()
  fd.append('file', file)
  const res = await fetch(apiUrl('/import'), {
    method: 'POST',
    body: fd,
    headers: { Accept: 'application/json' },
  })
  const raw = await res.text()
  let body: unknown = raw
  try {
    body = raw ? JSON.parse(raw) : null
  } catch {
    /* keep raw text */
  }
  if (!res.ok) {
    const msg =
      typeof body === 'object' && body !== null && 'message' in body
        ? String((body as { message: unknown }).message)
        : typeof body === 'string' && body
          ? body
          : `Import failed (${res.status})`
    throw new ApiError(msg, res.status, body)
  }
  return body as ImportSummaryResponse
}

export type ExportDownloadOutcome =
  | { kind: 'blob'; blob: Blob; filename: string }
  | { kind: 'queued'; payload: ExportQueuedResponse }

async function requestExport(
  path: '/export/csv' | '/export/excel',
  filters: Omit<SalesFilters, 'page'>,
): Promise<ExportDownloadOutcome> {
  const params = filtersToSearchParams(filters)
  const url = `${apiUrl(path)}?${params.toString()}`
  const res = await fetch(url, { headers: { Accept: '*/*' } })

  if (res.status === 202) {
    const payload = (await res.json()) as ExportQueuedResponse
    return { kind: 'queued', payload }
  }

  const ct = res.headers.get('Content-Type') ?? ''
  if (res.status === 200 && ct.includes('application/json')) {
    const payload = (await res.json()) as ExportQueuedResponse
    if (payload.status === 'queued' && payload.job_id) {
      return { kind: 'queued', payload }
    }
  }

  if (!res.ok) {
    const body = await res.text()
    throw new ApiError(body || `Export failed (${res.status})`, res.status, body)
  }

  const blob = await res.blob()
  const ext = path === '/export/csv' ? 'csv' : 'xlsx'
  const fallback = `sales-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.${ext}`
  const filename = filenameFromContentDisposition(
    res.headers.get('Content-Disposition'),
    fallback,
  )
  return { kind: 'blob', blob, filename }
}

export function requestExportCsv(filters: Omit<SalesFilters, 'page'>): Promise<ExportDownloadOutcome> {
  return requestExport('/export/csv', filters)
}

export function requestExportExcel(filters: Omit<SalesFilters, 'page'>): Promise<ExportDownloadOutcome> {
  return requestExport('/export/excel', filters)
}

export function getExportStatus(jobId: string): Promise<ExportStatusResponse> {
  return apiGetJson<ExportStatusResponse>(`/export/status/${encodeURIComponent(jobId)}`)
}

export async function downloadExportFile(downloadUrl: string): Promise<{ blob: Blob; filename: string }> {
  const absolute = downloadUrl.startsWith('http') ? downloadUrl : `${getApiBase()}${downloadUrl}`
  const res = await fetch(absolute)
  if (!res.ok) {
    const t = await res.text()
    throw new ApiError(t || `Download failed (${res.status})`, res.status, t)
  }
  const blob = await res.blob()
  const ext = blob.type.includes('spreadsheet') ? 'xlsx' : 'csv'
  const filename = filenameFromContentDisposition(
    res.headers.get('Content-Disposition'),
    `export-${Date.now()}.${ext}`,
  )
  return { blob, filename }
}
