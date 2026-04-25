import { useQuery } from '@tanstack/react-query'
import { useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import { getSales, getSalesSummary } from '../api/salesApi'
import { SalesFilterBar } from '../components/SalesFilterBar'
import { omitPage, parseSalesFilters, salesFiltersToSearchParams } from '../url/filters'
import { formatApiError } from '../utils/errors'
import { formatBdt, formatInt } from '../utils/format'

export function SalesPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const filters = useMemo(() => parseSalesFilters(searchParams), [searchParams])
  const summaryFilters = useMemo(() => omitPage(filters), [filters])

  const sales = useQuery({
    queryKey: ['sales', filters],
    queryFn: () => getSales(filters),
    placeholderData: (prev) => prev,
  })

  const summary = useQuery({
    queryKey: ['summary', summaryFilters],
    queryFn: () => getSalesSummary(summaryFilters),
  })

  const goPage = (p: number) => {
    const next = { ...filters, page: p > 1 ? p : undefined }
    setSearchParams(salesFiltersToSearchParams(next), { replace: true })
  }

  const data = sales.data

  return (
    <>
      <h1>Sales</h1>
      <p className="muted" style={{ marginBottom: '1.25rem' }}>
        Paginated list (100 per page) with filters. Stored values are already normalized.
      </p>

      <SalesFilterBar />

      {summary.isError && (
        <div className="alert alert--error" role="alert">
          {formatApiError(summary.error)}
        </div>
      )}

      {summary.isSuccess && (
        <div className="card">
          <h2 className="card__title">Summary</h2>
          <p className="muted" style={{ marginBottom: '1rem' }}>
            Revenue uses{' '}
            <code style={{ fontSize: '0.85em' }}>quantity × unit_price × (1 − discount_pct)</code> on the server.
          </p>
          <div className="stats-grid">
            <div className="stat">
              <div className="stat__label">Total revenue</div>
              <div className="stat__value">{formatBdt(summary.data.total_revenue)}</div>
            </div>
            <div className="stat">
              <div className="stat__label">Total quantity</div>
              <div className="stat__value">{formatInt(summary.data.total_quantity)}</div>
            </div>
            <div className="stat">
              <div className="stat__label">Transactions</div>
              <div className="stat__value">{formatInt(summary.data.total_rows)}</div>
            </div>
            <div className="stat">
              <div className="stat__label">Avg order value</div>
              <div className="stat__value">{formatBdt(summary.data.average_order_value)}</div>
            </div>
          </div>
          <div className="two-col" style={{ marginTop: '1.25rem' }}>
            <div>
              <h3>Top products by revenue</h3>
              <div className="table-wrap">
                <table className="data">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Revenue</th>
                      <th>Qty</th>
                    </tr>
                  </thead>
                  <tbody>
                    {summary.data.top_products.map((p) => (
                      <tr key={p.product_name}>
                        <td>{p.product_name}</td>
                        <td>{formatBdt(p.revenue)}</td>
                        <td>{formatInt(p.quantity)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
            <div>
              <h3>By branch</h3>
              <div className="table-wrap">
                <table className="data">
                  <thead>
                    <tr>
                      <th>Branch</th>
                      <th>Rows</th>
                      <th>Revenue</th>
                    </tr>
                  </thead>
                  <tbody>
                    {summary.data.branch_breakdown.map((b) => (
                      <tr key={b.branch}>
                        <td>{b.branch}</td>
                        <td>{formatInt(b.rows)}</td>
                        <td>{formatBdt(b.revenue)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      )}

      {sales.isError && (
        <div className="alert alert--error" role="alert">
          {formatApiError(sales.error)}
        </div>
      )}

      {sales.isPending && <p className="muted">Loading sales…</p>}

      {data && (
        <div className="card">
          <h2 className="card__title">Results</h2>
          {data.total === 0 ? (
            <p className="muted" style={{ margin: 0 }}>
              No sales match these filters.
            </p>
          ) : (
            <>
              <div className="table-wrap">
                <table className="data">
                  <thead>
                    <tr>
                      <th>Sale ID</th>
                      <th>Branch</th>
                      <th>Date</th>
                      <th>Product</th>
                      <th>Category</th>
                      <th>Qty</th>
                      <th>Unit</th>
                      <th>Disc</th>
                      <th>Pay</th>
                      <th>Salesperson</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.data.map((row) => (
                      <tr key={row.id}>
                        <td>{row.sale_id}</td>
                        <td>{row.branch}</td>
                        <td>{row.sale_date}</td>
                        <td>{row.product_name}</td>
                        <td>{row.category ?? '—'}</td>
                        <td>{row.quantity}</td>
                        <td>{formatBdt(Number(row.unit_price))}</td>
                        <td>{row.discount_pct}</td>
                        <td>{row.payment_method}</td>
                        <td>{row.salesperson}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="pager">
                <span>
                  Page {data.current_page} of {data.last_page} · {formatInt(data.total)} total
                </span>
                <span style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                  <button
                    type="button"
                    className="btn btn--secondary"
                    disabled={data.current_page <= 1}
                    onClick={() => goPage(data.current_page - 1)}
                  >
                    Previous
                  </button>
                  <button
                    type="button"
                    className="btn btn--secondary"
                    disabled={data.current_page >= data.last_page}
                    onClick={() => goPage(data.current_page + 1)}
                  >
                    Next
                  </button>
                </span>
              </div>
            </>
          )}
        </div>
      )}
    </>
  )
}
