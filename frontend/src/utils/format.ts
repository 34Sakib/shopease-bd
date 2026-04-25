export function formatBdt(n: number): string {
  const s = n.toLocaleString('en-BD', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
  return `৳${s}`
}

export function formatInt(n: number): string {
  return n.toLocaleString('en-BD')
}
