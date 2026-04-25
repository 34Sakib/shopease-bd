import type { SalesFilters } from '../api/types'

export function parseSalesFilters(searchParams: URLSearchParams): SalesFilters {
  const pageRaw = searchParams.get('page')
  const page = pageRaw ? Math.max(1, parseInt(pageRaw, 10) || 1) : 1
  return {
    branch: searchParams.get('branch') ?? undefined,
    category: searchParams.get('category') ?? undefined,
    payment_method: searchParams.get('payment_method') ?? undefined,
    from: searchParams.get('from') ?? undefined,
    to: searchParams.get('to') ?? undefined,
    page: page > 1 ? page : undefined,
  }
}

export function salesFiltersToSearchParams(f: SalesFilters): URLSearchParams {
  const p = new URLSearchParams()
  if (f.branch) p.set('branch', f.branch)
  if (f.category?.trim()) p.set('category', f.category.trim())
  if (f.payment_method) p.set('payment_method', f.payment_method)
  if (f.from) p.set('from', f.from)
  if (f.to) p.set('to', f.to)
  if (f.page != null && f.page > 1) p.set('page', String(f.page))
  return p
}

export function omitPage({ page, ...rest }: SalesFilters): Omit<SalesFilters, 'page'> {
  void page
  return rest
}
