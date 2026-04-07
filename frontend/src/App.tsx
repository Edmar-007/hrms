import { Routes, Route, Navigate } from 'react-router-dom'
import { Suspense, useEffect } from 'react'
import Layout from '@/components/Layout/Layout'
import { useAuth } from '@/hooks/useAuth'
import LoadingSpinner from '@/components/ui/LoadingSpinner'
import { usePrefetchHRMS } from '@/hooks/useHRMSData'

// Eager imports for "Instant Load"
import Dashboard from './pages/Dashboard'
import Employees from './pages/Employees'
import Attendance from './pages/Attendance'
import Login from './pages/Login'
import LeaveRequests from './pages/LeaveRequests'
import ExpenseClaims from './pages/ExpenseClaims'
import Profile from './pages/Profile'
import UserAccounts from './pages/UserAccounts'
import Payroll from './pages/Payroll'
import Reports from './pages/Reports'
import HRAnalytics from './pages/HRAnalytics'
import Compensation from './pages/Compensation'
import AuditLogs from './pages/AuditLogs'
import Settings from './pages/Settings'

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth()
  const { prefetch } = usePrefetchHRMS()

  useEffect(() => {
    if (isAuthenticated) {
      // Background prefetch all core data immediately on login
      prefetch('/employees')
      prefetch('/attendance')
    }
  }, [isAuthenticated, prefetch])
  
  if (isLoading) return <LoadingSpinner />
  return isAuthenticated ? <>{children}</> : <Navigate to="/login" replace />
}

function App() {
  return (
    <Suspense fallback={<LoadingSpinner />}>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route 
          element={
            <ProtectedRoute>
              <Layout />
            </ProtectedRoute>
          }
        >
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="/dashboard" element={<Dashboard />} />
          <Route path="/employees" element={<Employees />} />
          <Route path="/attendance" element={<Attendance />} />
          <Route path="/leave-requests" element={<LeaveRequests />} />
          <Route path="/expense-claims" element={<ExpenseClaims />} />
          <Route path="/profile" element={<Profile />} />
          <Route path="/user-accounts" element={<UserAccounts />} />
          <Route path="/payroll" element={<Payroll />} />
          <Route path="/payroll/summary" element={<Payroll />} />
          <Route path="/reports" element={<Reports />} />
          <Route path="/hr-analytics" element={<HRAnalytics />} />
          <Route path="/compensation" element={<Compensation />} />
          <Route path="/audit-logs" element={<AuditLogs />} />
          <Route path="/settings" element={<Settings />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Route>
      </Routes>
    </Suspense>
  )
}

export default App
