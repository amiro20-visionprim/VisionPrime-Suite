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
}
export interface ConversionRisk {
  id: number
  severity: string
  score: number
  explanation: string
  canonical_url: string
}
