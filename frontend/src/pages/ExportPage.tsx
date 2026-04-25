import { useMutation, useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  downloadExportFile,
  getExportStatus,
  requestExportCsv,
  requestExportExcel,
} from '../api/salesApi'
import { triggerBlobDownload } from '../api/client'
import { SalesFilterBar } from '../components/SalesFilterBar'
import { omitPage, parseSalesFilters } from '../url/filters'
import { formatApiError } from '../utils/errors'
import { formatInt } from '../utils/format'

export function ExportPage() {
  const [searchParams] = useSearchParams()
  const filters = useMemo(() => parseSalesFilters(searchParams), [searchParams])
  const exportFilters = useMemo(() => omitPage(filters), [filters])
  const [jobId, setJobId] = useState<string | null>(null)
  const [jobFormat, setJobFormat] = useState<'csv' | 'xlsx' | null>(null)
  const [banner, setBanner] = useState<{ type: 'ok' | 'err'; text: string } | null>(null)
  const status = useQuery({
    queryKey: ['export-status', jobId],
    queryFn: () => getExportStatus(jobId!),
    enabled: Boolean(jobId),
    refetchInterval: (q) => {
      const s = q.state.data?.status
      if (s === 'completed' || s === 'failed') return false
      return 2000
    },
  })

  const downloadMut = useMutation({
    mutationFn: async (url: string) => {
      const { blob, filename } = await downloadExportFile(url)
      triggerBlobDownload(blob, filename)
    },
    onError: (e) => setBanner({ type: 'err', text: formatApiError(e) }),
    onSuccess: () => {
      setBanner({ type: 'ok', text: 'Download started.' })
    },
  })

  const runExport = useMutation({
    mutationFn: async (format: 'csv' | 'xlsx') => {
      setBanner(null)
      const fn = format === 'csv' ? requestExportCsv : requestExportExcel
      const out = await fn(exportFilters)
      if (out.kind === 'blob') {
        triggerBlobDownload(out.blob, out.filename)
        return { kind: 'sync' as const }
      }
      setJobId(out.payload.job_id)
      setJobFormat(format)
      return { kind: 'queued' as const, rowCount: out.payload.row_count }
    },
    onSuccess: (res) => {
      if (res.kind === 'sync') {
        setJobId(null)
        setJobFormat(null)
        setBanner({ type: 'ok', text: 'File downloaded.' })
      } else {
        setBanner({
          type: 'ok',
          text: `Large export queued (${formatInt(res.rowCount)} rows). Status updates below.`,
        })
      }
    },
    onError: (e) => {
      setBanner({ type: 'err', text: formatApiError(e) })
    },
  })

  return (
    <>
      <h1>Export data</h1>
      <p className="muted" style={{ marginBottom: '1.25rem' }}>
        Uses the same filters as Sales. Up to 10,000 rows download immediately; larger exports run in the
        background — run <code>php artisan queue:work</code> on the server.
      </p>

      {banner && (
        <div className={`alert ${banner.type === 'err' ? 'alert--error' : 'alert--success'}`} role="status">
          {banner.text}
        </div>
      )}

      <SalesFilterBar />

      <div className="card">
        <h2 className="card__title">Download</h2>
        <p className="muted" style={{ marginBottom: '1rem' }}>
          CSV includes a UTF-8 BOM for Excel. Excel export has two sheets: Sales Data and Summary.
        </p>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
          <button
            type="button"
            className="btn btn--primary"
            disabled={runExport.isPending}
            onClick={() => runExport.mutate('csv')}
          >
            Export CSV
          </button>
          <button
            type="button"
            className="btn btn--secondary"
            disabled={runExport.isPending}
            onClick={() => runExport.mutate('xlsx')}
          >
            Export Excel
          </button>
        </div>
      </div>

      {jobId && (
        <div className="card">
          <h2 className="card__title">Background job</h2>
          <p className="muted" style={{ marginTop: 0 }}>
            Job ID: <code>{jobId}</code>
            {jobFormat ? ` · format: ${jobFormat}` : ''}
          </p>
          {status.isPending && <p className="muted">Loading status…</p>}
          {status.isError && (
            <div className="alert alert--error">{formatApiError(status.error)}</div>
          )}
          {status.data && (
            <>
              <p>
                <strong>Status:</strong> {status.data.status}
                {status.data.row_count != null && (
                  <>
                    {' '}
                    · <strong>Rows:</strong> {status.data.row_count}
                  </>
                )}
                {status.data.file_size_bytes != null && (
                  <>
                    {' '}
                    · <strong>Size:</strong> {(status.data.file_size_bytes / 1024).toFixed(1)} KB
                  </>
                )}
              </p>
              {status.data.error && (
                <div className="alert alert--error" style={{ marginTop: '0.5rem' }}>
                  {status.data.error}
                </div>
              )}
              {status.data.status === 'completed' && status.data.download_url ? (
                <button
                  type="button"
                  className="btn btn--primary"
                  disabled={downloadMut.isPending}
                  onClick={() => downloadMut.mutate(status.data.download_url as string)}
                >
                  Download file
                </button>
              ) : null}
            </>
          )}
        </div>
      )}
    </>
  )
}
