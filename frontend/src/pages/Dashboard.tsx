import { Area, AreaChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { useEffect, useMemo, useState } from 'react'
import { Briefcase, CalendarCheck2, DollarSign, Users } from 'lucide-react'
import type { AxiosError } from 'axios'
import { toast } from 'react-hot-toast'
import api from '@/lib/api'

const attendanceSeries = [
  { day: 'Mon', present: 94 },
  { day: 'Tue', present: 98 },
  { day: 'Wed', present: 101 },
  { day: 'Thu', present: 96 },
  { day: 'Fri', present: 103 },
  { day: 'Sat', present: 72 },
]

export default function Dashboard() {
  const [stats, setStats] = useState({
    activeEmployees: 0,
    todayAttendance: 0,
    pendingLeaves: 0,
    activeUsers: 0,
  })

  useEffect(() => {
    const loadStats = async () => {
      try {
        const { data } = await api.get('/dashboard-stats.php')
        setStats({
          activeEmployees: Number(data?.activeEmployees ?? 0),
          todayAttendance: Number(data?.todayAttendance ?? 0),
          pendingLeaves: Number(data?.pendingLeaves ?? 0),
          activeUsers: Number(data?.activeUsers ?? 0),
        })
      } catch (error) {
        const message = (error as AxiosError<{ error?: string }>).response?.data?.error
        toast.error(message ?? 'Unable to load dashboard statistics.')
      }
    }

    loadStats()
  }, [])

  const dynamicStatCards = useMemo(
    () => [
      {
        title: 'Total Employees',
        value: String(stats.activeEmployees),
        delta: 'Active employees',
        icon: Users,
        tint: 'from-sky-500 to-blue-600',
      },
      {
        title: 'Present Today',
        value: String(stats.todayAttendance),
        delta: 'Attendance records today',
        icon: CalendarCheck2,
        tint: 'from-emerald-500 to-teal-600',
      },
      {
        title: 'Pending Leaves',
        value: String(stats.pendingLeaves),
        delta: 'For review',
        icon: DollarSign,
        tint: 'from-violet-500 to-indigo-600',
      },
      {
        title: 'Active Users',
        value: String(stats.activeUsers),
        delta: 'Can access the system',
        icon: Briefcase,
        tint: 'from-orange-500 to-amber-500',
      },
    ],
    [stats],
  )

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-display text-3xl tracking-tight text-slate-900">Dashboard</h2>
        <p className="mt-1 text-sm text-slate-600">
          Overview of workforce, attendance, and payroll status.
        </p>
      </div>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {dynamicStatCards.map((card) => {
          const Icon = card.icon
          return (
            <article className="surface-card surface-card-hover p-5" key={card.title}>
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-slate-500">{card.title}</p>
                <div
                  className={`flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br ${card.tint} text-white`}
                >
                  <Icon className="h-4 w-4" />
                </div>
              </div>
              <p className="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{card.value}</p>
              <p className="mt-1 text-xs text-slate-500">{card.delta}</p>
            </article>
          )
        })}
      </section>

      <section className="grid gap-4 xl:grid-cols-3">
        <article className="surface-card p-5 xl:col-span-2">
          <div className="mb-3 flex items-center justify-between">
            <h3 className="text-lg font-semibold text-slate-900">Weekly Attendance Trend</h3>
            <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
              +4.2% vs last week
            </span>
          </div>
          <div className="h-[280px] w-full">
            <ResponsiveContainer height="100%" width="100%">
              <AreaChart data={attendanceSeries} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="attendanceGradient" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="5%" stopColor="#0ea5e9" stopOpacity={0.42} />
                    <stop offset="95%" stopColor="#0ea5e9" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <XAxis
                  dataKey="day"
                  tick={{ fill: '#64748b', fontSize: 12 }}
                  tickLine={false}
                  axisLine={false}
                />
                <YAxis
                  tick={{ fill: '#64748b', fontSize: 12 }}
                  tickLine={false}
                  axisLine={false}
                  width={34}
                />
                <Tooltip
                  contentStyle={{
                    borderRadius: '12px',
                    border: '1px solid #e2e8f0',
                    boxShadow: '0 10px 35px rgba(15,23,42,0.08)',
                    fontSize: '12px',
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="present"
                  stroke="#0284c7"
                  strokeWidth={2.5}
                  fill="url(#attendanceGradient)"
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </article>

        <article className="surface-card p-5">
          <h3 className="text-lg font-semibold text-slate-900">Today's Highlights</h3>
          <ul className="mt-4 space-y-3 text-sm text-slate-600">
            <li className="rounded-xl bg-slate-50 px-4 py-3">9 employees on leave</li>
            <li className="rounded-xl bg-slate-50 px-4 py-3">Payroll approval pending for Finance</li>
            <li className="rounded-xl bg-slate-50 px-4 py-3">3 new applicants scheduled for interview</li>
            <li className="rounded-xl bg-slate-50 px-4 py-3">Compliance report due in 5 days</li>
          </ul>
        </article>
      </section>
    </div>
  )
}
