<script setup lang="ts">
import { computed } from 'vue'
import {
  Activity,
  ArrowDownRight,
  ArrowUpRight,
  Ban,
  BarChart3,
  BellRing,
  Building2,
  Calendar,
  CalendarClock,
  CheckCircle2,
  Clock,
  Eye,
  FileText,
  Gauge,
  GraduationCap,
  LifeBuoy,
  Lightbulb,
  LineChart,
  ListChecks,
  Megaphone,
  Moon,
  Newspaper,
  PieChart,
  Plane,
  RotateCcw,
  Search,
  Settings,
  Sun,
  ShieldCheck,
  ShoppingBag,
  Sparkles,
  Stethoscope,
  Timer,
  TrendingDown,
  TrendingUp,
  UserCheck,
  Users,
  XCircle,
  Zap,
} from '@lucide/vue'
import type { Component } from 'vue'

export type IconName =
  | 'activity'
  | 'arrow-down'
  | 'arrow-up'
  | 'ban'
  | 'bell'
  | 'building'
  | 'calendar'
  | 'calendar-clock'
  | 'chart-bar'
  | 'chart-doughnut'
  | 'chart-line'
  | 'check'
  | 'clock'
  | 'eye'
  | 'file'
  | 'gauge'
  | 'graduation'
  | 'lightbulb'
  | 'list'
  | 'megaphone'
  | 'moon'
  | 'news'
  | 'plane'
  | 'rotate'
  | 'search'
  | 'settings'
  | 'shield'
  | 'shopping-bag'
  | 'sparkles'
  | 'stethoscope'
  | 'sun'
  | 'support'
  | 'timer'
  | 'trend-down'
  | 'trend-up'
  | 'user-check'
  | 'users'
  | 'x'
  | 'zap'

export type IconTone = 'brand' | 'success' | 'warning' | 'danger' | 'neutral' | 'violet'

const registry: Record<IconName, Component> = {
  activity: Activity,
  'arrow-down': ArrowDownRight,
  'arrow-up': ArrowUpRight,
  ban: Ban,
  bell: BellRing,
  building: Building2,
  calendar: Calendar,
  'calendar-clock': CalendarClock,
  'chart-bar': BarChart3,
  'chart-doughnut': PieChart,
  'chart-line': LineChart,
  check: CheckCircle2,
  clock: Clock,
  eye: Eye,
  file: FileText,
  gauge: Gauge,
  graduation: GraduationCap,
  lightbulb: Lightbulb,
  list: ListChecks,
  megaphone: Megaphone,
  moon: Moon,
  news: Newspaper,
  plane: Plane,
  rotate: RotateCcw,
  search: Search,
  settings: Settings,
  shield: ShieldCheck,
  'shopping-bag': ShoppingBag,
  sparkles: Sparkles,
  stethoscope: Stethoscope,
  sun: Sun,
  support: LifeBuoy,
  timer: Timer,
  'trend-down': TrendingDown,
  'trend-up': TrendingUp,
  'user-check': UserCheck,
  users: Users,
  x: XCircle,
  zap: Zap,
}

const toneClasses: Record<IconTone, string> = {
  brand: 'text-brand-700',
  success: 'text-success-600',
  warning: 'text-warning-600',
  danger: 'text-danger-600',
  neutral: 'text-ink-muted',
  violet: 'text-violet-600',
}

const props = withDefaults(
  defineProps<{
    name: IconName
    tone?: IconTone
    size?: 'sm' | 'md' | 'lg' | 'xl'
  }>(),
  {
    tone: 'neutral',
    size: 'md',
  },
)

const sizeClasses: Record<string, string> = {
  sm: 'size-4',
  md: 'size-5',
  lg: 'size-6',
  xl: 'size-8',
}

const icon = computed(() => registry[props.name] ?? Sparkles)
</script>

<template>
  <component
    :is="icon"
    :class="['shrink-0', toneClasses[tone], sizeClasses[size]]"
    aria-hidden="true"
  />
</template>
