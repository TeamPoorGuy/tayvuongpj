import { Route, Routes } from 'react-router'
import { PublicLayout } from './layouts/PublicLayout'
import { DashboardLayout } from './layouts/DashboardLayout'
import { HomePage } from './pages/HomePage'
import { FieldsPage } from './pages/FieldsPage'
import { FieldDetailPage } from './pages/FieldDetailPage'
import { LoginPage, RegisterPage } from './pages/AuthPages'
import { ProtectedRoute } from './components/ProtectedRoute'
import { CustomerBookingsPage, CustomerProfilePage } from './pages/customer/CustomerPages'
import { OwnerBookingsPage, OwnerDashboardPage, OwnerFieldsPage, OwnerProfilePage, OwnerReviewsPage } from './pages/owner/OwnerPages'
import { AdminBookingsPage, AdminCategoriesPage, AdminDashboardPage, AdminFieldsPage, AdminOwnersPage, AdminReviewsPage, AdminUsersPage } from './pages/admin/AdminPages'

export default function App() {
  return (
    <Routes>
      <Route element={<PublicLayout />}>
        <Route index element={<HomePage />} />
        <Route path="fields" element={<FieldsPage />} />
        <Route path="fields/:slug" element={<FieldDetailPage />} />
      </Route>
      <Route path="login" element={<LoginPage />} />
      <Route path="register" element={<RegisterPage />} />

      <Route element={<ProtectedRoute roles={['customer']} />}>
        <Route element={<DashboardLayout />}>
          <Route path="bookings" element={<CustomerBookingsPage />} />
          <Route path="profile" element={<CustomerProfilePage />} />
        </Route>
      </Route>

      <Route element={<ProtectedRoute roles={['field_owner']} />}>
        <Route element={<DashboardLayout />}>
          <Route path="owner" element={<OwnerDashboardPage />} />
          <Route path="owner/fields" element={<OwnerFieldsPage />} />
          <Route path="owner/bookings" element={<OwnerBookingsPage />} />
          <Route path="owner/reviews" element={<OwnerReviewsPage />} />
          <Route path="owner/profile" element={<OwnerProfilePage />} />
        </Route>
      </Route>

      <Route element={<ProtectedRoute roles={['admin']} />}>
        <Route element={<DashboardLayout />}>
          <Route path="admin" element={<AdminDashboardPage />} />
          <Route path="admin/users" element={<AdminUsersPage />} />
          <Route path="admin/owners" element={<AdminOwnersPage />} />
          <Route path="admin/fields" element={<AdminFieldsPage />} />
          <Route path="admin/bookings" element={<AdminBookingsPage />} />
          <Route path="admin/reviews" element={<AdminReviewsPage />} />
          <Route path="admin/categories" element={<AdminCategoriesPage />} />
        </Route>
      </Route>

      <Route path="*" element={<div className="empty-page"><h1>404</h1><p>Trang bạn tìm không tồn tại.</p></div>} />
    </Routes>
  )
}
