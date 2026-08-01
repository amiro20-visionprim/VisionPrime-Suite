export interface ReviewItem {
  id: number
  subject_type: string
  subject_id: number
  status: string
  assigned_to: number | null
  due_at: string | null
}
export interface ReviewDecision {
  id: number
  decision: string
  note: string | null
  decided_at: string
}
export interface Paginated<T> {
  data: T[]
}
