import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { BRANCHES, PAYMENT_METHODS } from '../constants'
import type { SalesFilters } from '../api/types'
import { parseSalesFilters, salesFiltersToSearchParams } from '../url/filters'

type Draft = {
  branch: string
  category: string
  payment_method: string
  from: string
  to: string
}

function filtersToDraft(f: ReturnType<typeof parseSalesFilters>): Draft {
  return {
    branch: f.branch ?? '',
    category: f.category ?? '',
    payment_method: f.payment_method ?? '',
    from: f.from ?? '',
    to: f.to ?? '',
  }
}

function SalesFilterBarInner({
  searchParamsSnapshot,
  setSearchParams,
}: {
  searchParamsSnapshot: URLSearchParams
  setSearchParams: ReturnType<typeof useSearchParams>[1]
}) {
  const [draft, setDraft] = useState<Draft>(() =>
    filtersToDraft(parseSalesFilters(searchParamsSnapshot)),
  )

  const apply = () => {
    const next: SalesFilters = {
      branch: draft.branch || undefined,
      category: draft.category || undefined,
      payment_method: draft.payment_method || undefined,
      from: draft.from || undefined,
      to: draft.to || undefined,
    }
    setSearchParams(salesFiltersToSearchParams(next), { replace: true })
  }

  const clear = () => {
    setDraft({ branch: '', category: '', payment_method: '', from: '', to: '' })
    setSearchParams(new URLSearchParams(), { replace: true })
  }

  return (
    <div className="card">
      <h2 className="card__title">Filters</h2>
      <p className="muted" style={{ marginBottom: '1rem' }}>
        Match the Laravel API: branch, date range (YYYY-MM-DD), category, payment method.
      </p>
      <div className="form-grid">
        <label className="field">
          Branch
          <select
            value={draft.branch}
            onChange={(e) => setDraft((d) => ({ ...d, branch: e.target.value }))}
          >
            <option value="">All branches</option>
            {BRANCHES.map((b) => (
              <option key={b} value={b}>
                {b}
              </option>
            ))}
          </select>
        </label>
        <label className="field">
          From
          <input
            type="date"
            value={draft.from}
            onChange={(e) => setDraft((d) => ({ ...d, from: e.target.value }))}
          />
        </label>
        <label className="field">
          To
          <input
            type="date"
            value={draft.to}
            onChange={(e) => setDraft((d) => ({ ...d, to: e.target.value }))}
          />
        </label>
        <label className="field">
          Category
          <input
            type="text"
            placeholder="Exact match"
            value={draft.category}
            onChange={(e) => setDraft((d) => ({ ...d, category: e.target.value }))}
          />
        </label>
        <label className="field">
          Payment
          <select
            value={draft.payment_method}
            onChange={(e) => setDraft((d) => ({ ...d, payment_method: e.target.value }))}
          >
            <option value="">All methods</option>
            {PAYMENT_METHODS.map((m) => (
              <option key={m} value={m}>
                {m}
              </option>
            ))}
          </select>
        </label>
        <div className="form-actions" style={{ gridColumn: '1 / -1' }}>
          <button type="button" className="btn btn--primary" onClick={() => apply()}>
            Apply filters
          </button>
          <button type="button" className="btn btn--secondary" onClick={clear}>
            Clear
          </button>
        </div>
      </div>
    </div>
  )
}

export function SalesFilterBar({ id }: { id?: string }) {
  const [searchParams, setSearchParams] = useSearchParams()
  return (
    <div id={id}>
      <SalesFilterBarInner
        key={searchParams.toString()}
        searchParamsSnapshot={searchParams}
        setSearchParams={setSearchParams}
      />
    </div>
  )
}
