import { Button } from '@/components/ui/button'

interface TablePaginationProps {
  page: number
  pageSize: number
  pageSizeOptions?: number[]
  total: number
  currentCount: number
  itemLabel: string
  isLoading?: boolean
  onPageChange: (page: number) => void
  onPageSizeChange: (pageSize: number) => void
}

export default function TablePagination({
  page,
  pageSize,
  pageSizeOptions = [10, 20, 50],
  total,
  currentCount,
  itemLabel,
  isLoading = false,
  onPageChange,
  onPageSizeChange,
}: TablePaginationProps) {
  const totalPages = Math.max(1, Math.ceil(total / pageSize))
  const from = total === 0 ? 0 : (page - 1) * pageSize + 1
  const to = total === 0 ? 0 : from + Math.max(currentCount - 1, 0)

  return (
    <div className="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:gap-4">
        <p>
          {isLoading ? 'Loading results...' : `Showing ${from}-${to} of ${total} ${itemLabel}`}
        </p>
        <label className="flex items-center gap-2">
          <span>Rows per page</span>
          <select
            className="h-9 rounded-lg border border-slate-200 bg-white px-2 text-sm text-slate-700 outline-none transition focus:border-sky-400"
            onChange={(e) => onPageSizeChange(Number(e.target.value))}
            value={pageSize}
          >
            {pageSizeOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className="flex items-center justify-between gap-2 sm:justify-end">
        <p className="text-sm text-slate-500">
          Page {Math.min(page, totalPages)} of {totalPages}
        </p>
        <div className="flex items-center gap-2">
          <Button
            className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-slate-700 hover:bg-slate-50"
            disabled={isLoading || page <= 1}
            onClick={() => onPageChange(page - 1)}
            type="button"
            variant="outline"
          >
            Previous
          </Button>
          <Button
            className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-slate-700 hover:bg-slate-50"
            disabled={isLoading || page >= totalPages || total === 0}
            onClick={() => onPageChange(page + 1)}
            type="button"
            variant="outline"
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  )
}
