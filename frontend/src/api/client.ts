export function getApiBase(): string {
  const raw = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'
  return raw.replace(/\/+$/, '')
}

export function apiUrl(path: string): string {
  const base = getApiBase()
  const p = path.startsWith('/') ? path : `/${path}`
  return `${base}${p}`
}

export class ApiError extends Error {
  status: number
  body: unknown

  constructor(message: string, status: number, body: unknown) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

async function parseBody(res: Response): Promise<unknown> {
  const text = await res.text()
  if (!text) return null
  try {
    return JSON.parse(text) as unknown
  } catch {
    return text
  }
}

export async function apiGetJson<T>(path: string, search?: URLSearchParams): Promise<T> {
  const url = apiUrl(path)
  const full = search && [...search].length ? `${url}?${search.toString()}` : url
  const res = await fetch(full, {
    headers: { Accept: 'application/json' },
  })
  const body = await parseBody(res)
  if (!res.ok) {
    const msg =
      typeof body === 'object' && body !== null && 'message' in body
        ? String((body as { message: unknown }).message)
        : `HTTP ${res.status}`
    throw new ApiError(msg, res.status, body)
  }
  return body as T
}

export function triggerBlobDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

export function filenameFromContentDisposition(header: string | null, fallback: string): string {
  if (!header) return fallback
  const m = /filename\*?=(?:UTF-8'')?["']?([^"';]+)["']?/i.exec(header)
  if (m?.[1]) {
    try {
      return decodeURIComponent(m[1].trim())
    } catch {
      return m[1].trim()
    }
  }
  return fallback
}
