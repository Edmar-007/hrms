import { useEffect, useMemo, useState } from 'react'
import type { AxiosError } from 'axios'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Line,
  LineChart,
  Pie,
  PieChart,
  RadialBar,
  RadialBarChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { ArrowUpRight, BadgeCheck, CalendarDays, Download, Landmark, Target, TrendingUp, Users } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { toast } from 'react-hot-toast'
import api from '@/lib/api'

interface Summary {
  totalEmployees: number
  activeEmployees: number
  inactiveEmployees: number
}

type RangeKey = 'month' | 'quarter' | 'ytd'
type Status = 'On Route' | 'High Performer' | 'Coaching' | 'On Track'

interface Point { label: string; value: number }
interface RoleSlice { role: string; value: number; color: string }
interface BranchAttendanceRow { branch: string; rate: number }
interface BranchProductivityRow { branch: string; loans: number }
interface PersonRow {
  employeeName: string
  branch: string
  role: string
  attendance: number
  loansProcessed: number
  lastActivity: string
  status: Status
  accent: string
}

interface AnalyticsView {
  note: string
  totalEmployees: number
  activeFieldStaff: number
  attendanceRate: number
  productivityAverage: number
  attendanceGoal: number
  spark: {
    employees: Point[]
    field: Point[]
    attendance: Point[]
    productivity: Point[]
  }
  monthlyTrend: { month: string; rate: number }[]
  attendanceByBranch: BranchAttendanceRow[]
  productivityByBranch: BranchProductivityRow[]
  roleDistribution: RoleSlice[]
  performanceRows: PersonRow[]
}

interface AnalyticsApiResponse {
  summary: Summary
  analytics: AnalyticsView
  range: RangeKey
}

const rangeOptions: { key: RangeKey; label: string }[] = [
  { key: 'month', label: 'This Month' },
  { key: 'quarter', label: 'This Quarter' },
  { key: 'ytd', label: 'YTD' },
]

