export type LeadSource = 'demo' | 'support'
export type LeadStatus = 'new' | 'contacted' | 'qualified' | 'unqualified'

export interface MarketingLead {
  id: number
  name: string
  email: string | null
  company: string | null
  website: string | null
  message: string | null
  source: LeadSource
  status: LeadStatus
  utmSource: string | null
  utmMedium: string | null
  utmCampaign: string | null
  utmTerm: string | null
  utmContent: string | null
  landingPage: string | null
  referrer: string | null
  contact: string | null
  device: string | null
  score: number | null
  scoreBreakdown: { key: string; label: string; points: number }[]
  userAgent?: string | null
  locale?: string | null
  createdAt: string | null
}

export interface LeadNote {
  id: number
  body: string
  createdAt: string | null
  user: { name: string; email: string } | null
}

export interface MarketingStats {
  total: number
  thisWeek: number
  byStatus: Record<LeadStatus, number>
  bySource: { demo: number; support: number }
  topCampaigns: { campaign: string; count: number }[]
  topSources: { source: string; count: number }[]
}

export interface MarketingFilters {
  status: string
  source: string
  campaign: string
  q: string
  from: string
  to: string
  sort: string
}
