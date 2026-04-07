import { useEffect, useState } from 'react'
import { useAppearance } from '@/hooks/useAppearance'
import { Bell, FilePlus2, LogOut, Menu, PanelLeftClose, PanelLeftOpen, Plus, QrCode, Search, User, UserPlus, X } from 'lucide-react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { toast } from 'react-hot-toast'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useAuth } from '@/hooks/useAuth'
import LoadingSpinner from '@/components/ui/LoadingSpinner'
import { useNotifications } from '@/hooks/useNotifications'
import { navSections } from './navConfig'

interface Notification {
  id: number
  title: string
  message: string
  is_read: 0 | 1
  created_at: string
  time_ago: string
}

interface NavBarProps {
  isSidebarCollapsed: boolean
  onToggleSidebar: () => void
}

export default function NavBar({ isSidebarCollapsed, onToggleSidebar }: NavBarProps) {
  const { isLoading: appearanceLoading } = useAppearance()
  const location = useLocation()
  const navigate = useNavigate()
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false)
  const isMicroFinanceAnalyticsPage = location.pathname.startsWith('/hr-analytics')

  const routeMeta = [
    { path: '/dashboard', title: 'Dashboard', subtitle: 'Overview and workforce insights' },
    { path: '/employees', title: 'Employees', subtitle: 'Team records and account actions' },
    { path: '/attendance', title: 'Attendance', subtitle: 'Daily attendance and shift activity' },
    { path: '/leave-requests', title: 'Leave Requests', subtitle: 'Approval queue and leave policies' },
    { path: '/expense-claims', title: 'Expense Claims', subtitle: 'Reimbursement and claim review' },
    { path: '/profile', title: 'My Profile', subtitle: 'Account details and preferences' },
    { path: '/user-accounts', title: 'User Accounts', subtitle: 'Role access and account controls' },
    { path: '/payroll', title: 'Payroll', subtitle: 'Cycles, approvals, and payouts' },
    { path: '/reports', title: 'Reports', subtitle: 'Analytics and export center' },
    { path: '/hr-analytics', title: 'HR Analytics & Reports', subtitle: 'MicroFinance manpower and field force insights' },
    { path: '/compensation', title: 'Compensation', subtitle: 'Compensation structures and rules' },
    { path: '/audit-logs', title: 'Audit Logs', subtitle: 'Security events and activity tracking' },
    { path: '/settings', title: 'Settings', subtitle: 'Workspace and security preferences' },
  ] as const

  const currentRoute = routeMeta.find((item) => location.pathname.startsWith(item.path)) ?? routeMeta[0]

  const searchInputClassName = [
    'h-10 w-full rounded-xl border border-slate-200/80 bg-slate-50/90',
    'pl-10 pr-16 text-sm text-slate-700 outline-none transition',
    'focus:border-sky-400 focus:bg-white',
    'focus:shadow-[0_8px_18px_rgba(59,130,246,0.12)]',
  ].join(' ')

  const { user, logout } = useAuth()
  const { notifications, unreadCount, markAllRead, isLoading } = useNotifications()

  const handleQuickAddNavigate = (path: string, label: string) => {
    navigate(path)
    toast.success(`${label} opened.`)
    setIsMobileMenuOpen(false)
  }

  useEffect(() => {
    setIsMobileMenuOpen(false)
  }, [location.pathname])

  if (appearanceLoading || !user) return null

  return (
    <>
      <header
        className={[
          'app-navbar z-30 border-b border-slate-200/80',
          isMicroFinanceAnalyticsPage ? 'app-navbar-microfinance' : '',
        ].join(' ')}
      >
        <div className="flex h-16 items-center justify-between gap-3 px-3 sm:px-6">
          <div className="flex min-w-0 flex-1 items-center gap-4">
            <div className="flex items-center gap-2 lg:hidden">
              <Button
                aria-expanded={isMobileMenuOpen}
                aria-label={isMobileMenuOpen ? 'Close navigation menu' : 'Open navigation menu'}
                className="h-10 w-10 rounded-xl"
                onClick={() => setIsMobileMenuOpen((current) => !current)}
                size="icon"
                variant="ghost"
              >
                {isMobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
              </Button>
              <div className="min-w-0">
                <p className="app-shell-title truncate text-sm font-semibold">{currentRoute.title}</p>
                <p className="app-shell-subtitle truncate text-xs">{currentRoute.subtitle}</p>
              </div>
            </div>

            <Button
              aria-label={isSidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
              className="hidden h-10 w-10 rounded-xl lg:inline-flex"
              onClick={onToggleSidebar}
              size="icon"
              type="button"
              variant="ghost"
            >
              {isSidebarCollapsed ? <PanelLeftOpen className="h-5 w-5" /> : <PanelLeftClose className="h-5 w-5" />}
            </Button>

            <div className="flex items-center gap-2 xl:hidden">
              <div className="h-8 w-8 rounded-lg bg-slate-200 xl:h-10 xl:w-10" />
            </div>

            <div className="hidden xl:block">
              {isMicroFinanceAnalyticsPage ? (
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-[linear-gradient(145deg,#34C261_0%,#007C6E_100%)] text-sm font-bold tracking-[0.12em] text-white shadow-[0_12px_28px_rgba(0,124,110,0.18)]">
                    MF
                  </div>
                  <div>
                    <p className="app-shell-title m-0 text-sm font-semibold leading-tight">{currentRoute.title}</p>
                    <p className="app-shell-subtitle m-0 text-xs leading-tight">{currentRoute.subtitle}</p>
                  </div>
                </div>
              ) : (
                <div className="h-10 w-10 rounded-lg bg-slate-200" />
              )}
              <p className="app-shell-title m-0 text-sm font-semibold leading-tight">{currentRoute.title}</p>
              <p className="app-shell-subtitle m-0 text-xs leading-tight">{currentRoute.subtitle}</p>
            </div>

            <div className="relative hidden min-w-[260px] max-w-lg flex-1 items-center lg:flex">
              <Search className="pointer-events-none absolute left-3 h-4 w-4 text-slate-400" />
              <input
                className={searchInputClassName}
                placeholder={isMicroFinanceAnalyticsPage ? 'Search branches, officers, attendance...' : 'Search employees, reports, payroll...'}
                type="text"
              />
              <span className="pointer-events-none absolute right-3 rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-[11px] font-semibold text-slate-400">
                Ctrl+K
              </span>
            </div>
          </div>

          <div className="ml-auto flex h-16 items-center gap-1 sm:gap-2">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button className="app-accent-button hidden h-10 rounded-xl px-4 text-white sm:inline-flex" variant="default">
                  <Plus className="mr-1.5 h-4 w-4" />
                  Quick Add
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" sideOffset={6}>
                <DropdownMenuLabel>Quick Add Actions</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => handleQuickAddNavigate('/employees?quickAdd=employee', 'Add Employee')}>
                  <UserPlus className="mr-2 h-4 w-4" />
                  Add Employee
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleQuickAddNavigate('/attendance', 'Attendance Scanner')}>
                  <QrCode className="mr-2 h-4 w-4" />
                  Open Attendance Scanner
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleQuickAddNavigate('/leave-requests?quickAdd=primary', 'Leave Request')}>
                  <FilePlus2 className="mr-2 h-4 w-4" />
                  Create Leave Request
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleQuickAddNavigate('/expense-claims?quickAdd=submit-claim', 'Expense Claim')}>
                  <FilePlus2 className="mr-2 h-4 w-4" />
                  Submit Expense Claim
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleQuickAddNavigate('/user-accounts?quickAdd=add-user', 'User Account')}>
                  <UserPlus className="mr-2 h-4 w-4" />
                  Add User Account
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleQuickAddNavigate('/payroll?quickAdd=draft', 'Payroll Draft')}>
                  <FilePlus2 className="mr-2 h-4 w-4" />
                  Generate Payroll Draft
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleQuickAddNavigate('/reports?quickAdd=filters', 'Report Filters')}>
                  <FilePlus2 className="mr-2 h-4 w-4" />
                  Open Report Filters
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button className="app-accent-button h-10 rounded-xl px-3 text-white sm:hidden" variant="default">
                  <Plus className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" sideOffset={6}>
                <DropdownMenuLabel>Quick Add Actions</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => handleQuickAddNavigate('/employees?quickAdd=employee', 'Add Employee')}>
                  <UserPlus className="mr-2 h-4 w-4" />
                  Add Employee
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleQuickAddNavigate('/attendance', 'Attendance Scanner')}>
                  <QrCode className="mr-2 h-4 w-4" />
                  Open Attendance Scanner
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleQuickAddNavigate('/leave-requests?quickAdd=primary', 'Leave Request')}>
                  <FilePlus2 className="mr-2 h-4 w-4" />
                  Create Leave Request
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  className="relative h-10 w-10 rounded-xl"
                  size="icon"
                  variant="ghost"
                >
                  <Bell className="h-5 w-5" />
                  {unreadCount > 0 && (
                    <Badge className="absolute -right-1 -top-1 size-5 rounded-full p-0 text-xs">
                      {unreadCount}
                    </Badge>
                  )}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-80" sideOffset={6}>
                <DropdownMenuLabel className="flex flex-col space-y-1">
                  <p className="text-sm font-medium leading-none">Notifications</p>
                  <p className="text-xs text-muted-foreground">
                    {unreadCount} unread
                  </p>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <div className="max-h-[300px] overflow-y-auto p-2">
                  {isLoading ? (
                    <div className="flex items-center justify-center p-8">
                      <LoadingSpinner />
                    </div>
                  ) : notifications.length === 0 ? (
                    <div className="flex flex-col items-center p-8 text-center">
                      <Bell className="h-8 w-8 text-muted-foreground" />
                      <p className="text-sm text-muted-foreground">No notifications</p>
                    </div>
                  ) : (
                    notifications.map((notification: Notification) => (
                      <DropdownMenuItem
                        key={notification.id}
                        className={`flex w-full cursor-pointer gap-2 p-2 ${notification.is_read === 1 ? 'opacity-50' : ''}`}
                      >
                        <div
                          className={`h-2 w-2 rounded-full ${notification.is_read === 0 ? 'bg-primary' : 'bg-muted'}`}
                        />
                        <div className="min-w-0 flex-1 truncate">
                          <p className="truncate text-sm font-medium leading-none">
                            {notification.title}
                          </p>
                          <p className="truncate text-xs text-muted-foreground">
                            {notification.message}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            {notification.time_ago}
                          </p>
                        </div>
                      </DropdownMenuItem>
                    ))
                  )}
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="cursor-pointer focus:bg-accent"
                  onClick={() => markAllRead()}
                >
                  Mark all as read
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button className="h-10 rounded-xl px-3" variant="ghost">
                  <span className="app-shell-user mr-2 hidden text-sm font-medium md:inline">
                    {user.name || 'Admin User'}
                  </span>
                  <User className="h-5 w-5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" sideOffset={5}>
                <DropdownMenuLabel>
                  <div className="flex items-center gap-2">
                    <div className="h-8 w-8 rounded-full bg-primary/10 p-1">
                      <span className="text-lg font-semibold text-primary">
                        {(user.name || 'A').charAt(0).toUpperCase()}
                      </span>
                    </div>
                    <div className="space-y-1 leading-none">
                      <p className="font-medium">{user.name || 'Admin User'}
                      </p>
                      <p className="w-[200px] truncate text-sm text-muted-foreground">
                        {user.role}
                      </p>
                    </div>
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="cursor-pointer focus:bg-destructive focus:text-destructive-foreground"
                  onClick={() => logout.mutate()}
                >
                  <LogOut className="mr-2 h-4 w-4" />
                  Logout
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </header>

      {isMobileMenuOpen && (
        <div
          className="fixed inset-0 z-20 bg-slate-950/40 backdrop-blur-[2px] lg:hidden"
          onClick={() => setIsMobileMenuOpen(false)}
        >
          <div
            className="absolute inset-x-0 top-16 max-h-[calc(100vh-4rem)] overflow-y-auto border-b border-slate-200/80 bg-white/95 px-4 py-4 shadow-2xl backdrop-blur-xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="mb-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <p className="app-shell-kicker text-xs font-semibold uppercase tracking-[0.14em]">Workspace</p>
              <p className="mt-1 text-sm font-semibold text-slate-900">Navigate your HRMS modules on any screen size.</p>
            </div>

            <nav className="space-y-4">
              {navSections.map((section) => (
                <div key={section.label}>
                  <p className="px-1 pb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                    {section.label}
                  </p>
                  <div className="space-y-2">
                    {section.items.map((item) => {
                      const Icon = item.icon
                      const active = location.pathname.startsWith(item.to)

                      return (
                        <Link
                          className={[
                            'group flex items-center justify-between rounded-2xl border px-4 py-3 text-sm font-medium transition-all',
                            active
                              ? 'border-sky-100 bg-[linear-gradient(90deg,#e0f2fe_0%,#dbeafe_100%)] text-slate-900 shadow-sm'
                              : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900',
                          ].join(' ')}
                          key={item.label}
                          to={item.to}
                        >
                          <span className="flex items-center gap-3">
                            <Icon className="h-4 w-4 text-slate-400 group-hover:text-slate-700" />
                            {item.label}
                          </span>
                          <span className="text-xs uppercase tracking-[0.12em] text-slate-400">Open</span>
                        </Link>
                      )
                    })}
                  </div>
                </div>
              ))}
            </nav>
          </div>
        </div>
      )}
    </>
  )
}
