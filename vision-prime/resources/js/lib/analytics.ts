export interface TrackEventParams {
  [key: string]: string | number | boolean | undefined
}

declare global {
  interface Window {
    dataLayer?: unknown[]
  }
}

/**
 * Push a structured event into window.dataLayer (Google Tag Manager / GA4
 * compatible). Any future analytics or ad platform (GTM, GA4, Yandex, native
 * pixel, ...) can consume these events without further code changes.
 */
export function trackEvent(name: string, params: TrackEventParams = {}): void {
  if (typeof window === 'undefined') {
    return
  }

  window.dataLayer = window.dataLayer ?? []
  window.dataLayer.push({ event: name, ...params })
}

const TRAFFIC_KEY = 'vp_traffic_attrs'

interface TrafficAttributes {
  utm_source?: string
  utm_medium?: string
  utm_campaign?: string
  utm_term?: string
  utm_content?: string
  landing_page?: string
  referrer?: string
}

/** Capture UTM / referrer attributes from the URL once and persist them for the session. */
export function captureTrafficAttributes(): TrafficAttributes {
  if (typeof window === 'undefined') {
    return {}
  }

  const stored = readTrafficAttributes()
  const url = new URL(window.location.href)
  const incoming: TrafficAttributes = {}

  for (const key of ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as const) {
    const value = url.searchParams.get(key)?.trim()
    if (value) {
      incoming[key] = value
    }
  }

  if (stored.landing_page === undefined) {
    incoming.landing_page = url.pathname
  }

  if (stored.referrer === undefined) {
    const referrer = document.referrer
    if (referrer) {
      try {
        incoming.referrer = new URL(referrer).hostname
      } catch {
        incoming.referrer = referrer
      }
    }
  }

  const merged: TrafficAttributes = { ...stored, ...incoming }

  try {
    window.sessionStorage.setItem(TRAFFIC_KEY, JSON.stringify(merged))
  } catch {
    // sessionStorage may be unavailable (privacy mode); attribution is best-effort.
  }

  return merged
}

export function readTrafficAttributes(): TrafficAttributes {
  if (typeof window === 'undefined') {
    return {}
  }

  try {
    const raw = window.sessionStorage.getItem(TRAFFIC_KEY)
    return raw ? (JSON.parse(raw) as TrafficAttributes) : {}
  } catch {
    return {}
  }
}
