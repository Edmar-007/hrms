import { useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import { toast } from 'react-hot-toast'
import api from '@/lib/api'

interface ProfileData {
  name: string
  email: string
  department: string
  role: string
}

export default function Profile() {
  const [profile, setProfile] = useState<ProfileData | null>(null)
  const [editMode, setEditMode] = useState(false)
  const [form, setForm] = useState<ProfileData>({
    name: '',
    email: '',
    department: '',
    role: '',
  })
  const [isSaving, setIsSaving] = useState(false)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // Simulate API call to fetch profile
    async function loadProfile() {
      setIsLoading(true)
      try {
        // Replace with real API call
        const { data } = await api.get('/me.php')
        setProfile(data)
        setForm(data)
      } catch {
        setProfile({
          name: 'System Admin',
          email: 'admin@hrms.local',
          department: 'HR',
          role: 'HR Specialist',
        })
        setForm({
          name: 'System Admin',
          email: 'admin@hrms.local',
          department: 'HR',
          role: 'HR Specialist',
        })
      } finally {
        setIsLoading(false)
      }
    }
    loadProfile()
  }, [])

  function handleEdit() {
    setEditMode(true)
  }

  function handleCancel() {
    setEditMode(false)
    if (profile) setForm(profile)
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault()
    setIsSaving(true)
    try {
      // Replace with real API call
      await api.post('/me.php', form)
      setProfile(form)
      setEditMode(false)
      toast.success('Profile updated!')
    } catch {
      toast.error('Failed to update profile.')
    } finally {
      setIsSaving(false)
    }
  }

  if (isLoading) {
    return <div className="flex h-96 items-center justify-center text-slate-500">Loading profile...</div>
  }

  return (
    <div className="page-enter space-y-6">
      <section className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-3xl tracking-tight text-slate-900">My Profile</h2>
          <p className="mt-1 text-sm text-slate-600">Update your personal details and account preferences.</p>
        </div>
        <div className="flex items-center gap-2">
          {editMode ? (
            <Button className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-slate-700 hover:bg-slate-100" onClick={handleCancel} disabled={isSaving}>
              Cancel
            </Button>
          ) : (
            <Button className="h-10 rounded-xl border border-slate-300 bg-white px-4 text-slate-700 hover:bg-slate-100" onClick={handleEdit}>
              Edit Details
            </Button>
          )}
          {editMode && (
            <Button className="h-10 rounded-xl bg-slate-900 px-4 text-white hover:bg-slate-800" type="submit" form="profile-form" disabled={isSaving}>
              {isSaving ? 'Saving...' : 'Save Profile'}
            </Button>
          )}
        </div>
      </section>

      <section className="surface-card surface-card-hover p-5 max-w-xl">
        <form id="profile-form" onSubmit={handleSave} className="space-y-5">
          <div>
            <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Full Name</label>
            <input
              className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
              type="text"
              value={form.name}
              onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
              disabled={!editMode}
              required
            />
          </div>
          <div>
            <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Email</label>
            <input
              className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
              type="email"
              value={form.email}
              onChange={e => setForm(f => ({ ...f, email: e.target.value }))}
              disabled={!editMode}
              required
            />
          </div>
          <div>
            <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Department</label>
            <input
              className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
              type="text"
              value={form.department}
              onChange={e => setForm(f => ({ ...f, department: e.target.value }))}
              disabled={!editMode}
              required
            />
          </div>
          <div>
            <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Role</label>
            <input
              className="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-sky-400"
              type="text"
              value={form.role}
              onChange={e => setForm(f => ({ ...f, role: e.target.value }))}
              disabled={!editMode}
              required
            />
          </div>
        </form>
      </section>
    </div>
  )
}
