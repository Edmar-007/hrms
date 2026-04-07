import React from 'react'
import ReactDOM from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'react-hot-toast'
import './index.css'
import 'bootstrap/dist/css/bootstrap.min.css'
import App from './App'
import { BrowserRouter } from 'react-router-dom'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60 * 60 * 1000, // 1 hour for "Instant Cache"
      gcTime: 24 * 60 * 60 * 1000, // 24 hours
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})

const routerBasename = import.meta.env.DEV ? '/' : '/hrms'

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <BrowserRouter basename={routerBasename}>
      <QueryClientProvider client={queryClient}>
        <App />
        <Toaster position="top-right" />
      </QueryClientProvider>
    </BrowserRouter>
  </React.StrictMode>,
)

