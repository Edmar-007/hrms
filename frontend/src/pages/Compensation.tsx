import { useEffect, useState } from 'react'
import type { AxiosError } from 'axios'
import { toast } from 'react-hot-toast'
import api from '@/lib/api'

interface StructureRow {
  id: number
  name: string
  description: string
  componentCount: number
  createdAt: string
}

interface ComponentCounts {
  earning: number
  deduction: number
}

export default function Compensation() {
  const [rows, setRows] = useState<StructureRow[]>([])
  const [counts, setCounts] = useState<ComponentCounts>({ earning: 0, deduction: 0 })
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    const loadCompensation = async () => {
      try {
        setIsLoading(true)
        const { data } = await api.get('/compensation.php')
        setRows(Array.isArray(data?.structures) ? data.structures : [])
        setCounts(data?.componentCounts ?? { earning: 0, deduction: 0 })
      } catch (error) {
        const message = (error as AxiosError<{ error?: string }>).response?.data?.error
        toast.error(message ?? 'Unable to load compensation data.')
      } finally {
        setIsLoading(false)
      }
    }

    loadCompensation()
  }, [])

  return (
    <div className="page-enter space-y-6">
      <section>
        <h2 className="font-display text-3xl tracking-tight text-slate-900">Compensation</h2>
        <p className="mt-1 text-sm text-slate-600">Configure salary structures and compensation policies.</p>
      </section>

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article className="surface-card p-5">
          <p className="text-sm font-medium text-slate-500">Salary Structures</p>
          <p className="mt-2 text-3xl font-semibold text-slate-900">{rows.length}</p>
        </article>
        <article className="surface-card p-5">
          <p className="text-sm font-medium text-slate-500">Earning Components</p>
          <p className="mt-2 text-3xl font-semibold text-emerald-700">{counts.earning}</p>
        </article>
        <article className="surface-card p-5">
          <p className="text-sm font-medium text-slate-500">Deduction Components</p>
          <p className="mt-2 text-3xl font-semibold text-amber-700">{counts.deduction}</p>
        </article>
      </section>

      <section className="surface-card p-5">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-slate-900">Salary Structures</h3>
          <p className="text-sm text-slate-500">Latest first</p>
        </div>

        {isLoading ? (
          <p className="py-8 text-center text-sm text-slate-500">Loading compensation...</p>
        ) : rows.length === 0 ? (
          <p className="py-8 text-center text-sm text-slate-500">No salary structures found.</p>
        ) : (
          <div className="overflow-x-auto">
            <div className="max-h-[60vh] overflow-y-auto">
              <table className="w-full min-w-[760px] text-left text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs uppercase tracking-[0.1em] text-slate-500">
                  <th className="px-3 py-3 font-semibold">Structure Name</th>
                  <th className="px-3 py-3 font-semibold">Description</th>
                  <th className="px-3 py-3 font-semibold">Components</th>
                  <th className="px-3 py-3 font-semibold">Created</th>
                  <th className="px-3 py-3 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr className="border-b border-slate-100" key={row.id}>
                    <td className="px-3 py-3 font-medium text-slate-900">{row.name}</td>
                    <td className="px-3 py-3 text-slate-700">{row.description || '-'}</td>
                    <td className="px-3 py-3 text-slate-700">{row.componentCount}</td>
                    <td className="px-3 py-3 text-slate-700">{new Date(row.createdAt).toLocaleDateString()}</td>
                    <td className="px-3 py-3 text-right">
                      <button
                        className="inline-flex h-8 items-center rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        onClick={() => toast(`Structure: ${row.name}`, { icon: 'i' })}
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
        )}
      </section>
    </div>
  )
}
