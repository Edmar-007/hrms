import { useEffect, useState } from 'react'
import { BellRing, Building2, Globe2, Lock, Palette, ShieldCheck, User } from 'lucide-react'
import { Button } from '@/components/ui/button'
import SettingsNav from '@/components/SettingsNav'
import { toast } from 'react-hot-toast'
import api from '@/lib/api'
import { useAppearance } from '@/hooks/useAppearance'

interface ToggleRowProps {
  label: string
  detail: string
  enabled: boolean
  onToggle: () => void
}

interface ProfileForm {
  name: string
  email: string
  department: string
  role: string
}

type SettingsSectionKey =
  | 'profile'
  | 'organization'
  | 'notifications'
  | 'security'
  | 'design'
  | 'environment'

const DEFAULT_PROFILE: ProfileForm = {
  name: 'System Admin',
  email: 'admin@hrms.local',
  department: 'HR',
  role: 'HR Specialist',
}

const settingsSections: { key: SettingsSectionKey; label: string; icon: React.ElementType }[] = [
  { key: 'profile', label: 'My Profile', icon: User },
  { key: 'organization', label: 'Organization', icon: Building2 },
  { key: 'notifications', label: 'Notifications', icon: BellRing },
  { key: 'security', label: 'Security', icon: ShieldCheck },
  { key: 'design', label: 'Website Design', icon: Palette },
  { key: 'environment', label: 'Environment', icon: Globe2 },
]

function ToggleRow({ label, detail, enabled, onToggle }: ToggleRowProps) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3">
      <div className="flex flex-col justify-center">
        <span className="text-sm font-semibold text-slate-900">{label}</span>
        <span className="mt-1 text-xs text-slate-500">{detail}</span>
      </div>
      <button
        aria-pressed={enabled}
        aria-label={label}
        className={[
          'relative h-7 w-12 rounded-full transition-colors outline-none focus:ring-2 focus:ring-sky-400',
          enabled ? 'bg-sky-500' : 'bg-slate-300',
        ].join(' ')}
        onClick={onToggle}
        type="button"
        tabIndex={0}
      >
        <span
          className={[
            'absolute top-0.5 left-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform',
            enabled ? 'translate-x-5' : 'translate-x-0',
          ].join(' ')}
        />
      </button>
    </div>
  )
}

