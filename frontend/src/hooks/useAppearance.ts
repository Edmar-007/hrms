import { useEffect, useState, useCallback } from 'react'

export type ThemeMode = 'light' | 'dark'
export type AccentMode = 'ocean' | 'forest' | 'sunset'
export type NavStyle = 'glass' | 'solid' | 'contrast'
export type DensityMode = 'comfortable' | 'compact'
export type IconSize = 'sm' | 'md' | 'lg'

const STORAGE_KEY = 'hrms.appearance'
const CHANGE_EVENT = 'hrms:appearance-change'

export interface AppearanceSettings {
  theme: ThemeMode
  accent: AccentMode
  navStyle: NavStyle
  density: DensityMode
  iconSize: IconSize
}

const DEFAULT_APPEARANCE: AppearanceSettings = {
  theme: 'light',
  accent: 'ocean',
  navStyle: 'glass',
  density: 'comfortable',
  iconSize: 'md',
}

function isAppearanceSettings(value: unknown): value is AppearanceSettings {
  if (!value || typeof value !== 'object') return false
  const candidate = value as Record<string, unknown>
  return (
    (candidate.theme === 'light' || candidate.theme === 'dark') &&
    (candidate.accent === 'ocean' || candidate.accent === 'forest' || candidate.accent === 'sunset') &&
    (candidate.navStyle === 'glass' || candidate.navStyle === 'solid' || candidate.navStyle === 'contrast') &&
    (candidate.density === 'comfortable' || candidate.density === 'compact') &&
    (candidate.iconSize === 'sm' || candidate.iconSize === 'md' || candidate.iconSize === 'lg')
  )
}

function readLocalAppearance(): AppearanceSettings {
  if (typeof window === 'undefined') return DEFAULT_APPEARANCE
  const stored = window.localStorage.getItem(STORAGE_KEY)
  if (!stored) return DEFAULT_APPEARANCE
  try {
    const parsed = JSON.parse(stored)
    return isAppearanceSettings(parsed) ? parsed : DEFAULT_APPEARANCE
  } catch {
    return DEFAULT_APPEARANCE
  }
}

function applyAppearanceSettings(settings: AppearanceSettings) {
  if (typeof document === 'undefined') return

  const root = document.documentElement
  root.dataset.theme = settings.theme
  root.dataset.accent = settings.accent
  root.dataset.navStyle = settings.navStyle
  root.dataset.density = settings.density
  root.dataset.iconSize = settings.iconSize
}

function persistLocalAppearance(settings: AppearanceSettings) {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(settings))
  window.dispatchEvent(new CustomEvent<AppearanceSettings>(CHANGE_EVENT, { detail: settings }))
}

export function useAppearance() {
  const [settings, setSettings] = useState<AppearanceSettings>(DEFAULT_APPEARANCE)
  const [isLoading, setIsLoading] = useState(true)

  // Load initial settings from local storage only
  useEffect(() => {
    const localSettings = readLocalAppearance()
    setSettings(localSettings)
    setIsLoading(false)
  }, [])

  useEffect(() => {
    if (!isLoading) {
      applyAppearanceSettings(settings)
      persistLocalAppearance(settings)
    }
  }, [settings, isLoading])

  useEffect(() => {
    if (typeof window === 'undefined') return

    const syncFromStorage = () => setSettings(readLocalAppearance())
    const handleStorage = (event: StorageEvent) => event.key === STORAGE_KEY && syncFromStorage()
    const handleCustomEvent = (event: Event) => {
      const detail = (event as CustomEvent<AppearanceSettings>).detail
      if (detail && isAppearanceSettings(detail)) {
        setSettings(detail)
      }
    }

    window.addEventListener('storage', handleStorage)
    window.addEventListener(CHANGE_EVENT, handleCustomEvent)
    return () => {
      window.removeEventListener('storage', handleStorage)
      window.removeEventListener(CHANGE_EVENT, handleCustomEvent)
    }
  }, [])

  const updateAppearance = useCallback((partial: Partial<AppearanceSettings>) => {
    setSettings(prev => ({ ...prev, ...partial }))
  }, [])

  const resetAppearance = useCallback(() => {
    setSettings(DEFAULT_APPEARANCE)
  }, [])

  return {
    appearance: settings,
    isLoading,
    updateAppearance,
    resetAppearance,
  }
}

