import { useEffect, useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useAppearance } from '@/hooks/useAppearance'
import NavBar from './NavBar'
import Sidebar from './Sidebar'
import LoadingSpinner from '@/components/ui/LoadingSpinner'

const SIDEBAR_COLLAPSE_STORAGE_KEY = 'hrms.sidebar-collapsed'

export default function Layout() {
  const { isAuthenticated, isLoading: authLoading } = useAuth()
  const { isLoading: appearanceLoading } = useAppearance()
  const location = useLocation()
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(() => {
    if (typeof window === 'undefined') {
      return false
    }
    return window.localStorage.getItem(SIDEBAR_COLLAPSE_STORAGE_KEY) === 'true'
  })

  useEffect(() => {
    window.localStorage.setItem(SIDEBAR_COLLAPSE_STORAGE_KEY, String(isSidebarCollapsed))
  }, [isSidebarCollapsed])

  const isMicroFinanceAnalyticsPage = location.pathname.startsWith('/hr-analytics')

  if (authLoading || appearanceLoading) {
    return (
      <div className="flex h-screen items-center justify-center bg-gradient-to-br from-slate-50 to-slate-100">
        <LoadingSpinner />
      </div>
    )
  }

  if (!isAuthenticated) return null // ProtectedRoute handles redirect

  return (
    <div
      className={[
        'app-bg flex h-screen min-w-0 overflow-hidden',
        isMicroFinanceAnalyticsPage ? 'app-bg-microfinance' : '',
      ].join(' ')}
    >
      <Sidebar collapsed={isSidebarCollapsed} />

      <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <section className="app-shell-navbar-section flex-none">
          <NavBar
            isSidebarCollapsed={isSidebarCollapsed}
            onToggleSidebar={() => setIsSidebarCollapsed((current) => !current)}
          />
        </section>
        <section className="app-shell-body-section flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
          <main
            className={[
              'app-main-shell page-enter min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-6 lg:p-8',
              isMicroFinanceAnalyticsPage ? 'app-main-shell-microfinance' : '',
            ].join(' ')}
          >
            <Outlet />
          </main>
        </section>
      </div>
    </div>
  )
}

