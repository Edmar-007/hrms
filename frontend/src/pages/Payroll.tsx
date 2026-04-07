import { CalendarClock, CheckCircle2, Download, FileText, Wallet } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { toast } from 'react-hot-toast'
import { useEffect, useMemo, useState } from 'react'
import type { AxiosError } from 'axios'
import { useSearchParams } from 'react-router-dom'
import api from '@/lib/api'

interface PayrollRun {
  id: string
  periodStart: string
  periodEnd: string
  preparedBy: string
  employees: number
  amount: number
  status: string
  processedAt: string
  createdAt: string
}

function statusClass(status: string) {
  if (status === 'Completed') {
    return 'bg-emerald-100 text-emerald-700'
  }

  if (status === 'Draft') {
    return 'bg-slate-200 text-slate-700'
  }

  return 'bg-amber-100 text-amber-700'
}

function downloadCsv(fileName: string, rows: Array<Record<string, string | number>>) {
  if (rows.length === 0) return

  const headers = Object.keys(rows[0])
  const lines = [
    headers.join(','),
    ...rows.map((row) => headers.map((key) => `"${String(row[key])}"`).join(',')),
  ]

  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = fileName
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

export default function Payroll() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [payrollRows, setPayrollRows] = useState<PayrollRun[]>([])
  const [isLoading, setIsLoading] = useState(true)

  const loadPayrollRuns = async () => {
    try {
      setIsLoading(true)
      const { data } = await api.get('/payroll-process.php?action=list')
      setPayrollRows(Array.isArray(data?.runs) ? data.runs : [])
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to load payroll runs.')
      setPayrollRows([])
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadPayrollRuns()
  }, [])

  const cycleCards = useMemo(() => {
    const now = new Date()
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1)
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)
    const totalAmount = payrollRows.reduce((sum, row) => sum + row.amount, 0)
    const latestRun = payrollRows[0]

    return [
      {
        title: 'Current Cycle',
        value: `${firstDay.toLocaleDateString()} - ${lastDay.toLocaleDateString()}`,
        note: 'Current payroll month',
        icon: CalendarClock,
        tint: 'from-sky-500 to-blue-600',
      },
      {
        title: 'Recorded Payout',
        value: new Intl.NumberFormat('en-PH', {
          style: 'currency',
          currency: 'PHP',
          maximumFractionDigits: 0,
        }).format(totalAmount),
        note: `${payrollRows.length} recorded payroll runs`,
        icon: Wallet,
        tint: 'from-emerald-500 to-teal-600',
      },
      {
        title: 'Latest Status',
        value: latestRun ? latestRun.status : 'No records',
        note: latestRun ? `Prepared by ${latestRun.preparedBy}` : 'Process payroll to generate records',
        icon: CheckCircle2,
        tint: 'from-amber-500 to-orange-500',
      },
    ]
  }, [payrollRows])

  const handleGenerateDraft = () => {
    toast('Use the payroll processor workflow to generate new records.', { icon: 'i' })
  }

  const handleExport = () => {
    downloadCsv(
      'payroll-runs.csv',
      payrollRows.map((row) => ({
        id: row.id,
        periodStart: row.periodStart,
        periodEnd: row.periodEnd,
        preparedBy: row.preparedBy,
        employees: row.employees,
        amount: row.amount,
        status: row.status,
      })),
    )
    toast.success('Payroll CSV exported.')
  }

  useEffect(() => {
    if (searchParams.get('quickAdd') === 'draft') {
      handleGenerateDraft()
      setSearchParams({}, { replace: true })
    }
  }, [searchParams, setSearchParams])

  return (
    <div className="page-enter space-y-6">
      <section className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-3xl tracking-tight text-slate-900">Payroll</h2>
          <p className="mt-1 text-sm text-slate-600">
            Review payroll cycles, payouts, and approval progress.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Button
            className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-slate-700 hover:bg-slate-100"
            onClick={handleGenerateDraft}
          >
            <FileText className="mr-2 h-4 w-4" />
            Generate Draft
          </Button>
          <Button
            className="h-10 rounded-xl bg-slate-900 px-4 text-white hover:bg-slate-800"
            onClick={handleExport}
          >
            <Download className="mr-2 h-4 w-4" />
            Export Payroll
          </Button>
        </div>
      </section>

      <section className="stagger-children grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {cycleCards.map((card) => {
          const Icon = card.icon
          return (
            <article className="surface-card surface-card-hover p-5" key={card.title}>
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-slate-500">{card.title}</p>
                <div
                  className={[
                    'flex h-9 w-9 items-center justify-center rounded-xl text-white',
                    'bg-gradient-to-br',
                    card.tint,
                  ].join(' ')}
                >
                  <Icon className="h-4 w-4" />
                </div>
              </div>
              <p className="mt-3 text-2xl font-semibold tracking-tight text-slate-900">{card.value}</p>
              <p className="mt-1 text-xs text-slate-500">{card.note}</p>
            </article>
          )
        })}
      </section>

      <section className="surface-card surface-card-hover p-5">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Recent Payroll Runs</h3>
          <p className="text-sm text-slate-500">From payroll records</p>
        </div>

        {isLoading ? (
          <p className="py-8 text-center text-sm text-slate-500">Loading payroll runs...</p>
        ) : payrollRows.length === 0 ? (
          <p className="py-8 text-center text-sm text-slate-500">No payroll records found yet.</p>
        ) : (
          <div className="overflow-x-auto">
            <div className="max-h-[60vh] overflow-y-auto">
              <table className="w-full min-w-[760px] text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs uppercase tracking-[0.1em] text-slate-500">
                  <th className="px-3 py-3 font-semibold">Run ID</th>
                  <th className="px-3 py-3 font-semibold">Period</th>
                  <th className="px-3 py-3 font-semibold">Prepared By</th>
                  <th className="px-3 py-3 font-semibold">Employees</th>
                  <th className="px-3 py-3 font-semibold">Amount</th>
                  <th className="px-3 py-3 font-semibold">Status</th>
                  <th className="px-3 py-3 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {payrollRows.map((row) => (
                  <tr className="border-b border-slate-100 hover:bg-slate-50/70" key={`${row.id}-${row.periodStart}`}>
                    <td className="px-3 py-3 font-medium text-slate-900">{row.id}</td>
                    <td className="px-3 py-3 text-slate-700">
                      {new Date(row.periodStart).toLocaleDateString()} - {new Date(row.periodEnd).toLocaleDateString()}
                    </td>
                    <td className="px-3 py-3 text-slate-700">{row.preparedBy}</td>
                    <td className="px-3 py-3 text-slate-700">{row.employees}</td>
                    <td className="px-3 py-3 text-slate-700">
                      {new Intl.NumberFormat('en-PH', {
                        style: 'currency',
                        currency: 'PHP',
                        maximumFractionDigits: 2,
                      }).format(row.amount)}
                    </td>
                    <td className="px-3 py-3">
                      <span
                        className={[
                          'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                          statusClass(row.status),
                        ].join(' ')}
                      >
                        {row.status}
                      </span>
                    </td>
                    <td className="px-3 py-3 text-right">
                      <button
                        className="inline-flex h-8 items-center rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        onClick={() => toast(`Run ${row.id} for ${row.employees} employees`, { icon: 'i' })}
                        type="button"
                      >
                        Details
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
              </table>
            </div>
          </div>
        )}
      </section>
    </div>
  )
}
