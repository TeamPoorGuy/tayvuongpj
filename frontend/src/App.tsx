import { Route, Routes } from 'react-router'
import { PublicLayout } from './layouts/PublicLayout'
import { DashboardLayout } from './layouts/DashboardLayout'
import { HomePage } from './pages/HomePage'
import { FieldsPage } from './pages/FieldsPage'
import { FieldDetailPage } from './pages/FieldDetailPage'
import { MatchesPage } from './pages/MatchesPage'
import { EmailVerifiedPage, ForgotPasswordPage, LoginPage, RegisterPage, ResetPasswordPage } from './pages/AuthPages'
import { NotFoundPage } from './pages/NotFoundPage'
import { ProtectedRoute } from './components/ProtectedRoute'
import { VerifyGate } from './components/VerifyGate'
import { CustomerBookingsPage, CustomerProfilePage } from './pages/customer/CustomerPages'
import { BookingPaymentPage } from './pages/customer/BookingPaymentPage'
import { OwnerApplicationPage } from './pages/customer/OwnerApplicationPage'
import { OwnerBookingsPage, OwnerDashboardPage, OwnerFieldsPage, OwnerProfilePage, OwnerReviewsPage } from './pages/owner/OwnerPages'
import { OwnerSchedulePage } from './pages/owner/OwnerSchedulePage'
import { OwnerStatementsPage } from './pages/owner/OwnerStatementsPage'
import { AdminBookingsPage, AdminCategoriesPage, AdminFieldsPage, AdminMatchesPage, AdminOwnersPage, AdminReviewsPage, AdminUsersPage } from './pages/admin/AdminPages'
import { AdminDashboardPage } from './pages/admin/AdminDashboardPage'
import { AdminComplaintsPage } from './pages/admin/AdminComplaintsPage'
import { AdminSettingsPage } from './pages/admin/AdminSettingsPage'
import { AdminLogsPage } from './pages/admin/AdminLogsPage'
import { AdminStatementsPage } from './pages/admin/AdminStatementsPage'
import { AdminPaymentsPage } from './pages/admin/AdminPaymentsPage'
import {
  AboutPage,
  ContactPage,
  FaqPage,
  OwnerRegulationsPage,
  PrivacyPage,
  RefundPolicyPage,
  TermsPage,
} from './pages/PolicyPages'

export default function App() {
  return (
    <VerifyGate>
    <Routes>
      <Route element={<PublicLayout />}>
        <Route index element={<HomePage />} />
        <Route path="fields" element={<FieldsPage />} />
        <Route path="fields/:slug" element={<FieldDetailPage />} />
        <Route path="matches" element={<MatchesPage />} />
        <Route path="dieu-khoan" element={<TermsPage />} />
        <Route path="chinh-sach-bao-mat" element={<PrivacyPage />} />
        <Route path="chinh-sach-huy-hoan" element={<RefundPolicyPage />} />
        <Route path="quy-che-chu-san" element={<OwnerRegulationsPage />} />
        <Route path="cau-hoi-thuong-gap" element={<FaqPage />} />
        <Route path="gioi-thieu" element={<AboutPage />} />
        <Route path="lien-he" element={<ContactPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
      <Route path="login" element={<LoginPage />} />
      <Route path="register" element={<RegisterPage />} />
      <Route path="forgot-password" element={<ForgotPasswordPage />} />
      <Route path="reset-password" element={<ResetPasswordPage />} />
      <Route path="email-verified" element={<EmailVerifiedPage />} />

      <Route element={<ProtectedRoute roles={['customer']} />}>
        <Route element={<DashboardLayout />}>
          <Route path="bookings" element={<CustomerBookingsPage />} />
          <Route path="payments/:id" element={<BookingPaymentPage />} />
          <Route path="profile" element={<CustomerProfilePage />} />
          <Route path="become-owner" element={<OwnerApplicationPage />} />
        </Route>
      </Route>

      <Route element={<ProtectedRoute roles={['customer']} requireApprovedOwner />}>
        <Route element={<DashboardLayout />}>
          <Route path="owner" element={<OwnerDashboardPage />} />
          <Route path="owner/schedule" element={<OwnerSchedulePage />} />
          <Route path="owner/fields" element={<OwnerFieldsPage />} />
          <Route path="owner/bookings" element={<OwnerBookingsPage />} />
          <Route path="owner/statements" element={<OwnerStatementsPage />} />
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
          <Route path="admin/complaints" element={<AdminComplaintsPage />} />
          <Route path="admin/statements" element={<AdminStatementsPage />} />
          <Route path="admin/payments" element={<AdminPaymentsPage />} />
          <Route path="admin/matches" element={<AdminMatchesPage />} />
          <Route path="admin/reviews" element={<AdminReviewsPage />} />
          <Route path="admin/categories" element={<AdminCategoriesPage />} />
          <Route path="admin/settings" element={<AdminSettingsPage />} />
          <Route path="admin/logs" element={<AdminLogsPage />} />
        </Route>
      </Route>
    </Routes>
    </VerifyGate>
  )
}
