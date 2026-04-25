import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useCallback, useRef, useState } from 'react'
import { postImport } from '../api/salesApi'
import { triggerBlobDownload } from '../api/client'
import { formatApiError } from '../utils/errors'

export function ImportPage() {
  const inputRef = useRef<HTMLInputElement>(null)
  const [drag, setDrag] = useState(false)
  const [localError, setLocalError] = useState<string | null>(null)
  const qc = useQueryClient()

  const mutation = useMutation({
    mutationFn: postImport,
    onSuccess: () => {
      setLocalError(null)
      void qc.invalidateQueries({ queryKey: ['sales'] })
      void qc.invalidateQueries({ queryKey: ['summary'] })
    },
    onError: (err) => {
      setLocalError(formatApiError(err))
    },
  })

  const runFile = useCallback(
    (file: File | undefined) => {
      if (!file) return
      setLocalError(null)
      mutation.mutate(file)
    },
    [mutation],
  )

  const onDrop = (e: React.DragEvent) => {
    e.preventDefault()
    setDrag(false)
    const f = e.dataTransfer.files[0]
    runFile(f)
  }

  const downloadErrors = async () => {
    const url = mutation.data?.error_log_url
    if (!url) return
    const res = await fetch(url)
    if (!res.ok) {
      setLocalError(`Could not download error log (${res.status})`)
      return
    }
    const blob = await res.blob()
    triggerBlobDownload(blob, `import-${mutation.data?.import_id ?? 'errors'}.csv`)
  }

  return (
    <>
      <h1>Import sales file</h1>
      <p className="muted" style={{ marginBottom: '1.25rem' }}>
        Upload CSV or Excel. Rows are cleaned, deduplicated, and stored in chunks (500 at a time on the server).
      </p>

      {(localError || (mutation.isError && mutation.error)) && (
        <div className="alert alert--error" role="alert">
          {localError ?? (mutation.error ? formatApiError(mutation.error) : '')}
        </div>
      )}

      <div className="card">
        <input
          ref={inputRef}
          type="file"
          accept=".csv,.txt,.xlsx,.xls,.ods"
          className="sr-only"
          aria-label="Choose sales file"
          onChange={(e) => runFile(e.target.files?.[0])}
        />
        <div
          className={`dropzone${drag ? ' drag' : ''}`}
          role="button"
          tabIndex={0}
          onClick={() => inputRef.current?.click()}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault()
              inputRef.current?.click()
            }
          }}
          onDragOver={(e) => {
            e.preventDefault()
            setDrag(true)
          }}
          onDragLeave={() => setDrag(false)}
          onDrop={onDrop}
        >
          {mutation.isPending ? (
            <p style={{ margin: 0 }}>Importing… please wait.</p>
          ) : (
            <>
              <p style={{ margin: '0 0 0.5rem', fontWeight: 600 }}>Drag a file here, or click to browse</p>
              <p className="muted" style={{ margin: 0 }}>
                CSV, XLSX, XLS, ODS · max 50 MB
              </p>
            </>
          )}
        </div>
      </div>

      {mutation.isSuccess && mutation.data && (
        <div className="card">
          <h2 className="card__title">Import finished</h2>
          <div
            className={mutation.data.skipped_invalid ? 'alert alert--error' : 'alert alert--success'}
            style={{ marginTop: 0 }}
          >
            {mutation.data.skipped_invalid
              ? 'Import finished with invalid rows. Download the error log for details.'
              : mutation.data.skipped_duplicate && !mutation.data.skipped_invalid
                ? 'Import completed. Some rows were skipped as duplicates (see error log).'
                : 'Import completed successfully.'}
          </div>
          <dl
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fill, minmax(140px, 1fr))',
              gap: '0.75rem',
              margin: 0,
            }}
          >
            <div>
              <dt className="muted" style={{ fontSize: '0.75rem', margin: 0 }}>
                Total rows
              </dt>
              <dd style={{ margin: 0, fontWeight: 700 }}>{mutation.data.total}</dd>
            </div>
            <div>
              <dt className="muted" style={{ fontSize: '0.75rem', margin: 0 }}>
                Inserted
              </dt>
              <dd style={{ margin: 0, fontWeight: 700 }}>{mutation.data.inserted}</dd>
            </div>
            <div>
              <dt className="muted" style={{ fontSize: '0.75rem', margin: 0 }}>
                Skipped (duplicate)
              </dt>
              <dd style={{ margin: 0, fontWeight: 700 }}>{mutation.data.skipped_duplicate}</dd>
            </div>
            <div>
              <dt className="muted" style={{ fontSize: '0.75rem', margin: 0 }}>
                Skipped (invalid)
              </dt>
              <dd style={{ margin: 0, fontWeight: 700 }}>{mutation.data.skipped_invalid}</dd>
            </div>
          </dl>
          {mutation.data.error_log_url ? (
            <p style={{ margin: '1rem 0 0' }}>
              <button type="button" className="btn btn--secondary" onClick={() => void downloadErrors()}>
                Download error log (CSV)
              </button>
            </p>
          ) : (
            <p className="muted" style={{ margin: '1rem 0 0', marginBottom: 0 }}>
              No error log — all rows were valid or only in-file duplicates were skipped without a log file.
            </p>
          )}
        </div>
      )}
    </>
  )
}
