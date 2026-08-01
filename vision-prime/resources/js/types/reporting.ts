export interface Report {
  id: number
  type: string
  status: string
  period_start: string
  period_end: string
  content: Record<string, unknown> | null
  published_at: string | null
}
export interface Paginated<T> {
  data: T[]
}
