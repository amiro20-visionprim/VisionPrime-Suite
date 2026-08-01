export interface GscAccount {
  id: number
  email: string
  status: string
  token_expires_at: string | null
}
export interface GscProperty {
  id: number
  site_id: number
  site_name?: string
  property_uri: string
  property_type: string
  status: string
}
export interface GscImportRun {
  id: number
  site_name: string
  status: string
  summary: Record<string, unknown> | null
  error: Record<string, unknown> | null
}
export interface GscPageMetric {
  id: number
  page_url: string
  clicks: number
  impressions: number
  ctr: number
  position: number | null
}
export interface GscQueryMetric {
  id: number
  query: string
  clicks: number
  impressions: number
  ctr: number
  position: number | null
}
export interface Paginated<T> {
  data: T[]
}
