import { useEffect, useMemo, useState } from 'react'
import type { AxiosError } from 'axios'
import { CalendarDays, Eye, FileText, Search, UserCircle2, Wallet } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import { toast } from 'react-hot-toast'
import { Button } from '@/components/ui/button'
import AppModal from '@/components/ui/app-modal'
import TablePagination from '@/components/ui/table-pagination'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import api from '@/lib/api'
import { resolveLegacyUrl } from '@/lib/legacyUrl'

interface Claim {
  id: number
  claimNumber: string
  title: string
  amount: number
  currency: string
  status: string
  employeeName: string
  createdAt: string
}

const PAGE_SIZE_OPTIONS = [10, 20, 50]

export default function ExpenseClaims() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [rows, setRows] = useState<Claim[]>([])
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [totalRows, setTotalRows] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const [approvalsUrl, setApprovalsUrl] = useState('/hrms/modules/claims/index.php')
  const [selectedClaim, setSelectedClaim] = useState<Claim | null>(null)
  const debouncedQuery = useDebouncedValue(query, 300)
  const [form, setForm] = useState({ title: '', amount: '', description: '' })

  const loadClaims = async (nextPage = page, nextPageSize = pageSize, nextSearch = debouncedQuery.trim()) => {
    try {
      setIsLoading(true)
      const { data } = await api.get('/expense-claims.php', {
        params: {
          page: nextPage,
          limit: nextPageSize,
          search: nextSearch || undefined,
        },
      })
      setRows(Array.isArray(data?.claims) ? data.claims : [])
      if (typeof data?.legacyApprovalsUrl === 'string') setApprovalsUrl(data.legacyApprovalsUrl)
      const total = Number(data?.total ?? 0)
      const lastPage = Math.max(1, Math.ceil(total / nextPageSize))
      setTotalRows(total)
      if (nextPage > lastPage) {
        setPage(lastPage)
      }
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to load claims.')
      setRows([])
      setTotalRows(0)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void loadClaims(page, pageSize, debouncedQuery.trim())
  }, [page, pageSize, debouncedQuery])

  useEffect(() => {
    if (searchParams.get('quickAdd') === 'submit-claim') {
      setShowForm(true)
      setSearchParams({}, { replace: true })
    }
  }, [searchParams, setSearchParams])

  const selectedInitials = useMemo(() => {
    if (!selectedClaim?.employeeName) return 'NA'
    const parts = selectedClaim.employeeName.trim().split(/\s+/)
    const first = parts[0]?.[0] ?? ''
    const second = parts[1]?.[0] ?? ''
    return `${first}${second}`.toUpperCase() || 'NA'
  }, [selectedClaim])

  const submitClaim = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const amount = Number(form.amount)
    if (!form.title || !amount || amount <= 0) {
      toast.error('Title and valid amount are required.')
      return
    }

    try {
      setIsSaving(true)
      const { data } = await api.post('/expense-claims.php', {
        title: form.title,
        amount,
        description: form.description,
      })

      if (data?.claim) {
        setPage(1)
        await loadClaims(1, pageSize, debouncedQuery.trim())
      } else {
        await loadClaims(page, pageSize, debouncedQuery.trim())
      }

      toast.success('Expense claim submitted.')
      setShowForm(false)
      setForm({ title: '', amount: '', description: '' })
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to submit claim right now.')
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="page-enter space-y-6">
      <section className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-3xl tracking-tight text-slate-900">Expense Claims</h2>
          <p className="mt-1 text-sm text-slate-600">Process reimbursement claims and expense approvals.</p>
        </div>

        <div className="flex items-center gap-2">
          <Button className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-slate-700 hover:bg-slate-100" onClick={() => (window.location.href = resolveLegacyUrl(approvalsUrl))}>
            Open Approvals
          </Button>
          <Button className="h-10 rounded-xl bg-slate-900 px-4 text-white hover:bg-slate-800" onClick={() => setShowForm(true)}>
            Submit Claim
          </Button>
        </div>
      </section>

      <section className="surface-card surface-card-hover p-5">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="relative w-full max-w-md">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm text-slate-700 outline-none transition focus:border-sky-400 focus:bg-white"
              placeholder="Search claim #, title, employee, status..."
              type="text"
              value={query}
              onChange={(e) => {
                setQuery(e.target.value)
                setPage(1)
              }}
            />
          </div>
          <p className="text-sm text-slate-500">{totalRows} claims</p>
        </div>

        {isLoading ? (
          <p className="py-8 text-center text-sm text-slate-500">Loading claims...</p>
        ) : rows.length === 0 ? (
          <p className="py-8 text-center text-sm text-slate-500">No claims yet. Click Submit Claim to add one.</p>
        ) : (
          <>
            <div className="overflow-x-auto rounded-xl border border-slate-200">
            <div className="max-h-[60vh] overflow-y-auto">
              <table className="w-full min-w-[900px] text-left text-sm">
              <thead className="sticky top-0 z-10 bg-white/95 backdrop-blur">
                <tr className="border-b border-slate-200 text-xs uppercase tracking-[0.1em] text-slate-500">
                  <th className="px-3 py-3 font-semibold">Claim #</th>
                  <th className="px-3 py-3 font-semibold">Title</th>
                  <th className="px-3 py-3 font-semibold">Employee</th>
                  <th className="px-3 py-3 font-semibold">Amount</th>
                  <th className="px-3 py-3 font-semibold">Status</th>
                  <th className="px-3 py-3 font-semibold">Date</th>
                  <th className="px-3 py-3 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr className="border-b border-slate-100 hover:bg-slate-50/70" key={row.id}>
                    <td className="px-3 py-3 font-medium text-slate-900">{row.claimNumber}</td>
                    <td className="px-3 py-3 text-slate-700">{row.title}</td>
                    <td className="px-3 py-3 text-slate-700">{row.employeeName}</td>
                    <td className="px-3 py-3 text-slate-700">{row.currency} {Number(row.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td className="px-3 py-3">
                      <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{row.status}</span>
                    </td>
                    <td className="px-3 py-3 text-slate-700">{new Date(row.createdAt).toLocaleDateString()}</td>
                    <td className="px-3 py-3 text-right">
                      <button
                        className="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        onClick={() => setSelectedClaim(row)}
                        type="button"
                      >
                        <Eye className="h-3.5 w-3.5" />
                        View
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
              </table>
            </div>
            </div>
            <TablePagination
              currentCount={rows.length}
              isLoading={isLoading}
              itemLabel="claims"
              onPageChange={setPage}
              onPageSizeChange={(nextPageSize) => {
                setPageSize(nextPageSize)
                setPage(1)
              }}
              page={page}
              pageSize={pageSize}
              pageSizeOptions={PAGE_SIZE_OPTIONS}
              total={totalRows}
            />
          </>
        )}
      </section>

      <AppModal className="max-w-md" onClose={() => setShowForm(false)} open={showForm}>
            <form onSubmit={submitClaim}>
              <div className="border-b border-slate-100 px-5 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <h3 className="text-xl font-semibold tracking-tight text-slate-900">Submit Expense Claim</h3>
                    <p className="mt-1 text-xs text-slate-500">Create a reimbursement request for review.</p>
                  </div>
                  <button
                    aria-label="Close"
                    className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    onClick={() => setShowForm(false)}
                    type="button"
                  >
                    x
                  </button>
                </div>
              </div>

              <div className="space-y-4 px-5 py-4">
                <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-700">
                  Add claim title, amount, and optional notes for reimbursement processing.
                </div>

                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Title</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    required
                    type="text"
                    value={form.title}
                    onChange={(e) => setForm((prev) => ({ ...prev, title: e.target.value }))}
                  />
                </div>

                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Amount (PHP)</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    min="0.01"
                    required
                    step="0.01"
                    type="number"
                    value={form.amount}
                    onChange={(e) => setForm((prev) => ({ ...prev, amount: e.target.value }))}
                  />
                </div>

                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Description</label>
                  <textarea
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    rows={3}
                    value={form.description}
                    onChange={(e) => setForm((prev) => ({ ...prev, description: e.target.value }))}
                  />
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <button
                  className="h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                  onClick={() => setShowForm(false)}
                  type="button"
                >
                  Cancel
                </button>
                <button
                  className="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                  disabled={isSaving}
                  type="submit"
                >
                  {isSaving ? 'Submitting...' : 'Submit Claim'}
                </button>
              </div>
            </form>
      </AppModal>

      <AppModal className="max-w-lg" onClose={() => setSelectedClaim(null)} open={!!selectedClaim}>
            <div className="border-b border-slate-100 px-5 py-4">
              <h3 className="text-xl font-semibold tracking-tight text-slate-900">Claim Detail</h3>
            </div>
            <div className="space-y-4 px-5 py-4 text-sm text-slate-700">
              <div className="rounded-xl border border-slate-200 bg-gradient-to-r from-slate-50 to-sky-50 px-4 py-3">
                <div className="flex items-center justify-between gap-3">
                  <div className="flex items-center gap-3">
                    <div className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">
                      {selectedInitials}
                    </div>
                    <div>
                      <p className="text-sm font-semibold text-slate-900">{selectedClaim?.employeeName}</p>
                      <p className="text-xs text-slate-500">{selectedClaim?.claimNumber}</p>
                    </div>
                  </div>
                  <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                    {selectedClaim?.status}
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                  <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500"><Wallet className="h-3.5 w-3.5" /> Title</p>
                  <p className="font-semibold text-slate-800">{selectedClaim?.title || '-'}</p>
                </div>
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                  <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-emerald-700"><UserCircle2 className="h-3.5 w-3.5" /> Amount</p>
                  <p className="font-semibold text-emerald-800">
                    {selectedClaim?.currency} {Number(selectedClaim?.amount ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </p>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 sm:col-span-2">
                  <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500"><CalendarDays className="h-3.5 w-3.5" /> Submitted</p>
                  <p className="font-semibold text-slate-800">{selectedClaim ? new Date(selectedClaim.createdAt).toLocaleString() : '-'}</p>
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500"><FileText className="h-3.5 w-3.5" /> Claim Summary</p>
                <p>{selectedClaim?.title?.trim() ? selectedClaim.title : 'No summary provided.'}</p>
              </div>
            </div>
            <div className="flex items-center justify-end border-t border-slate-100 px-5 py-4">
              <button
                className="h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                onClick={() => setSelectedClaim(null)}
                type="button"
              >
                Close
              </button>
            </div>
      </AppModal>
    </div>
  )
}
