/**
 * Shared Persian label maps for domain enum values.
 * Keep values aligned with the backend (commands, reviews, risk tiers...).
 */

export const commandTypeLabels: Record<string, string> = {
  update_meta_title: 'به‌روزرسانی عنوان متا',
  update_meta_description: 'به‌روزرسانی توضیحات متا',
  update_content: 'به‌روزرسانی محتوای صفحه',
  add_internal_link: 'افزودن لینک داخلی',
  update_schema: 'به‌روزرسانی داده ساختاریافته',
  update_h1: 'به‌روزرسانی عنوان اصلی (H1)',
  update_alt_text: 'به‌روزرسانی متن جایگزین تصویر',
  add_faq_schema: 'افزودن اسکیمای سوالات متداول',
  publish_content: 'انتشار محتوا',
  publish_new_article: 'انتشار مقاله/محصول جدید',
}

export const commandStatusLabels: Record<string, string> = {
  draft: 'پیش‌نویس',
  pending: 'در انتظار تأیید',
  pending_approval: 'در انتظار تأیید انسانی',
  queued: 'در صف',
  approved: 'تأیید شده',
  rejected: 'رد شده',
  cancelled: 'لغو شده',
  changes_requested: 'درخواست تغییر',
  dispatched: 'در حال اجرا',
  executed: 'اجرا شده',
  failed: 'ناموفق',
  completed: 'تکمیل شده',
  rolled_back: 'بازگشت خورده',
  scheduled: 'زمان‌بندی شده',
}

export const contentScopeLabels: Record<string, string> = {
  meta: 'متا',
  article: 'مقاله',
  product: 'محصول',
}

export const riskTierLabels: Record<string, string> = {
  R0: 'بدون ریسک',
  R1: 'ریسک پایین',
  R2: 'ریسک متوسط',
  R3: 'ریسک بالا',
  R4: 'ریسک بحرانی',
}

export const reviewSubjectLabels: Record<string, string> = {
  money_page_audit: 'بازبینی صفحه درآمدزا',
  ai_generation: 'بازبینی پیشنویس هوش مصنوعی',
  command: 'بازبینی تغییر اجرایی',
  url_profile: 'بازبینی صفحه',
  content_snapshot: 'بازبینی محتوای صفحه',
}

export const reviewStatusLabels: Record<string, string> = {
  pending_review: 'در انتظار بازبینی',
  pending_approval: 'در انتظار تأیید',
  approved: 'تأیید شده',
  rejected: 'رد شده',
  changes_requested: 'درخواست تغییر',
  completed: 'تکمیل شده',
}

export const decisionLabels: Record<string, string> = {
  approved: 'تأیید',
  rejected: 'رد',
  changes_requested: 'درخواست تغییر',
}

/** Lookup a value in a label map; falls back to the raw value when unknown. */
export function labelOf(map: Record<string, string>, value?: string | null): string {
  if (!value) return '—'
  return map[value] ?? value
}
