export interface Opportunity {
  id: number
  type: string
  score: number
  confidence: number
  status: string
  explanation: string
  query_normalized?: string | null
  canonical_url?: string | null
}
export interface OpportunityFactor {
  id: number
  key: string
  weight: number
  raw_value: number | null
  normalized_value: number | null
  explanation: string
}
export interface Paginated<T> {
  data: T[]
}
export interface MoneyPageAudit {
  id: number
  score: number
  canonical_url: string
  site_name?: string
  issues_count?: number
  audited_at?: string
}

export interface MoneyPageIssue {
  key: string
  severity: string
  explanation: string
}

export interface MoneyPageAuditDetail {
  id: number
  score: number
  canonicalUrl: string
  siteName: string
  auditedAt: string
  gsc: {
    clicks: number
    impressions: number
    ctr: number
    position: number
  } | null
  issues: MoneyPageIssue[]
  opportunities: {
    id: number
    type: string
    score: number
    explanation: string
  }[]
  reviewItemId: number | null
}
export interface ConversionRisk {
  id: number
  severity: string
  score: number
  explanation: string
  canonical_url: string
  audit_id?: number | null
  url_profile_id?: number
}
