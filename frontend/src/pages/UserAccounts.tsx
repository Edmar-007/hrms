import { useEffect, useState } from 'react'
import { Search } from 'lucide-react'
import type { AxiosError } from 'axios'
import { useSearchParams } from 'react-router-dom'
import { toast } from 'react-hot-toast'
import { Button } from '@/components/ui/button'
import TablePagination from '@/components/ui/table-pagination'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import api from '@/lib/api'

interface UserRow {
  id: number
  email: string
  role: string
  isActive: boolean
  lastLogin: string
  name: string
  createdAt: string
}

interface EmployeeOption {
  id: number
  name: string
  email: string
}

const PAGE_SIZE_OPTIONS = [10, 20, 50]

export default function UserAccounts() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [rows, setRows] = useState<UserRow[]>([])
  const [employees, setEmployees] = useState<EmployeeOption[]>([])
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [totalRows, setTotalRows] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const debouncedQuery = useDebouncedValue(query, 300)
  const [form, setForm] = useState({
    employeeId: '',
    email: '',
    role: 'Employee',
    password: '',
  })

  const loadUsers = async (nextPage = page, nextPageSize = pageSize, nextSearch = debouncedQuery.trim()) => {
    try {
      setIsLoading(true)
      const { data } = await api.get('/user-accounts.php', {
        params: {
          page: nextPage,
          limit: nextPageSize,
          search: nextSearch || undefined,
        },
      })
      setRows(Array.isArray(data?.users) ? data.users : [])
      setEmployees(Array.isArray(data?.employees) ? data.employees : [])
      const total = Number(data?.total ?? 0)
      const lastPage = Math.max(1, Math.ceil(total / nextPageSize))
      setTotalRows(total)
      if (nextPage > lastPage) {
        setPage(lastPage)
      }
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to load user accounts.')
      setRows([])
      setEmployees([])
      setTotalRows(0)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    void loadUsers(page, pageSize, debouncedQuery.trim())
  }, [page, pageSize, debouncedQuery])

  useEffect(() => {
    if (searchParams.get('quickAdd') === 'add-user') {
      setShowForm(true)
      setSearchParams({}, { replace: true })
    }
  }, [searchParams, setSearchParams])

  const handleEmployeePick = (employeeId: string) => {
    setForm((prev) => ({ ...prev, employeeId }))
    const selected = employees.find((emp) => String(emp.id) === employeeId)
    if (selected) {
      setForm((prev) => ({ ...prev, employeeId, email: selected.email }))
    }
  }

  const createUser = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    if (!form.email || !form.password || !form.role) {
      toast.error('Email, role and password are required.')
      return
    }

    try {
      setIsSaving(true)
      await api.post('/user-accounts.php', {
        employeeId: form.employeeId ? Number(form.employeeId) : null,
        email: form.email,
        role: form.role,
        password: form.password,
      })
      toast.success('User account created.')
      setShowForm(false)
      setForm({ employeeId: '', email: '', role: 'Employee', password: '' })
      setPage(1)
      await loadUsers(1, pageSize, debouncedQuery.trim())
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to create account. Admin/HR role may be required.')
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="page-enter space-y-6">
      <section className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-3xl tracking-tight text-slate-900">User Accounts</h2>
          <p className="mt-1 text-sm text-slate-600">Manage roles, permissions, and account access.</p>
        </div>

        <div className="flex items-center gap-2">
          <Button className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-slate-700 hover:bg-slate-100" onClick={() => toast.success('Role matrix is part of account role assignments.')}>Role Matrix</Button>
          <Button className="h-10 rounded-xl bg-slate-900 px-4 text-white hover:bg-slate-800" onClick={() => setShowForm(true)}>Add User</Button>
        </div>
      </section>

      <section className="surface-card surface-card-hover p-5">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="relative w-full max-w-md">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm text-slate-700 outline-none transition focus:border-sky-400 focus:bg-white"
              placeholder="Search name, email, role..."
              type="text"
              value={query}
              onChange={(e) => {
                setQuery(e.target.value)
                setPage(1)
              }}
            />
          </div>
          <p className="text-sm text-slate-500">{totalRows} accounts</p>
        </div>

        {isLoading ? (
          <p className="py-8 text-center text-sm text-slate-500">Loading user accounts...</p>
        ) : rows.length === 0 ? (
          <p className="py-8 text-center text-sm text-slate-500">No user accounts found.</p>
        ) : (
          <>
            <div className="overflow-x-auto rounded-xl border border-slate-200">
            <div className="max-h-[60vh] overflow-y-auto">
              <table className="w-full min-w-[920px] text-left text-sm">
              <thead className="sticky top-0 z-10 bg-white/95 backdrop-blur">
                <tr className="border-b border-slate-200 text-xs uppercase tracking-[0.1em] text-slate-500">
                  <th className="px-3 py-3 font-semibold">Name</th>
                  <th className="px-3 py-3 font-semibold">Email</th>
                  <th className="px-3 py-3 font-semibold">Role</th>
                  <th className="px-3 py-3 font-semibold">Status</th>
                  <th className="px-3 py-3 font-semibold">Last Login</th>
                  <th className="px-3 py-3 font-semibold">Created</th>
                  <th className="px-3 py-3 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr className="border-b border-slate-100 hover:bg-slate-50/70" key={row.id}>
                    <td className="px-3 py-3 font-medium text-slate-900">{row.name}</td>
                    <td className="px-3 py-3 text-slate-700">{row.email}</td>
                    <td className="px-3 py-3 text-slate-700">{row.role}</td>
                    <td className="px-3 py-3">
                      <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                        {row.isActive ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-3 py-3 text-slate-700">{row.lastLogin ? new Date(row.lastLogin).toLocaleString() : '-'}</td>
                    <td className="px-3 py-3 text-slate-700">{new Date(row.createdAt).toLocaleDateString()}</td>
                    <td className="px-3 py-3 text-right">
                      <button
                        className="inline-flex h-8 items-center rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        onClick={() => toast('Account management actions will be added next.', { icon: 'i' })}
                        type="button"
                      >
                        Manage
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
              itemLabel="accounts"
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

      {showForm ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
          <button
            aria-label="Close modal"
            className="absolute inset-0 bg-slate-950/45 backdrop-blur-[2px]"
            onClick={() => setShowForm(false)}
            type="button"
          />

          <div className="relative z-10 w-full max-w-md hrms-slide-up overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_60px_rgba(15,23,42,0.25)]">
            <form onSubmit={createUser}>
              <div className="border-b border-slate-100 px-5 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <h3 className="text-xl font-semibold tracking-tight text-slate-900">Add User Account</h3>
                    <p className="mt-1 text-xs text-slate-500">Create access for employees and assign a role.</p>
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

              <div className="space-y-3 px-5 py-4">
                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Link Employee (optional)</label>
                  <select
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    value={form.employeeId}
                    onChange={(e) => handleEmployeePick(e.target.value)}
                  >
                    <option value="">Unlinked account</option>
                    {employees.map((emp) => (
                      <option key={emp.id} value={emp.id}>{emp.name} ({emp.email})</option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Email</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    required
                    type="email"
                    value={form.email}
                    onChange={(e) => setForm((prev) => ({ ...prev, email: e.target.value }))}
                  />
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Role</label>
                    <select
                      className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                      value={form.role}
                      onChange={(e) => setForm((prev) => ({ ...prev, role: e.target.value }))}
                    >
                      <option>Employee</option>
                      <option>Manager</option>
                      <option>HR Officer</option>
                      <option>Admin</option>
                      <option>Super Admin</option>
                    </select>
                  </div>

                  <div>
                    <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Password</label>
                    <input
                      className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                      minLength={6}
                      required
                      type="password"
                      value={form.password}
                      onChange={(e) => setForm((prev) => ({ ...prev, password: e.target.value }))}
                    />
                  </div>
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
                  {isSaving ? 'Creating...' : 'Create User'}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}
    </div>
  )
}
