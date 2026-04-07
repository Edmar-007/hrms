import { useEffect, useMemo, useState } from 'react'
import type { AxiosError } from 'axios'
import { CalendarDays, Eye, FileText, UserCircle2 } from 'lucide-react'
import { Search } from 'lucide-react'
import { toast } from 'react-hot-toast'
import api from '@/lib/api'
import { useSearchParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import AppModal from '@/components/ui/app-modal'
import TablePagination from '@/components/ui/table-pagination'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'

interface LeaveRequestRow {
  id: number
  employeeId: number
  employeeCode: string
  employeeName: string
  leaveType: string
  startDate: string
  endDate: string
  reason: string
  status: 'pending' | 'approved' | 'rejected' | string
  approvedBy: string
  approvedAt: string
  createdAt: string
}

interface LeaveTypeOption {
  id: number
  name: string
}

const PAGE_SIZE_OPTIONS = [10, 20, 50]

function statusClass(status: string) {
  if (status === 'approved') return 'bg-emerald-100 text-emerald-700'
  if (status === 'rejected') return 'bg-rose-100 text-rose-700'
  return 'bg-amber-100 text-amber-700'
}

export default function LeaveRequests() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [rows, setRows] = useState<LeaveRequestRow[]>([])
  const [leaveTypes, setLeaveTypes] = useState<LeaveTypeOption[]>([])
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [totalRows, setTotalRows] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [selectedRow, setSelectedRow] = useState<LeaveRequestRow | null>(null)
  const debouncedQuery = useDebouncedValue(query, 300)
  const [form, setForm] = useState({
    leaveTypeId: '',
    startDate: new Date().toISOString().slice(0, 10),
    endDate: new Date().toISOString().slice(0, 10),
    reason: '',
  })

  const loadRequests = async (nextPage = page, nextPageSize = pageSize, nextSearch = debouncedQuery.trim()) => {
    try {
      setIsLoading(true)
      const { data } = await api.get('/leave-requests.php', {
        params: {
          page: nextPage,
          limit: nextPageSize,
          search: nextSearch || undefined,
        },
      })
      setRows(Array.isArray(data?.requests) ? data.requests : [])
      setLeaveTypes(Array.isArray(data?.leaveTypes) ? data.leaveTypes : [])
      const total = Number(data?.total ?? 0)
      const lastPage = Math.max(1, Math.ceil(total / nextPageSize))
      setTotalRows(total)
      if (nextPage > lastPage) {
        setPage(lastPage)
      }
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to load leave requests.')
      setRows([])
      setLeaveTypes([])
      setTotalRows(0)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void loadRequests(page, pageSize, debouncedQuery.trim())
  }, [page, pageSize, debouncedQuery])

  useEffect(() => {
    if (searchParams.get('quickAdd') === 'primary') {
      setShowForm(true)
      setSearchParams({}, { replace: true })
    }
  }, [searchParams, setSearchParams])

  const pendingCount = totalRows > 0 ? rows.filter((row) => row.status === 'pending').length : 0
  const selectedInitials = useMemo(() => {
    if (!selectedRow?.employeeName) return 'NA'
    const parts = selectedRow.employeeName.trim().split(/\s+/)
    const first = parts[0]?.[0] ?? ''
    const second = parts[1]?.[0] ?? ''
    return `${first}${second}`.toUpperCase() || 'NA'
  }, [selectedRow])

  const selectedLeaveDays = useMemo(() => {
    if (!selectedRow?.startDate || !selectedRow?.endDate) return null
    const start = new Date(selectedRow.startDate)
    const end = new Date(selectedRow.endDate)
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null
    const diff = Math.floor((end.getTime() - start.getTime()) / 86400000) + 1
    return diff > 0 ? diff : null
  }, [selectedRow])

  const submitLeaveRequest = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()

    if (!form.leaveTypeId || !form.startDate || !form.endDate) {
      toast.error('Leave type and date range are required.')
      return
    }

    try {
      setIsSaving(true)
      await api.post('/leave-requests.php', {
        leaveTypeId: Number(form.leaveTypeId),
        startDate: form.startDate,
        endDate: form.endDate,
        reason: form.reason,
      })

      toast.success('Leave request submitted.')
      setShowForm(false)
      setForm({
        leaveTypeId: '',
        startDate: new Date().toISOString().slice(0, 10),
        endDate: new Date().toISOString().slice(0, 10),
        reason: '',
      })
      setPage(1)
      await loadRequests(1, pageSize, debouncedQuery.trim())
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to submit leave request.')
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="page-enter space-y-6">
      <section className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-3xl tracking-tight text-slate-900">Leave Requests</h2>
          <p className="mt-1 text-sm text-slate-600">Review and monitor employee leave submissions.</p>
        </div>
        <div className="flex items-center gap-2">
          <p className="rounded-xl bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
            {pendingCount} pending on page
          </p>
          <Button className="h-10 rounded-xl bg-slate-900 px-4 text-white hover:bg-slate-800" onClick={() => setShowForm(true)}>
            Submit Leave
          </Button>
        </div>
      </section>

      <section className="surface-card surface-card-hover p-5">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="relative w-full max-w-md">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm text-slate-700 outline-none transition focus:border-sky-400 focus:bg-white"
              placeholder="Search employee, leave type, status..."
              type="text"
              value={query}
              onChange={(e) => {
                setQuery(e.target.value)
                setPage(1)
              }}
            />
          </div>
          <p className="text-sm text-slate-500">{totalRows} requests</p>
        </div>

        {isLoading ? (
          <p className="py-8 text-center text-sm text-slate-500">Loading leave requests...</p>
        ) : rows.length === 0 ? (
          <p className="py-8 text-center text-sm text-slate-500">No leave requests found.</p>
        ) : (
          <>
            <div className="overflow-x-auto rounded-xl border border-slate-200">
            <div className="max-h-[60vh] overflow-y-auto">
              <table className="w-full min-w-[980px] text-left text-sm">
              <thead className="sticky top-0 z-10 bg-white/95 backdrop-blur">
                <tr className="border-b border-slate-200 text-xs uppercase tracking-[0.1em] text-slate-500">
                  <th className="px-3 py-3 font-semibold">Employee</th>
                  <th className="px-3 py-3 font-semibold">Leave Type</th>
                  <th className="px-3 py-3 font-semibold">Date Range</th>
                  <th className="px-3 py-3 font-semibold">Status</th>
                  <th className="px-3 py-3 font-semibold">Approved By</th>
                  <th className="px-3 py-3 font-semibold">Created</th>
                  <th className="px-3 py-3 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr className="border-b border-slate-100 hover:bg-slate-50/70" key={row.id}>
                    <td className="px-3 py-3">
                      <p className="font-semibold text-slate-900">{row.employeeName}</p>
                      <p className="text-xs text-slate-500">{row.employeeCode}</p>
                    </td>
                    <td className="px-3 py-3 text-slate-700">{row.leaveType}</td>
                    <td className="px-3 py-3 text-slate-700">
                      {new Date(row.startDate).toLocaleDateString()} - {new Date(row.endDate).toLocaleDateString()}
                    </td>
                    <td className="px-3 py-3">
                      <span className={[
                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                        statusClass(row.status),
                      ].join(' ')}>
                        {row.status}
                      </span>
                    </td>
                    <td className="px-3 py-3 text-slate-700">{row.approvedBy || '-'}</td>
                    <td className="px-3 py-3 text-slate-700">{new Date(row.createdAt).toLocaleDateString()}</td>
                    <td className="px-3 py-3 text-right">
                      <button
                        className="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        onClick={() => setSelectedRow(row)}
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
              itemLabel="requests"
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
            <form onSubmit={submitLeaveRequest}>
              <div className="border-b border-slate-100 px-5 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <h3 className="text-xl font-semibold tracking-tight text-slate-900">Submit Leave Request</h3>
                    <p className="mt-1 text-xs text-slate-500">Create a leave request from the React app.</p>
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
                  Fill in leave type, dates, and reason to submit for approval.
                </div>

                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Leave Type</label>
                  <select
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    required
                    value={form.leaveTypeId}
                    onChange={(e) => setForm((prev) => ({ ...prev, leaveTypeId: e.target.value }))}
                  >
                    <option value="">Select leave type</option>
                    {leaveTypes.map((type) => (
                      <option key={type.id} value={type.id}>{type.name}</option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Start Date</label>
                    <input
                      className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                      required
                      type="date"
                      value={form.startDate}
                      onChange={(e) => setForm((prev) => ({ ...prev, startDate: e.target.value }))}
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">End Date</label>
                    <input
                      className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                      required
                      type="date"
                      value={form.endDate}
                      onChange={(e) => setForm((prev) => ({ ...prev, endDate: e.target.value }))}
                    />
                  </div>
                </div>

                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Reason</label>
                  <textarea
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    rows={3}
                    value={form.reason}
                    onChange={(e) => setForm((prev) => ({ ...prev, reason: e.target.value }))}
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
                  {isSaving ? 'Submitting...' : 'Submit Leave'}
                </button>
              </div>
            </form>
      </AppModal>

      <AppModal className="max-w-lg" onClose={() => setSelectedRow(null)} open={!!selectedRow}>
            <div className="border-b border-slate-100 px-5 py-4">
              <h3 className="text-xl font-semibold tracking-tight text-slate-900">Leave Request Detail</h3>
            </div>
            <div className="space-y-4 px-5 py-4 text-sm text-slate-700">
              <div className="rounded-xl border border-slate-200 bg-gradient-to-r from-slate-50 to-sky-50 px-4 py-3">
                <div className="flex items-center justify-between gap-3">
                  <div className="flex items-center gap-3">
                    <div className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">
                      {selectedInitials}
                    </div>
                    <div>
                      <p className="text-sm font-semibold text-slate-900">{selectedRow?.employeeName}</p>
                      <p className="text-xs text-slate-500">{selectedRow?.employeeCode}</p>
                    </div>
                  </div>
                  <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${statusClass(selectedRow?.status ?? 'pending')}`}>
                    {selectedRow?.status}
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                  <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500"><UserCircle2 className="h-3.5 w-3.5" /> Leave Type</p>
                  <p className="font-semibold text-slate-800">{selectedRow?.leaveType || '-'}</p>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                  <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500"><CalendarDays className="h-3.5 w-3.5" /> Date Range</p>
                  <p className="font-semibold text-slate-800">{selectedRow ? `${selectedRow.startDate} - ${selectedRow.endDate}` : '-'}</p>
                </div>
                <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 sm:col-span-2">
                  <p className="mb-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Duration</p>
                  <p className="font-semibold text-slate-800">{selectedLeaveDays ? `${selectedLeaveDays} day(s)` : 'Not available'}</p>
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                <p className="mb-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Approved By</p>
                <p className="font-semibold text-slate-800">{selectedRow?.approvedBy || 'Pending approval'}</p>
              </div>

              <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500"><FileText className="h-3.5 w-3.5" /> Reason</p>
                <p>{selectedRow?.reason?.trim() ? selectedRow.reason : 'No reason provided.'}</p>
              </div>
            </div>
            <div className="flex items-center justify-end border-t border-slate-100 px-5 py-4">
              <button
                className="h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                onClick={() => setSelectedRow(null)}
                type="button"
              >
                Close
              </button>
            </div>
      </AppModal>
    </div>
  )
}
