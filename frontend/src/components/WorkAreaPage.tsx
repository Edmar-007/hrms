import { FileText, Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { toast } from 'react-hot-toast'
import { useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'

interface WorkAreaPageProps {
  title: string
  description: string
  primaryAction: string
  secondaryAction: string
}

export default function WorkAreaPage({
  title,
  description,
  primaryAction,
  secondaryAction,
}: WorkAreaPageProps) {
  const [searchParams, setSearchParams] = useSearchParams()

  useEffect(() => {
    if (searchParams.get('quickAdd') === 'primary') {
      toast.success(`${primaryAction} started.`)
      setSearchParams({}, { replace: true })
    }
  }, [primaryAction, searchParams, setSearchParams])

  return (
    <div className="page-enter space-y-6">
      <section className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-3xl tracking-tight text-slate-900">{title}</h2>
          <p className="mt-1 text-sm text-slate-600">{description}</p>
        </div>

        <div className="flex items-center gap-2">
          <Button
            className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-slate-700 hover:bg-slate-100"
            onClick={() => toast.success(`${secondaryAction} opened.`)}
          >
            {secondaryAction}
          </Button>
          <Button
            className="h-10 rounded-xl bg-slate-900 px-4 text-white hover:bg-slate-800"
            onClick={() => toast.success(`${primaryAction} started.`)}
          >
            {primaryAction}
          </Button>
        </div>
      </section>

      <section className="surface-card surface-card-hover p-5">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h3 className="text-lg font-semibold text-slate-900">{title} Workspace</h3>
          <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
            Live
          </span>
        </div>

        <div className="mb-4 max-w-md">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-3 text-sm text-slate-700 outline-none transition focus:border-sky-400 focus:bg-white"
              placeholder={`Search ${title.toLowerCase()}...`}
              type="text"
            />
          </div>
        </div>

        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-6 py-12 text-center">
          <FileText className="mx-auto mb-2 h-7 w-7 text-slate-400" />
          <p className="text-base font-semibold text-slate-700">{title} content is ready</p>
          <p className="mt-1 text-sm text-slate-500">
            Use the action buttons above to continue workflows in this module.
          </p>
        </div>
      </section>
    </div>
  )
}
