import { useEffect, useState } from 'react'
import { Download, Pencil, QrCode, Search, Trash2, UserPlus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import TablePagination from '@/components/ui/table-pagination'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { toast } from 'react-hot-toast'
import api from '@/lib/api'
import { useEmployees } from '@/hooks/useHRMSData'
import { useSearchParams } from 'react-router-dom'
import AppModal from '@/components/ui/app-modal'
import QRCode from 'qrcode'

type EmployeeStatus = 'Active' | 'On Leave' | 'Probation' | 'Inactive'

interface Employee {
  id: number
  employeeCode: string
  name: string
  email: string
  department: string
  role: string
  status: EmployeeStatus
}

type EmployeeFormData = Omit<Employee, 'id' | 'employeeCode'>

const initialEmployees: Employee[] = [
  { id: 1001, employeeCode: 'EMP-1001', name: 'Maya Watson', email: 'maya@company.com', department: 'HR', role: 'HR Manager', status: 'Active' },
  { id: 1002, employeeCode: 'EMP-1002', name: 'John Rivera', email: 'john@company.com', department: 'Engineering', role: 'Frontend Dev', status: 'Active' },
]

const PAGE_SIZE_OPTIONS = [10, 20, 50]

function statusClasses(status: EmployeeStatus) {
  switch (status) {
    case 'Active':
      return 'bg-emerald-100 text-emerald-700'
    case 'On Leave':
      return 'bg-amber-100 text-amber-700'
    case 'Probation':
      return 'bg-sky-100 text-sky-700'
    default:
      return 'bg-slate-200 text-slate-700'
  }
}

export default function Employees() {
  const [searchParams, setSearchParams] = useSearchParams()
  const searchInputClassName = [
    'h-10 w-full rounded-xl border border-slate-200 bg-slate-50',
    'pl-10 pr-3 text-sm text-slate-700 outline-none transition',
    'focus:border-sky-400 focus:bg-white',
  ].join(' ')

  const [query, setQuery] = useState('')
  const debouncedQuery = useDebouncedValue(query, 300)
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(20)

  const { data, isLoading: isLoadingEmployees, refetch: loadEmployees } = useEmployees({
    page,
    limit: pageSize,
    search: debouncedQuery.trim(),
  })

  const [employees, setEmployees] = useState<Employee[]>(initialEmployees)
  const [totalEmployees, setTotalEmployees] = useState(0)

  useEffect(() => {
    if (data) {
      setEmployees(Array.isArray(data.employees) ? data.employees : [])
      setTotalEmployees(Number(data.total ?? 0))
    }
  }, [data])

  const [isSaving, setIsSaving] = useState(false)
  const [selectedEmployee, setSelectedEmployee] = useState<Employee | null>(null)
  const [qrEmployee, setQrEmployee] = useState<Employee | null>(null)
  const [qrImageUrl, setQrImageUrl] = useState('')
  const [isGeneratingQr, setIsGeneratingQr] = useState(false)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [isEditMode, setIsEditMode] = useState(false)
  
  const [formData, setFormData] = useState<EmployeeFormData>({
    name: '',
    email: '',
    department: '',
    role: '',
    status: 'Active',
  })

  const resetForm = () => {
    setFormData({
      name: '',
      email: '',
      department: '',
      role: '',
      status: 'Active',
    })
    setIsEditMode(false)
  }

  useEffect(() => {
    if (searchParams.get('quickAdd') === 'employee') {
      resetForm()
      setIsFormOpen(true)
      toast.success('Ready to add a new employee.')
      setSearchParams({}, { replace: true })
    }
  }, [searchParams, setSearchParams])

  const handleAddEmployee = () => {
    resetForm()
    setIsFormOpen(true)
  }

  const handleInlineEdit = (employee: Employee) => {
    setFormData({
      name: employee.name,
      email: employee.email,
      department: employee.department,
      role: employee.role,
      status: employee.status,
    })
    setSelectedEmployee(employee)
    setIsEditMode(true)
    setIsFormOpen(true)
  }

  const buildQrPayload = (employee: Employee) =>
    JSON.stringify({
      type: 'hrms_employee',
      id: employee.id,
      code: employee.employeeCode || `EMP-${employee.id}`,
      name: employee.name,
    })

  const handleOpenQr = async (employee: Employee) => {
    setQrEmployee(employee)
    setQrImageUrl('')
    setIsGeneratingQr(true)
    try {
      const url = await QRCode.toDataURL(buildQrPayload(employee), {
        width: 280,
        margin: 1,
      })
      setQrImageUrl(url)
    } catch {
      toast.error('Unable to generate employee QR code.')
    } finally {
      setIsGeneratingQr(false)
    }
  }

  const downloadQr = () => {
    if (!qrEmployee || !qrImageUrl) return
    const link = document.createElement('a')
    link.href = qrImageUrl
    link.download = `QR_${qrEmployee.employeeCode || qrEmployee.id}.png`
    link.click()
  }

  const handleDeactivateEmployee = async (employee: Employee) => {
    if (isSaving) return

    const confirmed = window.confirm(`Deactivate ${employee.name}?`)
    if (!confirmed) return

    try {
      setIsSaving(true)
      await api.delete('/employees.php', { data: { id: employee.id } })
      await loadEmployees()
      toast.success(`${employee.name} is now inactive.`)
    } catch {
      toast.error('Unable to deactivate employee.')
    } finally {
      setIsSaving(false)
    }
  }

  const handleFormSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()

    if (!formData.name || !formData.email || !formData.department || !formData.role) {
      toast.error('Please complete all fields.')
      return
    }

    void (async () => {
      try {
        setIsSaving(true)

        if (isEditMode && selectedEmployee) {
          await api.put('/employees.php', {
            id: selectedEmployee.id,
            ...formData,
          })
          await loadEmployees()
          toast.success('Employee updated.')
        } else {
          await api.post('/employees.php', formData)
          setPage(1)
          await loadEmployees()
          toast.success('Employee added.')
        }

        setIsFormOpen(false)
        resetForm()
        setSelectedEmployee(null)
      } catch {
        toast.error('Unable to save employee.')
      } finally {
        setIsSaving(false)
      }
    })()
  }

  return (
    <div className="page-enter space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-3xl tracking-tight text-slate-900">Employees</h2>
          <p className="mt-1 text-sm text-slate-600">Manage people, statuses, and account actions.</p>
        </div>
        <Button
          className="h-10 rounded-xl bg-slate-900 px-4 text-white hover:bg-slate-800"
          onClick={handleAddEmployee}
        >
          <UserPlus className="mr-2 h-4 w-4" />
          Add Employee
        </Button>
      </div>


      <section className="surface-card p-4 sm:p-5 flex flex-col gap-4">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="relative w-full max-w-md">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              className={searchInputClassName}
              onChange={(e) => {
                setQuery(e.target.value)
                setPage(1)
              }}
              placeholder="Search name, role, email, status..."
              type="text"
              value={query}
            />
          </div>
          <p className="text-sm text-slate-500">{totalEmployees} records</p>
        </div>

        <section className="relative flex-1 min-h-[300px] max-h-[60vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white">
          {isLoadingEmployees ? (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-6 py-12 text-center">
              <p className="text-base font-semibold text-slate-700">Loading employees...</p>
            </div>
          ) : employees.length === 0 ? (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-6 py-12 text-center">
              <p className="text-base font-semibold text-slate-700">No employees found</p>
              <p className="mt-1 text-sm text-slate-500">
                Try a different keyword for name, email, department, role, or status.
              </p>
            </div>
          ) : (
            <>
              <div className="overflow-x-auto">
              <table className="w-full min-w-[920px] text-left text-sm">
                <thead className="sticky top-0 z-10 bg-white/95 backdrop-blur">
                  <tr className="border-b border-slate-200 text-xs uppercase tracking-[0.1em] text-slate-500">
                    <th className="px-3 py-3 font-semibold">Employee</th>
                    <th className="px-3 py-3 font-semibold">Department</th>
                    <th className="px-3 py-3 font-semibold">Role</th>
                    <th className="px-3 py-3 font-semibold">Status</th>
                    <th className="px-3 py-3 text-right font-semibold">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {employees.map((employee) => (
                    <tr className="border-b border-slate-100 hover:bg-slate-50/70" key={employee.id}>
                      <td className="px-3 py-3">
                        <p className="font-semibold text-slate-900">{employee.name}</p>
                        <p className="text-xs text-slate-500">{employee.email}</p>
                        <p className="text-xs text-slate-500">{employee.employeeCode || `EMP-${employee.id}`}</p>
                      </td>
                      <td className="px-3 py-3 text-slate-700">{employee.department}</td>
                      <td className="px-3 py-3 text-slate-700">{employee.role}</td>
                      <td className="px-3 py-3">
                        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${statusClasses(employee.status)}`}>
                          {employee.status}
                        </span>
                      </td>
                      <td className="px-3 py-3 text-right">
                        <div className="inline-flex flex-wrap justify-end gap-1">
                          <button
                            className="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            onClick={() => handleInlineEdit(employee)}
                            title="Edit employee"
                            type="button"
                          >
                            <Pencil className="h-4 w-4" />
                          </button>
                          <button
                            className="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            onClick={() => void handleOpenQr(employee)}
                            title="View employee QR code"
                            type="button"
                          >
                            <QrCode className="h-4 w-4" />
                          </button>
                          <button
                            className="inline-flex h-8 items-center justify-center rounded-lg border border-rose-200 px-2.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                            disabled={isSaving || employee.status === 'Inactive'}
                            onClick={() => void handleDeactivateEmployee(employee)}
                            title={employee.status === 'Inactive' ? 'Employee already inactive' : 'Deactivate employee'}
                            type="button"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              </div>
              <TablePagination
                currentCount={employees.length}
                isLoading={isLoadingEmployees}
                itemLabel="records"
                onPageChange={setPage}
                onPageSizeChange={(nextPageSize) => {
                  setPageSize(nextPageSize)
                  setPage(1)
                }}
                page={page}
                pageSize={pageSize}
                pageSizeOptions={PAGE_SIZE_OPTIONS}
                total={totalEmployees}
              />
            </>
          )}
        </section>
      </section>

      <AppModal className="max-w-xl" onClose={() => {
        if (isSaving) return
        setIsFormOpen(false)
        resetForm()
        setSelectedEmployee(null)
      }} open={isFormOpen}>
            <form onSubmit={handleFormSubmit}>
              <div className="border-b border-slate-100 px-5 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <h3 className="text-xl font-semibold tracking-tight text-slate-900">
                      {isEditMode ? 'Edit Employee' : 'Add Employee'}
                    </h3>
                    <p className="mt-1 text-xs text-slate-500">Update employee profile details and status.</p>
                  </div>
                  <button
                    aria-label="Close"
                    className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-70"
                    disabled={isSaving}
                    onClick={() => {
                      setIsFormOpen(false)
                      resetForm()
                      setSelectedEmployee(null)
                    }}
                    type="button"
                  >
                    x
                  </button>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-2">
                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Full Name</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    onChange={(e) => setFormData((prev) => ({ ...prev, name: e.target.value }))}
                    required
                    type="text"
                    value={formData.name}
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Email</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    onChange={(e) => setFormData((prev) => ({ ...prev, email: e.target.value }))}
                    required
                    type="email"
                    value={formData.email}
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Department</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    onChange={(e) => setFormData((prev) => ({ ...prev, department: e.target.value }))}
                    required
                    type="text"
                    value={formData.department}
                  />
                </div>
                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Role</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    onChange={(e) => setFormData((prev) => ({ ...prev, role: e.target.value }))}
                    required
                    type="text"
                    value={formData.role}
                  />
                </div>
                <div className="sm:col-span-2">
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Status</label>
                  <select
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    onChange={(e) =>
                      setFormData((prev) => ({
                        ...prev,
                        status: e.target.value as EmployeeStatus,
                      }))
                    }
                    value={formData.status}
                  >
                    <option value="Active">Active</option>
                    <option value="On Leave">On Leave</option>
                    <option value="Probation">Probation</option>
                    <option value="Inactive">Inactive</option>
                  </select>
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <button
                  className="h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                  disabled={isSaving}
                  onClick={() => {
                    setIsFormOpen(false)
                    resetForm()
                    setSelectedEmployee(null)
                  }}
                  type="button"
                >
                  Cancel
                </button>
                <button className="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70" disabled={isSaving} type="submit">
                  {isSaving
                    ? 'Saving...'
                    : isEditMode
                      ? 'Save Changes'
                      : 'Add Employee'}
                </button>
              </div>
            </form>
      </AppModal>

      <AppModal className="max-w-md" onClose={() => setQrEmployee(null)} open={!!qrEmployee}>
        <div className="border-b border-slate-100 px-5 py-4">
          <h3 className="text-xl font-semibold tracking-tight text-slate-900">Employee QR Code</h3>
          <p className="mt-1 text-xs text-slate-500">Use this QR for attendance scanner check-in/check-out.</p>
        </div>

        <div className="space-y-3 px-5 py-4">
          <p className="text-sm text-slate-700">
            <span className="font-semibold text-slate-900">Employee:</span> {qrEmployee?.name}
          </p>
          <p className="text-sm text-slate-700">
            <span className="font-semibold text-slate-900">Code:</span> {qrEmployee?.employeeCode || (qrEmployee ? `EMP-${qrEmployee.id}` : '-')}
          </p>

          <div className="flex min-h-[290px] items-center justify-center rounded-xl border border-slate-200 bg-slate-50 p-3">
            {isGeneratingQr ? (
              <p className="text-sm text-slate-500">Generating QR code...</p>
            ) : qrImageUrl ? (
              <img alt="Employee QR" className="h-auto w-[280px] rounded-lg bg-white p-2" src={qrImageUrl} />
            ) : (
              <p className="text-sm text-rose-600">QR code unavailable.</p>
            )}
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-4">
          <button
            className="h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            onClick={() => setQrEmployee(null)}
            type="button"
          >
            Close
          </button>
          <button
            className="inline-flex h-10 items-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
            disabled={!qrImageUrl || isGeneratingQr}
            onClick={downloadQr}
            type="button"
          >
            <Download className="h-4 w-4" />
            Download
          </button>
        </div>
      </AppModal>
    </div>
  )
}
