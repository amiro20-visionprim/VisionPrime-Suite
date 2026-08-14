export interface AudienceStat {
  to: number
  suffix?: string
  label: string
}

export interface AudiencePain {
  title: string
  text: string
  solution: string
}

export interface AudienceStep {
  title: string
  text: string
}

export interface AudienceFaq {
  q: string
  a: string
}

export interface AudienceLink {
  href: string
  title: string
  description: string
}

import type { Component } from 'vue'

export interface AudienceCasePoint {
  label: string
  value: string
  hint?: string
}

export interface AudienceWhyItem {
  icon: Component
  title: string
  text: string
}

export interface AudienceMatrixRow {
  concern: string
  scenario: string
  vision: string
  inhouse: string
  saas: string
}

export interface AudiencePageData {
  slug: string
  navLabel: string
  title: string
  gradientWord: string
  description: string
  badge: string
  stats: AudienceStat[]
  pains: AudiencePain[]
  workflow: AudienceStep[]
  faqs: AudienceFaq[]
  related: AudienceLink[]
  cta: {
    label: string
    href: string
  }
}