function downloadAnalyticsCsv(rows: PersonRow[], range: RangeKey) {
  const header = 'Employee Name,Branch,Role,Attendance %,Loans Processed,Last Activity,Status'
  const lines = rows.map((row) => [row.employeeName, row.branch, row.role, `${row.attendance}%`, row.loansProcessed, row.lastActivity, row.status].map((value) => `"${String(value)}"`).join(','))
  const blob = new Blob([[header, ...lines].join('\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `microfinance-hr-analytics-${range}.csv`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

function getStatusClasses(status: Status) {
  if (status === 'High Performer') return 'bg-emerald-100 text-emerald-800'
  if (status === 'On Route') return 'bg-teal-100 text-teal-800'
  if (status === 'Coaching') return 'bg-amber-100 text-amber-800'
  return 'bg-slate-200 text-slate-700'
}

function AnalyticsSparkline({ data }: { data: Point[] }) {
  return (
    <div className="h-14 w-24">
      <ResponsiveContainer height="100%" width="100%">
        <LineChart data={data}>
          <Line dataKey="value" dot={false} stroke="#007C6E" strokeWidth={2} type="monotone" />
        </LineChart>
      </ResponsiveContainer>
    </div>
  )
}

export default function HRAnalytics() {
  const [selectedRange, setSelectedRange] = useState<RangeKey>('month')
  const [view, setView] = useState<AnalyticsView | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    const loadAnalytics = async () => {
      try {
        setIsLoading(true)
        const { data } = await api.get<AnalyticsApiResponse>('/hr-analytics.php', {
          params: { range: selectedRange },
        })
        setView(data?.analytics ?? null)
      } catch (error) {
        const message = (error as AxiosError<{ error?: string }>).response?.data?.error
        toast.error(message ?? 'Unable to load HR analytics.')
      }
      finally {
        setIsLoading(false)
      }
    }
    loadAnalytics()
  }, [selectedRange])

  const kpis = useMemo(() => {
    if (!view) {
      return []
    }

    return [
      { title: 'Total Employees', value: String(view.totalEmployees), context: 'MicroFinance workforce', delta: 'Live company headcount', icon: Users, spark: view.spark.employees },
      { title: 'Active Field Staff', value: String(view.activeFieldStaff), context: 'Loan officers and collectors deployed', delta: 'Pulled from branch profiles', icon: Landmark, spark: view.spark.field },
      { title: 'Overall Attendance Rate', value: `${view.attendanceRate}%`, context: 'Daily branch attendance compliance', delta: 'Average from stored branch metrics', icon: BadgeCheck, spark: view.spark.attendance },
      { title: 'Average Productivity', value: `${view.productivityAverage}`, context: 'Loans per officer', delta: 'Average from branch productivity logs', icon: TrendingUp, spark: view.spark.productivity },
    ]
  }, [view])

  if (isLoading && !view) {
    return (
      <div className="page-enter mx-auto max-w-[1560px]">
        <section className="rounded-[32px] border border-[#DCE8DD] bg-[linear-gradient(135deg,#FFFDF8_0%,#F8F6F2_52%,#F1F7EF_100%)] px-8 py-16 text-center shadow-[0_24px_60px_rgba(11,75,44,0.08)]">
          <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[#007C6E]">MicroFinance Analytics</p>
          <h2 className="mt-3 font-display text-3xl tracking-tight text-[#103B2C]">Loading database-backed analytics...</h2>
          <p className="mt-2 text-sm text-[#5B7066]">Preparing attendance, branch, and manpower insights.</p>
        </section>
      </div>
    )
  }

  if (!view) {
    return (
      <div className="page-enter mx-auto max-w-[1560px]">
        <section className="rounded-[32px] border border-[#DCE8DD] bg-white px-8 py-16 text-center shadow-[0_24px_60px_rgba(11,75,44,0.08)]">
          <h2 className="font-display text-3xl tracking-tight text-[#103B2C]">HR Analytics</h2>
          <p className="mt-2 text-sm text-[#5B7066]">No analytics data is available yet.</p>
        </section>
      </div>
    )
  }

  return (
    <div className="page-enter mx-auto max-w-[1560px] space-y-6">
      <section className="overflow-hidden rounded-[32px] border border-[#DCE8DD] bg-[linear-gradient(135deg,#FFFDF8_0%,#F8F6F2_52%,#F1F7EF_100%)] shadow-[0_24px_60px_rgba(11,75,44,0.08)]">
        <div className="flex flex-col gap-6 px-6 py-6 lg:px-8 lg:py-7">
          <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex items-start gap-4">
              <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-[20px] bg-[linear-gradient(145deg,#34C261_0%,#007C6E_100%)] text-white shadow-[0_18px_35px_rgba(0,124,110,0.28)]">
                <span className="text-lg font-bold tracking-[0.12em]">MF</span>
              </div>
              <div className="min-w-0">
                <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[#007C6E]">MicroFinance Logo</p>
                <h2 className="mt-2 font-display text-3xl tracking-tight text-[#103B2C]">HR Analytics &amp; Reports - MicroFinance</h2>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-[#4D655A]">
                  Manpower and field staff management at a glance, designed for branch operations, collection mobility,
                  and workforce productivity coaching.
                </p>
              </div>
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="inline-flex rounded-2xl border border-[#DDE7D9] bg-white/90 p-1 shadow-[0_12px_24px_rgba(17,85,53,0.06)]">
                {rangeOptions.map((option) => (
                  <button
                    key={option.key}
                    className={[
                      'rounded-[14px] px-4 py-2 text-sm font-semibold transition-all',
                      selectedRange === option.key
                        ? 'bg-[linear-gradient(135deg,#34C261_0%,#008C45_100%)] text-white shadow-[0_10px_24px_rgba(52,194,97,0.22)]'
                        : 'text-[#5D7268] hover:bg-[#F3F8F1]',
                    ].join(' ')}
                    onClick={() => setSelectedRange(option.key)}
                    type="button"
                  >
                    {option.label}
                  </button>
                ))}
              </div>

              <Button
                className="h-11 rounded-2xl border border-[#CFE2D1] bg-white px-5 text-[#103B2C] shadow-[0_10px_24px_rgba(17,85,53,0.06)] hover:bg-[#F4FAF1]"
                onClick={() => {
                  downloadAnalyticsCsv(view.performanceRows, selectedRange)
                  toast.success('Analytics export downloaded.')
                }}
                type="button"
                variant="outline"
              >
                <Download className="mr-2 h-4 w-4" />
                Export
              </Button>
            </div>
          </div>

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div className="rounded-[28px] border border-[#E1EBDF] bg-white/88 p-5 shadow-[0_18px_36px_rgba(17,85,53,0.06)]">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[#008C45]">People Pulse</p>
                  <p className="mt-1 text-base font-semibold text-[#123728]">{view.note}</p>
                </div>
                <div className="inline-flex items-center gap-2 rounded-full bg-[#ECF8EF] px-3 py-1 text-xs font-semibold text-[#0E7A45]">
                  <TrendingUp className="h-3.5 w-3.5" />
                  Growth-oriented operations
                </div>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3 xl:grid-cols-3">
              <article className="rounded-[24px] border border-[#DAE7D8] bg-white/88 p-4 shadow-[0_16px_32px_rgba(17,85,53,0.05)]">
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[#7C8F85]">Snapshot</p>
                <p className="mt-2 text-2xl font-semibold text-[#103B2C]">8</p>
                <p className="mt-1 text-sm text-[#5B7066]">Branches with field deployment</p>
              </article>
              <article className="rounded-[24px] border border-[#DAE7D8] bg-white/88 p-4 shadow-[0_16px_32px_rgba(17,85,53,0.05)]">
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[#7C8F85]">Mobility</p>
                <p className="mt-2 text-2xl font-semibold text-[#103B2C]">87%</p>
                <p className="mt-1 text-sm text-[#5B7066]">Field routes completed on schedule</p>
              </article>
              <article className="rounded-[24px] border border-[#DAE7D8] bg-white/88 p-4 shadow-[0_16px_32px_rgba(17,85,53,0.05)]">
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[#7C8F85]">Engagement</p>
                <p className="mt-2 text-2xl font-semibold text-[#103B2C]">4.6/5</p>
                <p className="mt-1 text-sm text-[#5B7066]">Supervisor confidence score</p>
              </article>
            </div>
          </div>
        </div>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {kpis.map((card) => {
          const Icon = card.icon
          return (
            <article className="rounded-[28px] border border-[#DDE8DC] bg-white p-5 shadow-[0_18px_38px_rgba(17,85,53,0.05)]" key={card.title}>
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-sm font-medium text-[#6E8178]">{card.title}</p>
                  <p className="mt-3 text-4xl font-semibold tracking-tight text-[#123728]">{card.value}</p>
                  <p className="mt-2 text-sm text-[#70847A]">{card.context}</p>
                </div>
                <div className="flex h-12 w-12 items-center justify-center rounded-[18px] bg-[linear-gradient(145deg,#ECF8EF_0%,#E2F4EE_100%)] text-[#007C6E]">
                  <Icon className="h-5 w-5" />
                </div>
              </div>
              <div className="mt-4 flex items-end justify-between gap-4">
                <div className="inline-flex items-center gap-1 rounded-full bg-[#EEF8F1] px-3 py-1 text-xs font-semibold text-[#0F7C48]">
                  <ArrowUpRight className="h-3.5 w-3.5" />
                  {card.delta}
                </div>
                <AnalyticsSparkline data={card.spark} />
              </div>
            </article>
          )
        })}
      </section>

      <section className="grid gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(320px,0.95fr)]">
        <div className="min-w-0 space-y-4">
          <article className="rounded-[32px] border border-[#DCE8DD] bg-white p-6 shadow-[0_20px_44px_rgba(17,85,53,0.06)]">
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
              <div>
                <h3 className="text-xl font-semibold text-[#123728]">Monthly Attendance Trend (Last 12 Months)</h3>
                <p className="mt-1 text-sm text-[#667A70]">Branch attendance consistency for field-heavy operations.</p>
              </div>
              <div className="inline-flex items-center gap-2 rounded-full bg-[#EEF8F1] px-3 py-1 text-xs font-semibold text-[#0E7A45]">
                <CalendarDays className="h-3.5 w-3.5" />
                Stable manpower reliability
              </div>
            </div>
            <div className="h-[320px]">
              <ResponsiveContainer height="100%" width="100%">
                <LineChart data={view.monthlyTrend} margin={{ top: 8, right: 12, left: -16, bottom: 0 }}>
                  <CartesianGrid stroke="#E4ECE4" strokeDasharray="4 4" vertical={false} />
                  <XAxis axisLine={false} dataKey="month" tick={{ fill: '#718579', fontSize: 12 }} tickLine={false} />
                  <YAxis axisLine={false} domain={[86, 97]} tick={{ fill: '#718579', fontSize: 12 }} tickFormatter={(value) => `${value}%`} tickLine={false} width={46} />
                  <Tooltip contentStyle={{ borderRadius: '18px', border: '1px solid #D8E6D8', background: '#FFFDF9', boxShadow: '0 18px 42px rgba(17,85,53,0.12)' }} formatter={(value: number) => [`${value.toFixed(1)}%`, 'Attendance']} />
                  <Line activeDot={{ r: 6, fill: '#34C261', stroke: '#ffffff', strokeWidth: 2 }} dataKey="rate" dot={{ r: 4, strokeWidth: 2, fill: '#F8F6F2', stroke: '#008C45' }} stroke="#008C45" strokeWidth={3} type="monotone" />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </article>

          <article className="rounded-[32px] border border-[#DCE8DD] bg-white p-6 shadow-[0_20px_44px_rgba(17,85,53,0.06)]">
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
              <div>
                <h3 className="text-xl font-semibold text-[#123728]">Attendance Rate by Branch</h3>
                <p className="mt-1 text-sm text-[#667A70]">Operational discipline across branch teams and field assignments.</p>
              </div>
              <span className="rounded-full bg-[#FFF3DF] px-3 py-1 text-xs font-semibold text-[#9B6A1E]">Goal-aware branch view</span>
            </div>
            <div className="h-[280px]">
              <ResponsiveContainer height="100%" width="100%">
                <BarChart data={view.attendanceByBranch} margin={{ top: 6, right: 12, left: -20, bottom: 0 }}>
                  <CartesianGrid stroke="#E9EFE8" strokeDasharray="4 4" vertical={false} />
                  <XAxis axisLine={false} dataKey="branch" tick={{ fill: '#718579', fontSize: 12 }} tickLine={false} />
                  <YAxis axisLine={false} domain={[85, 100]} tick={{ fill: '#718579', fontSize: 12 }} tickFormatter={(value) => `${value}%`} tickLine={false} width={44} />
                  <Tooltip contentStyle={{ borderRadius: '18px', border: '1px solid #D8E6D8', background: '#FFFDF9', boxShadow: '0 18px 42px rgba(17,85,53,0.12)' }} formatter={(value: number) => [`${value.toFixed(1)}%`, 'Attendance']} />
                  <Bar dataKey="rate" fill="#34C261" radius={[12, 12, 4, 4]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </article>
        </div>

        <div className="min-w-0 space-y-4">
          <article className="rounded-[32px] border border-[#DCE8DD] bg-white p-6 shadow-[0_20px_44px_rgba(17,85,53,0.06)]">
            <div className="mb-5">
              <h3 className="text-xl font-semibold text-[#123728]">Employee Distribution by Role</h3>
              <p className="mt-1 text-sm text-[#667A70]">A balanced view of frontline and support manpower.</p>
            </div>
            <div className="grid items-center gap-4 lg:grid-cols-[190px,1fr] xl:grid-cols-1 2xl:grid-cols-[190px,1fr]">
              <div className="mx-auto h-[220px] w-[220px]">
                <ResponsiveContainer height="100%" width="100%">
                  <PieChart>
                    <Pie data={view.roleDistribution} dataKey="value" innerRadius={55} nameKey="role" outerRadius={86} paddingAngle={2}>
                      {view.roleDistribution.map((entry) => <Cell fill={entry.color} key={entry.role} />)}
                    </Pie>
                    <Tooltip contentStyle={{ borderRadius: '18px', border: '1px solid #D8E6D8', background: '#FFFDF9', boxShadow: '0 18px 42px rgba(17,85,53,0.12)' }} formatter={(value: number, _name, payload) => [`${value}`, payload?.payload?.role ?? 'Role']} />
                  </PieChart>
                </ResponsiveContainer>
              </div>
              <div className="space-y-3">
                {view.roleDistribution.map((role) => (
                  <div className="flex items-center justify-between rounded-2xl bg-[#FBFAF7] px-4 py-3" key={role.role}>
                    <div className="flex items-center gap-3">
                      <span className="h-3 w-3 rounded-full" style={{ backgroundColor: role.color }} />
                      <span className="text-sm font-medium text-[#28483B]">{role.role}</span>
                    </div>
                    <span className="text-sm font-semibold text-[#123728]">{role.value}</span>
                  </div>
                ))}
              </div>
            </div>
          </article>

          <article className="rounded-[32px] border border-[#DCE8DD] bg-white p-6 shadow-[0_20px_44px_rgba(17,85,53,0.06)]">
            <div className="mb-5">
              <h3 className="text-xl font-semibold text-[#123728]">Productivity by Branch</h3>
              <p className="mt-1 text-sm text-[#667A70]">Average loans processed per active officer.</p>
            </div>
            <div className="h-[240px]">
              <ResponsiveContainer height="100%" width="100%">
                <BarChart data={view.productivityByBranch} layout="vertical" margin={{ top: 0, right: 12, left: 10, bottom: 0 }}>
                  <CartesianGrid horizontal={false} stroke="#E9EFE8" strokeDasharray="4 4" />
                  <XAxis axisLine={false} tick={{ fill: '#718579', fontSize: 12 }} tickLine={false} type="number" />
                  <YAxis axisLine={false} dataKey="branch" tick={{ fill: '#718579', fontSize: 12 }} tickLine={false} type="category" width={90} />
                  <Tooltip contentStyle={{ borderRadius: '18px', border: '1px solid #D8E6D8', background: '#FFFDF9', boxShadow: '0 18px 42px rgba(17,85,53,0.12)' }} formatter={(value: number) => [`${value}`, 'Loans / Officer']} />
                  <Bar dataKey="loans" fill="#007C6E" radius={[0, 12, 12, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </article>

          <article className="rounded-[32px] border border-[#DCE8DD] bg-white p-6 shadow-[0_20px_44px_rgba(17,85,53,0.06)]">
            <div className="mb-5 flex items-center justify-between gap-3">
              <div>
                <h3 className="text-xl font-semibold text-[#123728]">Attendance Goal (95%)</h3>
                <p className="mt-1 text-sm text-[#667A70]">Gauge view for current attendance delivery.</p>
              </div>
              <div className="rounded-full bg-[#ECF8EF] px-3 py-1 text-xs font-semibold text-[#0E7A45]">
                {view.attendanceRate >= view.attendanceGoal ? 'Goal met' : `${(view.attendanceGoal - view.attendanceRate).toFixed(1)} pts to target`}
              </div>
            </div>
            <div className="grid items-center gap-4 sm:grid-cols-[180px,1fr] xl:grid-cols-1 2xl:grid-cols-[180px,1fr]">
              <div className="relative mx-auto h-[180px] w-[180px]">
                <ResponsiveContainer height="100%" width="100%">
                    <RadialBarChart barSize={16} cx="50%" cy="50%" data={[{ name: 'Attendance', value: view.attendanceRate, fill: '#34C261' }]} endAngle={-270} innerRadius="70%" outerRadius="100%" startAngle={90}>
                      <RadialBar background cornerRadius={10} dataKey="value" />
                    </RadialBarChart>
                </ResponsiveContainer>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                  <p className="text-3xl font-semibold text-[#123728]">{view.attendanceRate}%</p>
                  <p className="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-[#718579]">Current</p>
                </div>
              </div>
              <div className="space-y-3">
                <div className="rounded-2xl bg-[#FBFAF7] px-4 py-3">
                  <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[#7A8D84]">Goal Guidance</p>
                  <p className="mt-1 text-sm text-[#29463A]">Focus route coaching on Tacloban and Iloilo to close the remaining attendance gap.</p>
                </div>
                <div className="flex items-center gap-2 rounded-2xl bg-[#EEF8F1] px-4 py-3 text-sm font-medium text-[#0E7A45]">
                  <Target className="h-4 w-4" />
                  Target remains achievable this period.
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section className="rounded-[32px] border border-[#DCE8DD] bg-white p-6 shadow-[0_20px_44px_rgba(17,85,53,0.06)]">
        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 className="text-xl font-semibold text-[#123728]">Recent Manpower Performance</h3>
            <p className="mt-1 text-sm text-[#667A70]">Latest performance and movement of branch teams and field staff.</p>
          </div>
          <div className="inline-flex items-center gap-2 rounded-full bg-[#FFF3DF] px-3 py-1 text-xs font-semibold text-[#9B6A1E]">
            <TrendingUp className="h-3.5 w-3.5" />
            Actionable branch staffing insights
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[980px] text-left">
            <thead>
              <tr className="border-b border-[#E4ECE3] text-xs uppercase tracking-[0.14em] text-[#80938A]">
                <th className="px-3 py-3 font-semibold">Employee Name</th>
                <th className="px-3 py-3 font-semibold">Branch</th>
                <th className="px-3 py-3 font-semibold">Role</th>
                <th className="px-3 py-3 font-semibold">Attendance %</th>
                <th className="px-3 py-3 font-semibold">Loans Processed</th>
                <th className="px-3 py-3 font-semibold">Last Activity</th>
                <th className="px-3 py-3 text-right font-semibold">Status</th>
              </tr>
            </thead>
            <tbody>
              {view.performanceRows.map((row) => {
                const initials = row.employeeName.split(' ').slice(0, 2).map((part) => part.charAt(0)).join('')
                return (
                  <tr className="border-b border-[#EDF2EC] hover:bg-[#FBFAF7]" key={`${row.employeeName}-${row.branch}`}>
                    <td className="px-3 py-4">
                      <div className="flex items-center gap-3">
                        <div className={`flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br ${row.accent} text-sm font-semibold text-white shadow-[0_10px_22px_rgba(17,85,53,0.18)]`}>
                          {initials}
                        </div>
                        <div>
                          <p className="text-sm font-semibold text-[#16392C]">{row.employeeName}</p>
                          <p className="text-xs text-[#7B8D85]">Filipino field team member</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-3 py-4 text-sm text-[#385347]">{row.branch}</td>
                    <td className="px-3 py-4 text-sm text-[#385347]">{row.role}</td>
                    <td className="px-3 py-4">
                      <div className="flex items-center gap-3">
                        <span className="text-sm font-semibold text-[#16392C]">{row.attendance}%</span>
                        <div className="h-2 w-24 overflow-hidden rounded-full bg-[#E7EFE6]">
                          <div className="h-full rounded-full bg-[linear-gradient(90deg,#34C261_0%,#007C6E_100%)]" style={{ width: `${Math.min(row.attendance, 100)}%` }} />
                        </div>
                      </div>
                    </td>
                    <td className="px-3 py-4 text-sm font-semibold text-[#16392C]">{row.loansProcessed}</td>
                    <td className="px-3 py-4 text-sm text-[#385347]">{row.lastActivity}</td>
                    <td className="px-3 py-4 text-right">
                      <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${getStatusClasses(row.status)}`}>{row.status}</span>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  )
}
