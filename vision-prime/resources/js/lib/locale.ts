import { toJalaali } from 'jalaali-js'

import type { AppLocale, DigitPreference, TextDirection } from '@/types/app'

export function getDirection(locale: AppLocale): TextDirection {
  return locale === 'fa' ? 'rtl' : 'ltr'
}

export function syncDocumentLocale(locale: AppLocale): void {
  document.documentElement.lang = locale
  document.documentElement.dir = getDirection(locale)
  document.body.dir = getDirection(locale)
}

export function formatNumber(value: number, digits: DigitPreference = 'persian'): string {
  return new Intl.NumberFormat(digits === 'persian' ? 'fa-IR' : 'en-US', {
    maximumFractionDigits: 2,
  }).format(value)
}

export function formatPercent(value: number, digits: DigitPreference = 'persian'): string {
  return new Intl.NumberFormat(digits === 'persian' ? 'fa-IR' : 'en-US', {
    style: 'percent',
    maximumFractionDigits: 1,
  }).format(value / 100)
}

export function formatTechnicalNumber(value: number): string {
  return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value)
}

export function formatJalaliDate(
  value: Date | string | null,
  digits: DigitPreference = 'persian',
): string {
  if (value === null) {
    return '—'
  }

  const date = toDate(value)
  const jalali = toJalaali(date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate())
  const year = formatInteger(jalali.jy, digits)
  const month = formatInteger(jalali.jm, digits).padStart(2, digits === 'persian' ? '۰' : '0')
  const day = formatInteger(jalali.jd, digits).padStart(2, digits === 'persian' ? '۰' : '0')

  return `${year}/${month}/${day}`
}

export function formatJalaliDateTime(
  value: Date | string | null,
  digits: DigitPreference = 'persian',
): string {
  if (value === null) {
    return '—'
  }

  const date = toDate(value)
  const jalali = toJalaali(date.getUTCFullYear(), date.getUTCMonth() + 1, date.getUTCDate())
  const year = formatInteger(jalali.jy, digits)
  const month = formatInteger(jalali.jm, digits).padStart(2, digits === 'persian' ? '۰' : '0')
  const day = formatInteger(jalali.jd, digits).padStart(2, digits === 'persian' ? '۰' : '0')
  const hours = formatInteger(date.getUTCHours(), digits).padStart(2, digits === 'persian' ? '۰' : '0')
  const minutes = formatInteger(date.getUTCMinutes(), digits).padStart(2, digits === 'persian' ? '۰' : '0')

  return `${year}/${month}/${day} ${hours}:${minutes}`
}

export function formatLocalizedDate(
  value: Date | string,
  locale: AppLocale,
  timezone = 'Asia/Tehran',
): string {
  const date = toDate(value)

  return new Intl.DateTimeFormat(locale === 'fa' ? 'fa-IR-u-ca-persian' : 'en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: timezone,
  }).format(date)
}

export function isTechnicalText(value: string): boolean {
  return /[A-Za-z0-9:/?&=#._-]/.test(value)
}

function formatInteger(value: number, digits: DigitPreference): string {
  return new Intl.NumberFormat(digits === 'persian' ? 'fa-IR' : 'en-US', {
    useGrouping: false,
    maximumFractionDigits: 0,
  }).format(value)
}

function toDate(value: Date | string): Date {
  const date = value instanceof Date ? value : new Date(value)

  if (Number.isNaN(date.getTime())) {
    throw new Error('Invalid date value supplied to locale formatter.')
  }

  return date
}
