import {
  BarChart3,
  Briefcase,
  CalendarClock,
  CircleDollarSign,
  ClipboardList,
  LayoutDashboard,
  Receipt,
  Settings,
  ShieldCheck,
  UserCog,
  type LucideIcon,
  Users,
} from 'lucide-react'

export interface NavItem {
  to: string
  label: string
  icon: LucideIcon
}

export interface NavSection {
  label: string
  items: NavItem[]
}

export const navSections: NavSection[] = [
  {
    label: 'People',
    items: [
      { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
      { to: '/employees', label: 'Employees', icon: Users },
      { to: '/attendance', label: 'Attendance', icon: CalendarClock },
      { to: '/leave-requests', label: 'Leave Requests', icon: ClipboardList },
      { to: '/expense-claims', label: 'Expense Claims', icon: Receipt },
    ],
  },
  {
    label: 'Management',
    items: [
      { to: '/user-accounts', label: 'User Accounts', icon: UserCog },
      { to: '/payroll', label: 'Payroll', icon: Briefcase },
      { to: '/reports', label: 'Reports', icon: BarChart3 },
      { to: '/hr-analytics', label: 'HR Analytics', icon: BarChart3 },
      { to: '/compensation', label: 'Compensation', icon: CircleDollarSign },
      { to: '/audit-logs', label: 'Audit Logs', icon: ShieldCheck },
    ],
  },
  {
    label: 'System',
    items: [{ to: '/settings', label: 'Settings', icon: Settings }],
  },
]
