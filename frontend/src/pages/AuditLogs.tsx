import { useEffect, useState } from 'react'
import type { AxiosError } from 'axios'
import { Search } from 'lucide-react'
import { toast } from 'react-hot-toast'
import TablePagination from '@/components/ui/table-pagination'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import api from '@/lib/api'

interface AuditLogRow {
  id: number
  actor: string
  action: string
  entityType: string
  entityId: number | null
  ipAddress: string
  createdAt: string
}

const PAGE_SIZE_OPTIONS = [10, 20, 50]

export default function AuditLogs() {
  const [rows, setRows] = useState<AuditLogRow[]>([])
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [totalRows, setTotalRows] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const debouncedQuery = useDebouncedValue(query, 300)

  const loadLogs = async (nextPage = page, nextPageSize = pageSize, nextSearch = debouncedQuery.trim()) => {
    try {
      setIsLoading(true)
      const { data } = await api.get('/audit-logs.php', {
        params: {
          page: nextPage,
          limit: nextPageSize,
          search: nextSearch || undefined,
        },
      })
      setRows(Array.isArray(data?.logs) ? data.logs : [])
      const total = Number(data?.total ?? 0)
      const lastPage = Math.max(1, Math.ceil(total / nextPageSize))
      setTotalRows(total)
      if (nextPage > lastPage) {
        setPage(lastPage)
      }
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to load audit logs.')
      setRows([])
      setTotalRows(0)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void loadLogs(page, pageSize, debouncedQuery.trim())
  }, [page, pageSize, debouncedQuery])

  return (
    <div className="page-enter space-y-6">
      <section className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-3xl tracking-tight text-slate-900">Audit Logs</h2>
          <p className="mt-1 text-sm text-slate-600">Track user activity and security-related events.</p>
        </div>
        <p className="rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700">{totalRows} events</p>
      </section>

      <section className="surface-card surface-card-hover p-5">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="relative w-full max-w-md">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm text-slate-700 outline-none transition focus:border-sky-400 focus:bg-white"
              placeholder="Search actor, action, entity, IP..."
              type="text"
              value={query}
              onChange={(e) => {
                setQuery(e.target.value)
                setPage(1)
              }}
            />
          </div>
        </div>

        {isLoading ? (
          <p className="py-8 text-center text-sm text-slate-500">Loading audit logs...</p>
        ) : rows.length === 0 ? (
          <p className="py-8 text-center text-sm text-slate-500">No audit logs found.</p>
        ) : (
          <>
            <div className="overflow-x-auto rounded-xl border border-slate-200">
            <div className="max-h-[60vh] overflow-y-auto">
              <table className="w-full min-w-[980px] text-left text-sm">
              <thead className="sticky top-0 z-10 bg-white/95 backdrop-blur">
                <tr className="border-b border-slate-200 text-xs uppercase tracking-[0.1em] text-slate-500">
                  <th className="px-3 py-3 font-semibold">Timestamp</th>
                  <th className="px-3 py-3 font-semibold">Actor</th>
                  <th className="px-3 py-3 font-semibold">Action</th>
                  <th className="px-3 py-3 font-semibold">Entity</th>
                  <th className="px-3 py-3 font-semibold">IP Address</th>
                  <th className="px-3 py-3 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr className="border-b border-slate-100 hover:bg-slate-50/70" key={row.id}>
                    <td className="px-3 py-3 text-slate-700">{new Date(row.createdAt).toLocaleString()}</td>
                    <td className="px-3 py-3 font-medium text-slate-900">{row.actor}</td>
                    <td className="px-3 py-3 text-slate-700">{row.action}</td>
                    <td className="px-3 py-3 text-slate-700">
                      {row.entityType || '-'} {row.entityId ? `#${row.entityId}` : ''}
                    </td>
                    <td className="px-3 py-3 text-slate-700">{row.ipAddress || '-'}</td>
                    <td className="px-3 py-3 text-right">
                      <button
                        className="inline-flex h-8 items-center rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        onClick={() => toast(`Audit event #${row.id}`, { icon: 'i' })}
                        type="button"
                      >
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
              itemLabel="events"
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
    </div>
  )
}
