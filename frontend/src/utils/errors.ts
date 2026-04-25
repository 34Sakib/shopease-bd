import { ApiError } from '../api/client'

export function formatApiError(err: unknown): string {
  if (err instanceof ApiError) {
    const b = err.body
    if (typeof b === 'object' && b !== null && 'errors' in b) {
      const errors = (b as { errors: Record<string, string[] | string> }).errors
      const parts: string[] = []
      for (const v of Object.values(errors)) {
        if (Array.isArray(v)) parts.push(...v)
        else if (typeof v === 'string') parts.push(v)
      }
      if (parts.length) return parts.join(' ')
    }
    if (typeof b === 'object' && b !== null && 'message' in b) {
      return String((b as { message: unknown }).message)
    }
    return err.message
  }
  if (err instanceof Error) return err.message
  return 'Something went wrong'
}
