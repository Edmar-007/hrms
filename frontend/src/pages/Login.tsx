import { useEffect, useMemo, useState } from 'react'
import type { AxiosError } from 'axios'
import { useAuth } from '@/hooks/useAuth'
import { useNavigate } from 'react-router-dom'
import { toast } from 'react-hot-toast'
import LoadingSpinner from '@/components/ui/LoadingSpinner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  ArrowRight,
  Briefcase,
  CheckCircle2,
  Clock3,
  Eye,
  EyeOff,
  Fingerprint,
  Layers,
  Lock,
  Mail,
  ScanLine,
  ShieldCheck,
  Sparkles,
  Wallet,
} from 'lucide-react'

const REMEMBER_EMAIL_KEY = 'hrms.remembered.email'

export default function Login() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [rememberEmail, setRememberEmail] = useState(false)
  const [capsLockOn, setCapsLockOn] = useState(false)
  const { login } = useAuth()
  const navigate = useNavigate()

  useEffect(() => {
    const rememberedEmail = window.localStorage.getItem(REMEMBER_EMAIL_KEY)
    if (rememberedEmail) {
      setEmail(rememberedEmail)
      setRememberEmail(true)
    }
  }, [])

  const runtimeStamp = useMemo(
    () =>
      new Intl.DateTimeFormat('en-PH', {
        weekday: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
      }).format(new Date()),
    [],
  )

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    const normalizedEmail = email.trim()
    if (!/^\S+@\S+\.\S+$/.test(normalizedEmail)) {
      toast.error('Please enter a valid work email address')
      return
    }

    if (password.length < 6) {
      toast.error('Password must be at least 6 characters')
      return
    }

    try {
      await login.mutateAsync({ email: normalizedEmail, password })

      if (rememberEmail) {
        window.localStorage.setItem(REMEMBER_EMAIL_KEY, normalizedEmail)
      } else {
        window.localStorage.removeItem(REMEMBER_EMAIL_KEY)
      }

      toast.success('Logged in successfully')
      navigate('/dashboard', { replace: true })
    } catch (error) {
      const apiError = (error as AxiosError<{ error?: string }>).response?.data?.error
      toast.error(apiError ?? 'Unable to sign in. Please verify your credentials.')
    }
  }

  const canSubmit = email.trim().length > 0 && password.length > 0 && !login.isPending

  return (
    <div className="hrms-auth-page">
      <div className="hrms-shape hrms-shape-a" />
      <div className="hrms-shape hrms-shape-b" />
      <div className="hrms-shape hrms-shape-c" />
      <div className="hrms-dot-grid" />

      <main className="relative z-10 mx-auto flex min-h-screen w-full max-w-6xl items-center px-4 py-8 sm:px-8 lg:px-10">
        <div className="grid w-full gap-8 lg:grid-cols-[1fr_1.1fr] lg:items-center">
          <section className="hrms-login-card hrms-slide-up">
            <div className="flex items-center justify-between gap-3">
              <div className="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-slate-50">
                <Sparkles className="h-3.5 w-3.5" />
                HRMS Access
              </div>
              <span className="inline-flex items-center gap-1 text-xs font-medium text-slate-500">
                <Clock3 className="h-3.5 w-3.5" />
                {runtimeStamp}
              </span>
            </div>

            <h1 className="mt-5 font-display text-4xl tracking-tight text-slate-900">Login</h1>
            <p className="mt-2 text-sm text-slate-600">
              Continue to Attendance, Payroll, Leave, Reports, and Employee Management.
            </p>

            <form className="mt-7 space-y-5" onSubmit={handleSubmit}>
              <div className="hrms-slide-up" style={{ animationDelay: '80ms' }}>
                <Label className="mb-1.5 block text-sm font-semibold text-slate-700" htmlFor="email">
                  Work email
                </Label>
                <div className="hrms-input-shell">
                  <Mail className="h-4 w-4 text-slate-400" />
                  <Input
                    id="email"
                    type="email"
                    placeholder="you@company.com"
                    value={email}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => setEmail(e.target.value)}
                    autoComplete="email"
                    className="h-auto border-0 bg-transparent px-2 py-0 text-[15px] shadow-none focus-visible:ring-0"
                    required
                  />
                </div>
              </div>

              <div className="hrms-slide-up" style={{ animationDelay: '130ms' }}>
                <div className="mb-1.5 flex items-center justify-between">
                  <Label className="text-sm font-semibold text-slate-700" htmlFor="password">
                    Password
                  </Label>
                  <a className="text-xs font-semibold text-cyan-700 transition hover:text-cyan-900" href="/hrms/auth/forgot-password.php">
                    Forgot password?
                  </a>
                </div>

                <div className="hrms-input-shell">
                  <Lock className="h-4 w-4 text-slate-400" />
                  <Input
                    id="password"
                    type={showPassword ? 'text' : 'password'}
                    placeholder="Enter your password"
                    value={password}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => setPassword(e.target.value)}
                    onKeyUp={(e: React.KeyboardEvent<HTMLInputElement>) => setCapsLockOn(e.getModifierState('CapsLock'))}
                    autoComplete="current-password"
                    className="h-auto border-0 bg-transparent px-2 py-0 text-[15px] shadow-none focus-visible:ring-0"
                    required
                  />
                  <button
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                    className="rounded p-1 text-slate-400 transition hover:text-slate-700"
                    onClick={() => setShowPassword((v) => !v)}
                    type="button"
                  >
                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
                {capsLockOn ? <p className="mt-2 text-xs font-medium text-amber-700">Caps Lock is enabled.</p> : null}
              </div>

              <div className="hrms-slide-up flex items-center justify-between" style={{ animationDelay: '180ms' }}>
                <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600" htmlFor="remember-email">
                  <input
                    id="remember-email"
                    type="checkbox"
                    className="h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600"
                    checked={rememberEmail}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => setRememberEmail(e.target.checked)}
                  />
                  Remember my email
                </label>

                <span className="inline-flex items-center gap-1 text-xs font-medium text-slate-500">
                  <Fingerprint className="h-3.5 w-3.5" />
                  Secure session
                </span>
              </div>

              <div className="hrms-slide-up" style={{ animationDelay: '230ms' }}>
                <Button type="submit" className="hrms-login-button group h-12 w-full rounded-xl text-sm font-semibold text-white" disabled={!canSubmit}>
                  {login.isPending ? <LoadingSpinner className="mr-2" /> : null}
                  <span className="inline-flex items-center gap-2">
                    Continue to dashboard
                    <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />
                  </span>
                </Button>
              </div>
            </form>

            <div className="hrms-slide-up mt-7 rounded-xl border border-slate-200 bg-slate-50/90 px-4 py-3 text-xs text-slate-600" style={{ animationDelay: '280ms' }}>
              <p className="inline-flex items-center gap-1 font-semibold text-slate-700">
                <ShieldCheck className="h-3.5 w-3.5 text-cyan-700" />
                Internal company access only
              </p>
              <p className="mt-1 leading-relaxed">Login is restricted to authorized HRMS users and monitored for security.</p>
            </div>

            <p className="hrms-slide-up mt-6 text-center text-sm text-slate-600" style={{ animationDelay: '320ms' }}>
              Need a company workspace?{' '}
              <a className="font-semibold text-cyan-700 transition hover:text-cyan-900" href="/hrms/auth/register.php">
                Register organization
              </a>
            </p>
          </section>

          <section className="hidden lg:block">
            <div className="hrms-hero-copy hrms-slide-up" style={{ animationDelay: '100ms' }}>
              <p className="text-sm font-semibold uppercase tracking-[0.22em] text-slate-500">HRMS control center</p>
              <h2 className="mt-3 max-w-xl font-display text-6xl leading-[0.98] tracking-tight text-slate-900">
                Manage your workforce with clarity and speed.
              </h2>
              <p className="mt-5 max-w-lg text-base leading-relaxed text-slate-600">
                Unified operations across attendance tracking, payroll processing, leave approvals, employee records, and audit reporting.
              </p>
            </div>

            <div className="mt-7 grid grid-cols-2 gap-4">
              <article className="hrms-module-card hrms-slide-up" style={{ animationDelay: '150ms' }}>
                <ScanLine className="h-5 w-5 text-cyan-700" />
                <h3 className="mt-2 text-sm font-semibold text-slate-900">Attendance</h3>
                <p className="mt-1 text-xs leading-relaxed text-slate-600">Shift logs, corrections, and live time-in or out visibility.</p>
              </article>

              <article className="hrms-module-card hrms-slide-up" style={{ animationDelay: '200ms' }}>
                <Wallet className="h-5 w-5 text-cyan-700" />
                <h3 className="mt-2 text-sm font-semibold text-slate-900">Payroll</h3>
                <p className="mt-1 text-xs leading-relaxed text-slate-600">Salary processing, components, and monthly payout workflows.</p>
              </article>

              <article className="hrms-module-card hrms-slide-up" style={{ animationDelay: '250ms' }}>
                <Layers className="h-5 w-5 text-cyan-700" />
                <h3 className="mt-2 text-sm font-semibold text-slate-900">Employee Records</h3>
                <p className="mt-1 text-xs leading-relaxed text-slate-600">Profiles, roles, leave balances, and lifecycle management.</p>
              </article>

              <article className="hrms-module-card hrms-slide-up" style={{ animationDelay: '300ms' }}>
                <Briefcase className="h-5 w-5 text-cyan-700" />
                <h3 className="mt-2 text-sm font-semibold text-slate-900">Claims and Reports</h3>
                <p className="mt-1 text-xs leading-relaxed text-slate-600">Expense claims, trends, and compliance-ready exports.</p>
              </article>
            </div>

            <div className="hrms-slide-up mt-6 inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-xs font-medium text-slate-100" style={{ animationDelay: '340ms' }}>
              <CheckCircle2 className="h-3.5 w-3.5 text-emerald-300" />
              System status: All core HRMS services operational
            </div>
          </section>
        </div>
      </main>
    </div>
  )
}

