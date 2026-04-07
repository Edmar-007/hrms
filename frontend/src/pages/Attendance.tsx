import { useEffect, useMemo, useRef, useState } from 'react'
import type { AxiosError } from 'axios'
import { Building2, CalendarDays, Clock3, Eye, FileText, QrCode, Search, UserCircle2 } from 'lucide-react'
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import { toast } from 'react-hot-toast'
import { Button } from '@/components/ui/button'
import AppModal from '@/components/ui/app-modal'
import TablePagination from '@/components/ui/table-pagination'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import api from '@/lib/api'

interface AttendanceRow {
  id: number
  employeeId: number
  employeeCode: string
  employeeName: string
  department: string
  date: string
  timeIn: string
  timeOut: string
  notes: string
  status: 'Complete' | 'In Progress' | 'Incomplete'
}

const PAGE_SIZE_OPTIONS = [10, 20, 50]

export default function Attendance() {
  const [rows, setRows] = useState<AttendanceRow[]>([])
  const [todayRecentScans, setTodayRecentScans] = useState<AttendanceRow[]>([])
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)
  const [totalRows, setTotalRows] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [showManual, setShowManual] = useState(false)
  const [showQuickScan, setShowQuickScan] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const [selectedRow, setSelectedRow] = useState<AttendanceRow | null>(null)
  const [scannerError, setScannerError] = useState('')
  const [quickScanCode, setQuickScanCode] = useState('')
  const [isScannerReady, setIsScannerReady] = useState(false)
  const [isScanning, setIsScanning] = useState(false)
  const [scannerStatus, setScannerStatus] = useState<{ type: 'ready' | 'success' | 'error'; text: string }>({
    type: 'ready',
    text: 'Click Start Scanner, then point camera at employee QR code.',
  })
  const lastScanCodeRef = useRef('')
  const lastScanAtRef = useRef(0)
  const scanLockRef = useRef(false)
  const scannerRef = useRef<Html5Qrcode | null>(null)
  const scannerRegionId = 'attendance-qr-reader'
  const debouncedQuery = useDebouncedValue(query, 300)
  const [form, setForm] = useState({
    employeeId: '',
    date: new Date().toISOString().slice(0, 10),
    timeIn: '',
    timeOut: '',
    notes: '',
  })

  const loadAttendance = async (nextPage = page, nextPageSize = pageSize, nextSearch = debouncedQuery.trim()) => {
    try {
      setIsLoading(true)
      const { data } = await api.get('/attendance.php', {
        params: {
          page: nextPage,
          limit: nextPageSize,
          search: nextSearch || undefined,
        },
      })

      const attendance = Array.isArray(data?.attendance) ? data.attendance : []
      const total = Number(data?.total ?? 0)
      const lastPage = Math.max(1, Math.ceil(total / nextPageSize))

      setRows(attendance)
      setTotalRows(total)

      if (nextPage > lastPage) {
        setPage(lastPage)
      }
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to load attendance records.')
      setRows([])
      setTotalRows(0)
    } finally {
      setIsLoading(false)
    }
  }

  const loadTodayRecentScans = async () => {
    const today = new Date().toISOString().slice(0, 10)
    try {
      const { data } = await api.get('/attendance.php', {
        params: {
          page: 1,
          limit: 10,
          search: today,
        },
      })
      const attendance = Array.isArray(data?.attendance) ? (data.attendance as AttendanceRow[]) : []
      setTodayRecentScans(attendance.filter((row: AttendanceRow) => row.date === today).slice(0, 10))
    } catch {
      setTodayRecentScans([])
    }
  }

  useEffect(() => {
    void loadAttendance(page, pageSize, debouncedQuery.trim())
  }, [page, pageSize, debouncedQuery])

  useEffect(() => {
    void loadTodayRecentScans()
  }, [])

  const getScannerErrorMessage = (error: unknown) => {
    const text = String(error ?? '')
    if (text.toLowerCase().includes('notallowederror')) {
      return 'Camera permission denied. Allow camera access in browser site settings and try again.'
    }
    if (text.toLowerCase().includes('notfounderror')) {
      return 'No camera device found.'
    }
    if (text.toLowerCase().includes('https')) {
      return 'Camera access requires localhost or HTTPS.'
    }
    return 'Camera scanner unavailable. You can still paste QR text or employee code below.'
  }

  const stopScanner = async () => {
    const scanner = scannerRef.current
    scannerRef.current = null
    if (!scanner) return

    try {
      await scanner.stop()
    } catch {
      // scanner may already be stopped
    }

    try {
      await scanner.clear()
    } catch {
      // ignore clear errors
    }

    setIsScanning(false)
    setIsScannerReady(false)
  }

  const processQrScan = async (rawCode: string, closeOnSuccess = false) => {
    if (scanLockRef.current) return

    const code = rawCode.trim()
    if (!code) {
      toast.error('Scan a QR code or paste employee code first.')
      return
    }

    try {
      scanLockRef.current = true
      setIsSaving(true)
      setScannerStatus({ type: 'ready', text: 'Processing scan...' })
      const { data } = await api.post('/attendance-scan.php', { code })
      const successMessage = data?.message ?? 'QR scan recorded successfully.'
      toast.success(successMessage)
      setScannerStatus({ type: 'success', text: successMessage })
      if (closeOnSuccess) {
        setShowQuickScan(false)
      }
      setQuickScanCode('')
      await Promise.all([
        loadAttendance(page, pageSize, debouncedQuery.trim()),
        loadTodayRecentScans(),
      ])
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to process QR scan.')
      setScannerStatus({ type: 'error', text: message ?? 'Unable to process QR scan.' })
    } finally {
      scanLockRef.current = false
      setIsSaving(false)
    }
  }

  const submitQuickScan = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    await processQrScan(quickScanCode, false)
  }

  const handleStartScanner = async () => {
    if (isScanning || scannerRef.current) return

    setScannerError('')
    setScannerStatus({ type: 'ready', text: 'Starting camera...' })

    const scanner = new Html5Qrcode(scannerRegionId, {
      verbose: false,
      formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
    })
    scannerRef.current = scanner

    try {
      await scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 220, height: 220 } },
        (decodedText) => {
          const now = Date.now()
          if (decodedText === lastScanCodeRef.current && now - lastScanAtRef.current < 3000) {
            return
          }
          if (scanLockRef.current) return

          lastScanCodeRef.current = decodedText
          lastScanAtRef.current = now
          setQuickScanCode(decodedText)
          void processQrScan(decodedText, false)
        },
        () => {
          // no-op for per-frame decode misses
        },
      )

      setIsScanning(true)
      setIsScannerReady(true)
      setScannerStatus({ type: 'ready', text: 'Scanner active. Show employee QR code.' })
    } catch (error) {
      const message = getScannerErrorMessage(error)
      setScannerError(message)
      setScannerStatus({ type: 'error', text: message })
      await stopScanner()
    }
  }

  const handleStopScanner = async () => {
    await stopScanner()
    setScannerStatus({ type: 'ready', text: 'Scanner stopped.' })
  }

  useEffect(() => {
    if (!showQuickScan) {
      void stopScanner()
      setQuickScanCode('')
      setScannerError('')
      setScannerStatus({
        type: 'ready',
        text: 'Click Start Scanner, then point camera at employee QR code.',
      })
    }

    return () => {
      void stopScanner()
    }
  }, [showQuickScan])

  const handleManualSave = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    if (!form.employeeId) {
      toast.error('Employee ID is required.')
      return
    }

    try {
      setIsSaving(true)
      await api.post('/attendance.php', {
        employeeId: Number(form.employeeId),
        date: form.date,
        timeIn: form.timeIn,
        timeOut: form.timeOut,
        notes: form.notes,
      })
      toast.success('Attendance record saved.')
      setShowManual(false)
      setForm({
        employeeId: '',
        date: new Date().toISOString().slice(0, 10),
        timeIn: '',
        timeOut: '',
        notes: '',
      })
      await Promise.all([
        loadAttendance(page, pageSize, debouncedQuery.trim()),
        loadTodayRecentScans(),
      ])
    } catch (error) {
      const message = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(message ?? 'Unable to save attendance. You may need HR/Admin access.')
    } finally {
      setIsSaving(false)
    }
  }

  const selectedInitials = useMemo(() => {
    if (!selectedRow?.employeeName) return 'NA'
    const parts = selectedRow.employeeName.trim().split(/\s+/)
    const first = parts[0]?.[0] ?? ''
    const second = parts[1]?.[0] ?? ''
    return `${first}${second}`.toUpperCase() || 'NA'
  }, [selectedRow])

  const workedDuration = useMemo(() => {
    if (!selectedRow?.timeIn || !selectedRow?.timeOut) return null
    const [inH, inM] = selectedRow.timeIn.split(':').map((v) => Number(v))
    const [outH, outM] = selectedRow.timeOut.split(':').map((v) => Number(v))
    if (Number.isNaN(inH) || Number.isNaN(inM) || Number.isNaN(outH) || Number.isNaN(outM)) return null

    const inMinutes = inH * 60 + inM
    const outMinutes = outH * 60 + outM
    if (outMinutes < inMinutes) return null

    const total = outMinutes - inMinutes
    const hours = Math.floor(total / 60)
    const minutes = total % 60
    return `${hours}h ${minutes}m`
  }, [selectedRow])

  return (
    <div className="page-enter space-y-6">
      <section className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-3xl tracking-tight text-slate-900">Attendance</h2>
          <p className="mt-1 text-sm text-slate-600">Track time-in, time-out, and scanner-based attendance.</p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-slate-700 hover:bg-slate-100"
            onClick={() => {
              void Promise.all([
                loadAttendance(page, pageSize, debouncedQuery.trim()),
                loadTodayRecentScans(),
              ])
            }}
          >
            Refresh
          </Button>
          <Button className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-slate-700 hover:bg-slate-100" onClick={() => setShowManual(true)}>
            Manual Entry
          </Button>
          <Button className="h-10 rounded-xl bg-slate-900 px-4 text-white hover:bg-slate-800" onClick={() => setShowQuickScan((prev) => !prev)}>
            <QrCode className="mr-2 h-4 w-4" />
            {showQuickScan ? 'Back To Table' : 'Quick Scan'}
          </Button>
        </div>
      </section>

      {!showQuickScan ? (
      <section className="surface-card surface-card-hover p-5">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="relative w-full max-w-md">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm text-slate-700 outline-none transition focus:border-sky-400 focus:bg-white"
              placeholder="Search employee, code, date..."
              type="text"
              value={query}
              onChange={(e) => {
                setQuery(e.target.value)
                setPage(1)
              }}
            />
          </div>
          <p className="text-sm text-slate-500">{totalRows} records</p>
        </div>

        {isLoading ? (
          <p className="py-8 text-center text-sm text-slate-500">Loading attendance...</p>
        ) : rows.length === 0 ? (
          <p className="py-8 text-center text-sm text-slate-500">No attendance data found.</p>
        ) : (
          <>
            <div className="overflow-x-auto rounded-xl border border-slate-200">
            <div className="max-h-[60vh] overflow-y-auto">
              <table className="w-full min-w-[860px] text-left text-sm">
              <thead className="sticky top-0 z-10 bg-white/95 backdrop-blur">
                <tr className="border-b border-slate-200 text-xs uppercase tracking-[0.1em] text-slate-500">
                  <th className="px-3 py-3 font-semibold">Employee</th>
                  <th className="px-3 py-3 font-semibold">Department</th>
                  <th className="px-3 py-3 font-semibold">Date</th>
                  <th className="px-3 py-3 font-semibold">Time In</th>
                  <th className="px-3 py-3 font-semibold">Time Out</th>
                  <th className="px-3 py-3 font-semibold">Status</th>
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
                    <td className="px-3 py-3 text-slate-700">{row.department}</td>
                    <td className="px-3 py-3 text-slate-700">{row.date}</td>
                    <td className="px-3 py-3 text-slate-700">{row.timeIn || '-'}</td>
                    <td className="px-3 py-3 text-slate-700">{row.timeOut || '-'}</td>
                    <td className="px-3 py-3">
                      <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                        {row.status}
                      </span>
                    </td>
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
              itemLabel="records"
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
      ) : (
      <section className="surface-card surface-card-hover p-5">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h3 className="text-xl font-semibold tracking-tight text-slate-900">QR Scanner</h3>
            <p className="mt-1 text-sm text-slate-600">Dedicated scanner section with compact recent attendance activity.</p>
          </div>
        </div>

        <form onSubmit={submitQuickScan}>
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <div className="space-y-3">
              <div>
                <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Camera Scanner</label>
                <div className="rounded-lg border border-slate-200 bg-slate-50 p-2">
                  <div className="min-h-[260px] overflow-hidden rounded-md bg-black/5" id={scannerRegionId} />
                </div>
                {!isScannerReady ? (
                  <p className="mt-2 text-xs text-slate-500">Click Start Scanner, then allow camera access when prompted.</p>
                ) : null}
                {scannerError ? <p className="mt-2 text-xs text-rose-600">{scannerError}</p> : null}
              </div>

              <div
                className={`rounded-lg border px-3 py-2 text-xs ${
                  scannerStatus.type === 'success'
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                    : scannerStatus.type === 'error'
                      ? 'border-rose-200 bg-rose-50 text-rose-700'
                      : 'border-slate-200 bg-slate-50 text-slate-600'
                }`}
              >
                {scannerStatus.text}
              </div>

              <div className="flex items-center gap-2">
                <button
                  className="h-10 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-70"
                  disabled={isScanning}
                  onClick={() => void handleStartScanner()}
                  type="button"
                >
                  Start Scanner
                </button>
                <button
                  className="h-10 rounded-lg bg-rose-600 px-4 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-70"
                  disabled={!isScanning}
                  onClick={() => void handleStopScanner()}
                  type="button"
                >
                  Stop
                </button>
              </div>

              <div>
                <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">QR Text Or Employee Code</label>
                <input
                  className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                  required
                  type="text"
                  value={quickScanCode}
                  onChange={(e) => setQuickScanCode(e.target.value)}
                />
              </div>

              <div className="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <button
                  className="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                  disabled={isSaving}
                  type="submit"
                >
                  {isSaving ? 'Processing...' : 'Submit Scan'}
                </button>
              </div>
            </div>

            <div className="space-y-3">
              <h4 className="text-sm font-semibold uppercase tracking-[0.08em] text-slate-500">Recent Scans Today</h4>
              <div className="overflow-hidden rounded-lg border border-slate-200 bg-white">
                {todayRecentScans.length === 0 ? (
                  <p className="px-4 py-8 text-center text-sm text-slate-500">No attendance records yet today.</p>
                ) : (
                  <div className="max-h-[430px] overflow-y-auto">
                      <table className="w-full text-left text-xs sm:text-sm">
                      <thead className="sticky top-0 bg-slate-50">
                        <tr className="border-b border-slate-200 text-slate-500">
                          <th className="px-3 py-2 font-semibold">Employee</th>
                          <th className="px-3 py-2 font-semibold">In</th>
                          <th className="px-3 py-2 font-semibold">Out</th>
                          <th className="px-3 py-2 font-semibold">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        {todayRecentScans.map((scan) => (
                          <tr className="border-b border-slate-100" key={scan.id}>
                            <td className="px-3 py-2">
                              <p className="font-semibold text-slate-900">{scan.employeeName}</p>
                              <p className="text-xs text-slate-500">{scan.employeeCode}</p>
                            </td>
                            <td className="px-3 py-2 text-slate-700">{scan.timeIn || '-'}</td>
                            <td className="px-3 py-2 text-slate-700">{scan.timeOut || '-'}</td>
                            <td className="px-3 py-2 text-slate-700">{scan.status}</td>
                          </tr>
                        ))}
                      </tbody>
                      </table>
                  </div>
                )}
              </div>
            </div>
          </div>
        </form>
      </section>
      )}

      <AppModal className="max-w-md" onClose={() => setSelectedRow(null)} open={!!selectedRow}>
            <div className="border-b border-slate-100 px-5 py-4">
              <h3 className="text-xl font-semibold tracking-tight text-slate-900">Attendance Detail</h3>
            </div>

            <div className="space-y-4 px-5 py-4">
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
                  <span
                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                      selectedRow?.status === 'Complete'
                        ? 'bg-emerald-100 text-emerald-700'
                        : selectedRow?.status === 'In Progress'
                          ? 'bg-amber-100 text-amber-700'
                          : 'bg-slate-100 text-slate-700'
                    }`}
                  >
                    {selectedRow?.status}
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                  <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500"><Building2 className="h-3.5 w-3.5" /> Department</p>
                  <p className="font-semibold text-slate-800">{selectedRow?.department || '-'}</p>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                  <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500"><CalendarDays className="h-3.5 w-3.5" /> Date</p>
                  <p className="font-semibold text-slate-800">{selectedRow?.date || '-'}</p>
                </div>
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                  <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-emerald-700"><UserCircle2 className="h-3.5 w-3.5" /> Time In</p>
                  <p className="font-semibold text-emerald-800">{selectedRow?.timeIn || '-'}</p>
                </div>
                <div className="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2">
                  <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-indigo-700"><Clock3 className="h-3.5 w-3.5" /> Time Out</p>
                  <p className="font-semibold text-indigo-800">{selectedRow?.timeOut || '-'}</p>
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <p className="mb-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Worked Duration</p>
                <p className="font-semibold text-slate-800">{workedDuration ?? 'Not enough data'}</p>
              </div>

              <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <p className="mb-1 flex items-center gap-1 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500"><FileText className="h-3.5 w-3.5" /> Notes</p>
                <p>{selectedRow?.notes?.trim() ? selectedRow.notes : 'No notes recorded.'}</p>
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

      <AppModal className="max-w-md" onClose={() => setShowManual(false)} open={showManual}>
            <form onSubmit={handleManualSave}>
              <div className="border-b border-slate-100 px-5 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <h3 className="text-xl font-semibold tracking-tight text-slate-900">Manual Attendance Entry</h3>
                    <p className="mt-1 text-xs text-slate-500">Create or update one attendance record.</p>
                  </div>
                  <button
                    aria-label="Close"
                    className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    onClick={() => setShowManual(false)}
                    type="button"
                  >
                    x
                  </button>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-2">
                <div className="sm:col-span-2">
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Employee ID</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    required
                    type="number"
                    value={form.employeeId}
                    onChange={(e) => setForm((prev) => ({ ...prev, employeeId: e.target.value }))}
                  />
                </div>

                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Date</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    required
                    type="date"
                    value={form.date}
                    onChange={(e) => setForm((prev) => ({ ...prev, date: e.target.value }))}
                  />
                </div>

                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Time In</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    type="time"
                    value={form.timeIn}
                    onChange={(e) => setForm((prev) => ({ ...prev, timeIn: e.target.value }))}
                  />
                </div>

                <div>
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Time Out</label>
                  <input
                    className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    type="time"
                    value={form.timeOut}
                    onChange={(e) => setForm((prev) => ({ ...prev, timeOut: e.target.value }))}
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Notes</label>
                  <textarea
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                    rows={3}
                    value={form.notes}
                    onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))}
                  />
                </div>
              </div>

              <div className="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <button
                  className="h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                  onClick={() => setShowManual(false)}
                  type="button"
                >
                  Cancel
                </button>
                <button
                  className="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                  disabled={isSaving}
                  type="submit"
                >
                  {isSaving ? 'Saving...' : 'Save Record'}
                </button>
              </div>
            </form>
      </AppModal>
    </div>
  )
}
