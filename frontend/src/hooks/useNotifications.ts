import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

interface Notification {
  id: number
  title: string
  message: string
  is_read: 0 | 1
  created_at: string
  time_ago: string
}

export function useNotifications() {
  const queryClient = useQueryClient()

  const { data: notifications = [], isLoading } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => api.get('/notifications.php')
      .then(res => res.data.notifications),
    refetchInterval: 30000,
    refetchIntervalInBackground: true,
  })

  const unreadCount = notifications.filter((n: Notification) => n.is_read === 0).length

  const markAllRead = useMutation({
    mutationFn: () => api.post('/notifications.php?action=read_all', {}),
    onSuccess: () => {
      queryClient.setQueryData(['notifications'], (old: Notification[] | undefined) =>
        old?.map(n => ({ ...n, is_read: 1 }))
      )
    },
  })

  return {
    notifications,
    unreadCount,
    markAllRead: markAllRead.mutate,
    isMarkingAllRead: markAllRead.isPending,
    isLoading,
  }
}

