import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { getApiBase } from '../api/client'
import { getHealth } from '../api/salesApi'
import { formatApiError } from '../utils/errors'

export function Dashboard() {
  const health = useQuery({
    queryKey: ['health'],
    queryFn: getHealth,
    staleTime: 30_000,
    retry: 1,
  })

  return (
    <>
      <h1>Wholesale overview</h1>
      <p className="muted" style={{ marginBottom: '1.25rem' }}>
        Revenue, volume, and branch performance from cleaned sales data.
      </p>

      <div className="card">
        <h2 className="card__title">API status</h2>
        {health.isPending && <p className="muted">Checking connection…</p>}
        {health.isError && (
          <div className="alert alert--error" role="alert">
            Cannot reach API. Is the backend running at <code>{getApiBase()}</code>?{' '}
            {formatApiError(health.error)}
          </div>
        )}
        {health.isSuccess && (
          <p style={{ margin: 0 }}>
            <span className="badge" style={{ background: '#d1fae5', color: '#065f46' }}>
              Connected
            </span>{' '}
            <strong>{health.data.app}</strong> - server time{' '}
            <time dateTime={health.data.time}>{new Date(health.data.time).toLocaleString()}</time>
          </p>
        )}
      </div>

      <div className="dashboard-cards">
        <Link to="/import" className="dash-card">
          <h3>Import</h3>
          <p>Upload CSV or Excel. Rows are cleaned and deduplicated in chunks.</p>
        </Link>
        <Link to="/sales" className="dash-card">
          <h3>Sales</h3>
          <p>Browse and filter normalized sales (100 per page).</p>
        </Link>
        <Link to="/export" className="dash-card">
          <h3>Export</h3>
          <p>Download CSV or Excel using the same filters as Sales.</p>
        </Link>
      </div>
    </>
  )
}