export default function Settings() {
  const { isLoading: appearanceLoading, updateAppearance: _updateAppearance, resetAppearance: _resetAppearance } = useAppearance()
  const [companyName, setCompanyName] = useState('HRMS Technologies Inc.')
  const [supportEmail, setSupportEmail] = useState('support@hrms.local')
  const [timezone, setTimezone] = useState('Asia/Manila')
  const [locale, setLocale] = useState('English (US)')
  const [emailAlerts, setEmailAlerts] = useState(true)
  const [weeklyDigest, setWeeklyDigest] = useState(true)
  const [twoFactor, setTwoFactor] = useState(false)
  const [ipRestriction, setIpRestriction] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const [section, setSection] = useState<'profile' | 'organization' | 'notifications' | 'security' | 'design' | 'environment'>('profile')

  const [profile, setProfile] = useState<ProfileForm>(DEFAULT_PROFILE)
  const [savedProfile, setSavedProfile] = useState<ProfileForm>(DEFAULT_PROFILE)
  const [editMode, setEditMode] = useState(false)
  const [isProfileLoading, setIsProfileLoading] = useState(true)
  const [isProfileSaving, setIsProfileSaving] = useState(false)

  useEffect(() => {
    async function loadSettings() {
      try {
        const { data } = await api.get('/company-settings.php')
        setCompanyName(String(data?.companyName ?? 'HRMS Technologies Inc.'))
        setSupportEmail(String(data?.supportEmail ?? 'support@hrms.local'))
        setTimezone(String(data?.timezone ?? 'Asia/Manila'))
        setLocale(String(data?.locale ?? 'English (US)'))
        setEmailAlerts(Boolean(data?.emailAlerts ?? true))
        setWeeklyDigest(Boolean(data?.weeklyDigest ?? true))
        setTwoFactor(Boolean(data?.twoFactor ?? false))
        setIpRestriction(Boolean(data?.ipRestriction ?? false))
      } catch {
        toast.error('Unable to load company settings.')
      }
    }

    async function loadProfile() {
      setIsProfileLoading(true)
      try {
        const { data } = await api.get('/me.php')
        const nextProfile: ProfileForm = {
          name: String(data?.name ?? DEFAULT_PROFILE.name),
          email: String(data?.email ?? DEFAULT_PROFILE.email),
          department: String(data?.department ?? DEFAULT_PROFILE.department),
          role: String(data?.role ?? DEFAULT_PROFILE.role),
        }
        setProfile(nextProfile)
        setSavedProfile(nextProfile)
      } catch {
        setProfile(DEFAULT_PROFILE)
        setSavedProfile(DEFAULT_PROFILE)
      } finally {
        setIsProfileLoading(false)
      }
    }

    loadSettings()
    loadProfile()
  }, [])

  const handleEditProfile = () => setEditMode(true)

  const handleCancelProfile = () => {
    setProfile(savedProfile)
    setEditMode(false)
  }

  const handleSaveProfile = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setIsProfileSaving(true)

    try {
      await api.post('/me.php', profile)
      setSavedProfile(profile)
      setEditMode(false)
      toast.success('Profile updated.')
    } catch {
      toast.error('Failed to update profile.')
    } finally {
      setIsProfileSaving(false)
    }
  }

  const handleSaveOrgSettings = async () => {
    setIsSaving(true)
    try {
      await api.post('/company-settings.php', {
        companyName,
        supportEmail,
        timezone,
        locale,
        emailAlerts,
        weeklyDigest,
        twoFactor,
        ipRestriction,
      })
      toast.success('Organization settings saved.')
    } catch {
      toast.error('Failed to save settings.')
    } finally {
      setIsSaving(false)
    }
  }

  const handleManageAccess = () => toast('Access policy management coming soon.')

  if (appearanceLoading) {
    return <div className="flex h-screen items-center justify-center"><div>Loading appearance...</div></div>
  }

  return (
    <div className="page-enter flex flex-col gap-6 xl:flex-row">
      <SettingsNav
        sections={settingsSections}
        current={section}
        onSelect={(key) => setSection(key as SettingsSectionKey)}
      />

      <div className="min-w-0 flex-1 space-y-6">
        {section === 'profile' && (
          <section className="surface-card surface-card-hover max-w-3xl p-5">
            <div className="mb-4">
              <h3 className="text-lg font-semibold text-slate-900">My Profile</h3>
              <p className="mt-1 text-sm text-slate-600">Update your personal details and account preferences.</p>
            </div>

            {isProfileLoading ? (
              <div className="flex h-32 items-center justify-center text-slate-500">Loading profile...</div>
            ) : (
              <form id="profile-form" onSubmit={handleSaveProfile} className="space-y-5">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Full Name</label>
                    <input
                      className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                      type="text"
                      value={profile.name}
                      onChange={(event) => setProfile((current) => ({ ...current, name: event.target.value }))}
                      disabled={!editMode}
                      required
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Email</label>
                    <input
                      className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                      type="email"
                      value={profile.email}
                      onChange={(event) => setProfile((current) => ({ ...current, email: event.target.value }))}
                      disabled={!editMode}
                      required
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Department</label>
                    <input
                      className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                      type="text"
                      value={profile.department}
                      onChange={(event) => setProfile((current) => ({ ...current, department: event.target.value }))}
                      disabled={!editMode}
                      required
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Role</label>
                    <input
                      className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
                      type="text"
                      value={profile.role}
                      onChange={(event) => setProfile((current) => ({ ...current, role: event.target.value }))}
                      disabled={!editMode}
                      required
                    />
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  {editMode ? (
                    <>
                      <Button
                        className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-slate-700 hover:bg-slate-100"
                        onClick={handleCancelProfile}
                        disabled={isProfileSaving}
                        type="button"
                      >
                        Cancel
                      </Button>
                      <Button
                        className="app-accent-button h-10 rounded-xl px-4 text-white"
                        type="submit"
                        disabled={isProfileSaving}
                      >
                        {isProfileSaving ? 'Saving...' : 'Save Profile'}
                      </Button>
                    </>
                  ) : (
                    <Button
                      className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-slate-700 hover:bg-slate-100"
                      onClick={handleEditProfile}
                      type="button"
                    >
                      Edit Details
                    </Button>
                  )}
                </div>
              </form>
            )}
          </section>
        )}

        {section === 'organization' && (
          <section className="surface-card surface-card-hover max-w-3xl p-5 bg-[var(--control-bg-color)]">
            <div className="mb-4">
              <h3 className="text-lg font-semibold" style={{ color: 'var(--control-text-color)' }}>Organization Profile</h3>
              <p className="mt-1 text-sm text-slate-600">Manage company identity, timezone, and localization defaults.</p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="space-y-1.5">
                <span className="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Company Name</span>
                <input
                  className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700 outline-none focus:border-sky-400 focus:bg-white"
                  onChange={(event) => setCompanyName(event.target.value)}
                  type="text"
                  value={companyName}
                />
              </label>
              <label className="space-y-1.5">
                <span className="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Support Email</span>
                <input
                  className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700 outline-none focus:border-sky-400 focus:bg-white"
                  onChange={(event) => setSupportEmail(event.target.value)}
                  type="email"
                  value={supportEmail}
                />
              </label>
              <label className="space-y-1.5">
                <span className="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Default Timezone</span>
                <select
                  className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700 outline-none focus:border-sky-400 focus:bg-white"
                  onChange={(event) => setTimezone(event.target.value)}
                  value={timezone}
                >
                  <option value="Asia/Manila">Asia/Manila</option>
                  <option value="UTC">UTC</option>
                  <option value="America/New_York">America/New_York</option>
                </select>
              </label>
              <label className="space-y-1.5">
                <span className="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Locale</span>
                <select
                  className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700 outline-none focus:border-sky-400 focus:bg-white"
                  onChange={(event) => setLocale(event.target.value)}
                  value={locale}
                >
                  <option value="English (US)">English (US)</option>
                  <option value="English (PH)">English (PH)</option>
                </select>
              </label>
            </div>

            <Button
              className="mt-6 h-10 rounded-xl px-4"
              disabled={isSaving}
              onClick={handleSaveOrgSettings}
              type="button"
            >
              {isSaving ? 'Saving...' : 'Save Organization Settings'}
            </Button>
          </section>
        )}

        {section === 'notifications' && (
          <section className="surface-card surface-card-hover max-w-3xl p-5">
            <div className="mb-4">
              <h3 className="text-lg font-semibold text-slate-900">Notifications</h3>
              <p className="mt-1 text-sm text-slate-600">Choose which updates are delivered to admins and team members.</p>
            </div>

            <div className="space-y-3">
              <ToggleRow
                label="Email alerts"
                detail="Receive approvals, leave, and payroll updates by email"
                enabled={emailAlerts}
                onToggle={() => setEmailAlerts((value) => !value)}
              />
              <ToggleRow
                label="Weekly digest"
                detail="Send a consolidated summary every Monday"
                enabled={weeklyDigest}
                onToggle={() => setWeeklyDigest((value) => !value)}
              />
            </div>
          </section>
        )}

        {section === 'security' && (
          <section className="surface-card surface-card-hover max-w-3xl p-5">
            <div className="mb-4">
              <h3 className="text-lg font-semibold text-slate-900">Security</h3>
              <p className="mt-1 text-sm text-slate-600">Strengthen sign-in requirements and device access controls.</p>
            </div>

            <div className="space-y-3">
              <ToggleRow
                label="Two-factor authentication"
                detail="Require OTP during sign in"
                enabled={twoFactor}
                onToggle={() => setTwoFactor((value) => !value)}
              />
              <ToggleRow
                label="IP restriction"
                detail="Allow sign in from approved IP list only"
                enabled={ipRestriction}
                onToggle={() => setIpRestriction((value) => !value)}
              />
            </div>

            <button
              className="mt-4 inline-flex items-center rounded-lg px-3 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-50"
              onClick={handleManageAccess}
              type="button"
            >
              <Lock className="mr-2 h-4 w-4" />
              Manage Access Policies
            </button>
          </section>
        )}

        {section === 'environment' && (
          <section className="surface-card surface-card-hover max-w-3xl p-5">
            <div className="mb-4">
              <h3 className="text-lg font-semibold text-slate-900">Environment</h3>
              <p className="mt-1 text-sm text-slate-600">View deployment metadata and retention defaults for this workspace.</p>
            </div>

            <ul className="space-y-3 text-sm text-slate-600">
              <li className="rounded-xl bg-slate-50 px-3 py-2">
                <span className="block text-xs uppercase tracking-[0.1em] text-slate-500">Region</span>
                <span className="mt-1 block font-medium text-slate-900">Asia Pacific</span>
              </li>
              <li className="rounded-xl bg-slate-50 px-3 py-2">
                <span className="block text-xs uppercase tracking-[0.1em] text-slate-500">Version</span>
                <span className="mt-1 block font-medium text-slate-900">v3.8.2</span>
              </li>
              <li className="rounded-xl bg-slate-50 px-3 py-2">
                <span className="block text-xs uppercase tracking-[0.1em] text-slate-500">Data Retention</span>
                <span className="mt-1 block font-medium text-slate-900">24 months</span>
              </li>
            </ul>
          </section>
        )}
      </div>
    </div>
  )
}

