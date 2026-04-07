import * as React from 'react'
import { createPortal } from 'react-dom'
import { cn } from '@/lib/utils'

interface AppModalProps {
  open: boolean
  onClose: () => void
  children: React.ReactNode
  className?: string
}

export default function AppModal({ open, onClose, children, className }: AppModalProps) {
  if (!open) return null

  return createPortal(
    <div className="fixed inset-0 z-[100000] flex items-center justify-center p-4 sm:p-6">
      <button
        aria-label="Close modal"
        className="absolute inset-0 bg-slate-950/45 backdrop-blur-[2px]"
        onClick={onClose}
        type="button"
      />
      <div
        className={cn(
          'relative z-10 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_60px_rgba(15,23,42,0.25)]',
          className,
        )}
      >
        {children}
      </div>
    </div>,
    document.body,
  )
}
