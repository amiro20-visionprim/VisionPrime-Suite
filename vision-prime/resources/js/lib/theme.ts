/**
 * Theme controller (D1-02).
 *
 * Class-based dark mode: the `dark` class lives on <html> and the token
 * overrides in app.css flip the whole suite. The preference is persisted
 * in localStorage; `system` tracks the OS and stays in sync.
 *
 * پیش‌فرض از نسخهٔ ۱.۹ به بعد «تاریک» است تا تجربهٔ یکپارچه‌ای در پنل‌ها
 * و صفحه‌های ورود/ثبت‌نام داشته باشیم؛ کاربر همیشه می‌تواند از توگل
 * (VThemeToggle) حالت روشن را انتخاب کند.
 */

/** پیش‌فرض هنگام اولین بازدید (قبل از هر انتخاب کاربر). */
export const DEFAULT_PREFERENCE: ThemePreference = 'dark'

export type ThemePreference = 'light' | 'dark' | 'system'

export const THEME_STORAGE_KEY = 'suite-theme'

export function getStoredPreference(): ThemePreference {
  if (typeof window === 'undefined') return DEFAULT_PREFERENCE
  const stored = window.localStorage.getItem(THEME_STORAGE_KEY)
  return stored === 'light' || stored === 'dark' || stored === 'system' ? stored : DEFAULT_PREFERENCE
}

export function systemPrefersDark(): boolean {
  if (typeof window === 'undefined' || !window.matchMedia) return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

export function resolveTheme(pref: ThemePreference): 'light' | 'dark' {
  if (pref === 'system') return systemPrefersDark() ? 'dark' : 'light'
  return pref
}

export function applyTheme(pref: ThemePreference): 'light' | 'dark' {
  const theme = resolveTheme(pref)
  if (typeof document !== 'undefined') {
    document.documentElement.classList.toggle('dark', theme === 'dark')
    document.documentElement.style.colorScheme = theme
  }
  return theme
}

export function setThemePreference(pref: ThemePreference): 'light' | 'dark' {
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(THEME_STORAGE_KEY, pref)
  }
  return applyTheme(pref)
}

/** Call once at app boot (before mount) and keep `system` in sync. */
export function initTheme(): void {
  applyTheme(getStoredPreference())
  if (typeof window !== 'undefined' && window.matchMedia) {
    window
      .matchMedia('(prefers-color-scheme: dark)')
      .addEventListener('change', () => {
        if (getStoredPreference() === 'system') applyTheme('system')
      })
  }
}
