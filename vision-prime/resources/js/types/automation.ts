export interface Command {
  id: number
  type: string
  content_type: string | null
  risk_tier: string
  status: string
  expires_at: string
  confidence_score: number | null
  decision_source: string | null
  published_at: string | null
  site_name?: string | null
  platform_url?: string | null
  confidence_factors?: Record<string, unknown> | null
  gate_snapshot?: Record<string, unknown> | null
  auto_approved?: boolean
  post_id?: number | null
  post_url?: string | null
  impact?: PublishImpactReport | null
}

export interface PublishImpactReport {
  status: string
  reason?: string
  url?: string | null
  window_days?: number
  published_at?: string | null
  before?: { days: number; clicks: number; impressions: number; avg_position: number | null }
  after?: { days: number; clicks: number; impressions: number; avg_position: number | null }
  series?: { date: string; position: number | null; clicks: number }[]
  delta?: { position: number; clicks: number; impressions: number }
  verdict?: string
}

export interface ImpactSummaryEntry {
  command_id: number
  site_name: string | null
  url: string
  verdict: 'improved' | 'declined' | 'stable'
  delta: { position: number; clicks: number; impressions: number }
}

export interface ContentImpactSummary {
  published: number
  reported: number
  insufficient_data: number
  verdicts: { improved: number; declined: number; stable: number }
  best: ImpactSummaryEntry | null
  worst: ImpactSummaryEntry | null
  declines: ImpactSummaryEntry[]
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
