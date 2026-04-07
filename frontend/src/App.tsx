import { Routes, Route, Navigate } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import Layout from '@/components/Layout/Layout'
import { useAuth } from '@/hooks/useAuth'
import LoadingSpinner from '@/components/ui/LoadingSpinner'

const Dashboard = lazy(() => import('./pages/Dashboard'))
const Login = lazy(() => import('./pages/Login'))
const Employees = lazy(() => import('./pages/Employees'))
const Attendance = lazy(() => import('./pages/Attendance'))
const LeaveRequests = lazy(() => import('./pages/LeaveRequests'))
const ExpenseClaims = lazy(() => import('./pages/ExpenseClaims'))
const Profile = lazy(() => import('./pages/Profile'))
const UserAccounts = lazy(() => import('./pages/UserAccounts'))
const Payroll = lazy(() => import('./pages/Payroll'))
const Reports = lazy(() => import('./pages/Reports'))
const HRAnalytics = lazy(() => import('./pages/HRAnalytics'))
const Compensation = lazy(() => import('./pages/Compensation'))
const AuditLogs = lazy(() => import('./pages/AuditLogs'))
const Settings = lazy(() => import('./pages/Settings'))

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth()
  
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

