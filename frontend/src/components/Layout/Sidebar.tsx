import { Link, useLocation } from 'react-router-dom'
import { useAppearance } from '@/hooks/useAppearance'
import { navSections } from './navConfig'
import { usePrefetchHRMS } from '@/hooks/useHRMSData'

interface SidebarProps {
  collapsed: boolean
}

export default function Sidebar({ collapsed }: SidebarProps) {
  const { isLoading } = useAppearance()
  const location = useLocation()
  const { prefetch } = usePrefetchHRMS()

  if (isLoading) return null

  return (
    <aside
      className={[
        'app-sidebar hidden h-full shrink-0 border-r border-slate-200/80 transition-[width] duration-200 lg:flex lg:flex-col',
        collapsed ? 'w-20' : 'w-72',
      ].join(' ')}
    >
      <div className={['border-b border-slate-200 py-5', collapsed ? 'px-3' : 'px-6'].join(' ')}>
        <p className={['app-shell-kicker text-xs font-semibold uppercase tracking-[0.14em]', collapsed ? 'text-center' : ''].join(' ')}>
          {collapsed ? 'HR' : 'HRMS Suite'}
        </p>
        <h1 className={['app-shell-title mt-1 font-display text-2xl', collapsed ? 'text-center text-base' : ''].join(' ')}>
          {collapsed ? 'CC' : 'Control Center'}
        </h1>
      </div>

      <nav className={['flex-1 space-y-4 overflow-y-auto py-4', collapsed ? 'px-2' : 'px-4'].join(' ')}>
        {navSections.map((section) => (
          <div key={section.label}>
            {!collapsed && (
              <p className="app-sidebar-section-label px-4 pb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                {section.label}
              </p>
            )}
            <div className="space-y-1">
              {section.items.map((item) => {
                const Icon = item.icon
                const active = location.pathname.startsWith(item.to)

                return (
                  <Link
                    className={[
                      'group rounded-xl text-sm font-medium no-underline transition-all duration-200 hover:no-underline',
                      collapsed
                        ? 'flex justify-center px-2 py-3'
                        : 'flex items-center gap-3 px-4 py-2.5',
                      active
                        ? 'bg-[linear-gradient(90deg,#e0f2fe_0%,#dbeafe_100%)] text-slate-900 shadow-sm ring-1 ring-sky-100'
                        : 'app-sidebar-link',
                    ].join(' ')}
                    key={item.label}
                    onMouseEnter={() => prefetch(item.to)}
                    title={collapsed ? item.label : undefined}
                    to={item.to}
                  >
                    <Icon className="h-4 w-4 text-slate-400 group-hover:text-slate-700" />
                    {!collapsed && item.label}
                  </Link>
                )
              })}
            </div>
          </div>
        ))}
      </nav>
    </aside>
  )
}
