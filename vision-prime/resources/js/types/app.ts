export type AppLocale = 'fa' | 'en'
export type TextDirection = 'rtl' | 'ltr'
export type DigitPreference = 'persian' | 'latin'

export interface VisionPrimeAppContext {
  name: string
  locale: AppLocale
  timezone: string
}

export interface OrganizationContext {
  publicId: string
  name: string
  slug: string
}

export interface ClientContext {
  publicId: string
  name: string
}

export interface AppPageProps {
  app: VisionPrimeAppContext
  currentOrganization?: OrganizationContext | null
  currentClient?: ClientContext | null
  [key: string]: unknown
}
