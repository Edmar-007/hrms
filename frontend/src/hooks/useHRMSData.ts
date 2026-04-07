import { useQuery, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

export const queryKeys = {
  employees: (params: any) => ['employees', params] as const,
  attendance: (params: any) => ['attendance', params] as const,
  leaveRequests: (params: any) => ['leaveRequests', params] as const,
  expenseClaims: (params: any) => ['expenseClaims', params] as const,
}

export function useEmployees(params: { page: number; limit: number; search: string }) {
  return useQuery({
    queryKey: queryKeys.employees(params),
    queryFn: async () => {
      const { data } = await api.get('/employees.php', { params })
      return data
    },
    staleTime: 30000, // 30 seconds
  })
}

export function useAttendance(params: { page: number; limit: number; search: string }) {
  return useQuery({
    queryKey: queryKeys.attendance(params),
    queryFn: async () => {
      const { data } = await api.get('/attendance.php', { params })
      return data
    },
    staleTime: 30000,
  })
}

export function usePrefetchHRMS() {
  const queryClient = useQueryClient()

  const prefetch = (key: string) => {
    const defaultParams = { page: 1, limit: 20, search: '' }
    
    if (key === '/employees') {
      void queryClient.prefetchQuery({
        queryKey: queryKeys.employees(defaultParams),
        queryFn: async () => {
          const { data } = await api.get('/employees.php', { params: defaultParams })
          return data
        },
      })
    } else if (key === '/attendance') {
      void queryClient.prefetchQuery({
        queryKey: queryKeys.attendance(defaultParams),
        queryFn: async () => {
          const { data } = await api.get('/attendance.php', { params: defaultParams })
          return data
        },
      })
    }
  }

  return { prefetch }
}
