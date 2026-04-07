import { cn } from '@/lib/utils'

interface SettingsNavProps {
  sections: { key: string; label: string; icon: React.ElementType }[]
  current: string
  onSelect: (key: string) => void
}

export default function SettingsNav({ sections, current, onSelect }: SettingsNavProps) {
  return (
    <nav className="flex w-full gap-2 overflow-x-auto rounded-xl border border-slate-200 bg-white p-2 xl:w-56 xl:flex-col xl:gap-1 xl:overflow-visible xl:p-4">
      {sections.map(({ key, label, icon: Icon }) => (
        <button
          key={key}
          className={cn(
            'flex min-w-fit items-center gap-3 rounded-lg px-4 py-2 text-sm font-medium transition-all xl:w-full',
            current === key
              ? 'bg-sky-50 text-sky-700 shadow ring-1 ring-sky-100'
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          )}
          onClick={() => onSelect(key)}
          type="button"
        >
          <Icon className="h-4 w-4" />
          {label}
        </button>
      ))}
    </nav>
  )
}
