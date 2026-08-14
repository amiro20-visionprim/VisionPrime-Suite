/**
 * @lucide/vue@1.x ships no type declarations; declare the icon components
 * used across the app so vue-tsc stays clean.
 */
declare module '@lucide/vue' {
  import type { Component } from 'vue'

  interface LucideIconProps {
    size?: number | string
    strokeWidth?: number | string
    color?: string
    class?: string
  }

  type LucideIcon = Component<LucideIconProps>

  export const Activity: LucideIcon
  export const AlertTriangle: LucideIcon
  export const ArrowDown: LucideIcon
  export const ArrowLeft: LucideIcon
  export const ArrowUpRight: LucideIcon
  export const Award: LucideIcon
  export const BarChart3: LucideIcon
  export const BookOpen: LucideIcon
  export const Brain: LucideIcon
  export const Briefcase: LucideIcon
  export const Building2: LucideIcon
  export const CalendarDays: LucideIcon
  export const CheckCircle2: LucideIcon
  export const FileText: LucideIcon
  export const Gauge: LucideIcon
  export const Globe: LucideIcon
  export const GraduationCap: LucideIcon
  export const Layers: LucideIcon
  export const LayoutDashboard: LucideIcon
  export const Lock: LucideIcon
  export const MapPin: LucideIcon
  export const Plug: LucideIcon
  export const Quote: LucideIcon
  export const Server: LucideIcon
  export const ShieldCheck: LucideIcon
  export const ShoppingBag: LucideIcon
  export const Sparkles: LucideIcon
  export const Star: LucideIcon
  export const Stethoscope: LucideIcon
  export const Store: LucideIcon
  export const Target: LucideIcon
  export const TrendingUp: LucideIcon
  export const Users: LucideIcon
  export const Workflow: LucideIcon
}
