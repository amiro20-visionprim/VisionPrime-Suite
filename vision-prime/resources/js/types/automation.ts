export interface Command {
  id: number
  type: string
  risk_tier: string
  status: string
  expires_at: string
}
export interface CommandApproval {
  id: number
  decision: string
  note: string | null
}
export interface CommandExecutionLog {
  id: number
  status: string
  executed_at: string | null
}
export interface RollbackSnapshot {
  id: number
  target_ref: string
  status: string
  expires_at: string | null
}
export interface Paginated<T> {
  data: T[]
}
