import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

interface User {
  id: number
  name: string
  email: string
  role: string
  theme: 'light' | 'dark'
}

export function useAuth() {
  const queryClient = useQueryClient()

  const { data: user, isLoading, error } = useQuery<User | null>({
    queryKey: ['auth'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/me.php')
        return data as User
      } catch {
        return null
      }
    },
    staleTime: 5 * 60 * 1000,
    retry: false,
    refetchOnWindowFocus: false,
  })

  const loginMutation = useMutation({
    mutationFn: (credentials: { email: string; password: string }) => 
      api.post('/login.php', credentials),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['auth'] }),
  })

  const logoutMutation = useMutation({
    mutationFn: () => api.post('/logout.php'),
    onSuccess: () => {
      queryClient.setQueryData(['auth'], null)
      window.location.href = import.meta.env.DEV ? '/login' : '/hrms/login'
    },
  })

  return {
    user,
    isAuthenticated: !!user,
    isLoading,
    error,
    login: loginMutation,
    logout: logoutMutation,
  }
}

