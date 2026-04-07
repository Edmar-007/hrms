import { cn } from '@/lib/utils'

interface LoadingSpinnerProps {
  className?: string
}

export default function LoadingSpinner({ className }: LoadingSpinnerProps) {
  return (
    <span
      aria-label="Loading"
      className={cn(
        'inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent align-[-0.125em]',
        className,
      )}
      role="status"
    />
  )
}
