import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { MutationCache, QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router'
import '@fontsource/barlow/latin-400.css'
import '@fontsource/barlow/latin-ext-400.css'
import '@fontsource/barlow/vietnamese-400.css'
import '@fontsource/barlow/latin-500.css'
import '@fontsource/barlow/latin-ext-500.css'
import '@fontsource/barlow/vietnamese-500.css'
import '@fontsource/barlow/latin-600.css'
import '@fontsource/barlow/latin-ext-600.css'
import '@fontsource/barlow/vietnamese-600.css'
import '@fontsource/barlow/latin-700.css'
import '@fontsource/barlow/latin-ext-700.css'
import '@fontsource/barlow/vietnamese-700.css'
import '@fontsource/barlow-condensed/latin-600.css'
import '@fontsource/barlow-condensed/latin-ext-600.css'
import '@fontsource/barlow-condensed/vietnamese-600.css'
import '@fontsource/barlow-condensed/latin-700.css'
import '@fontsource/barlow-condensed/latin-ext-700.css'
import '@fontsource/barlow-condensed/vietnamese-700.css'
import '@fontsource/barlow-condensed/latin-800.css'
import '@fontsource/barlow-condensed/latin-ext-800.css'
import '@fontsource/barlow-condensed/vietnamese-800.css'
import './index.css'
import './styles/components.css'
import App from './App.tsx'
import { AuthProvider } from './context/AuthContext'

const queryClient: QueryClient = new QueryClient({
  // Mọi thao tác ghi thành công đều làm mới dữ liệu đang hiển thị, để giao diện cập nhật ngay
  // mà không cần F5 (kể cả những chỗ quên invalidate đúng khoá).
  mutationCache: new MutationCache({
    onSuccess: () => {
      void queryClient.invalidateQueries()
    },
  }),
  defaultOptions: { queries: { staleTime: 30_000, retry: 1 } },
})

import { ToastProvider } from './components/Toast'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <ToastProvider>
            <App />
          </ToastProvider>
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
)
