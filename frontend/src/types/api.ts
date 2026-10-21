export type Role = 'customer' | 'field_owner' | 'admin'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  phone: string | null
  avatar: string | null
  address: string | null
  is_active: boolean
  trust_score?: number
  email_verified?: boolean
  has_password?: boolean
  is_field_owner?: boolean
  field_owner_profile?: OwnerProfile | null
}

export interface OwnerProfile {
  id: number
  owner_name: string | null
  owner_id_number: string | null
  business_name: string
  business_address: string
  business_phone: string
  business_license: string | null
  description: string | null
  verification_status: 'pending' | 'approved' | 'rejected' | 'suspended'
  bank_name?: string | null
  bank_bin?: string | null
  bank_account_number?: string | null
  bank_account_holder?: string | null
  terms_version?: string | null
  terms_accepted_at?: string | null
  documents?: { id: number; type: string; original_name: string; url: string }[]
  rejection_reason: string | null
  verified_at: string | null
  user?: User
}

export interface Category {
  id: number
  name: string
  slug: string
  icon: string | null
  description: string | null
  is_active: boolean
  field_types_count: number
}

export interface FieldType {
  id: number
  name: string
  slug: string
  category?: Pick<Category, 'id' | 'name' | 'slug' | 'icon'>
}

export interface LocationGroup {
  city: string
  districts: string[]
}

export interface SportsField {
  id: number
  name: string
  slug: string
  address: string
  city?: string | null
  district?: string | null
  latitude?: number | string | null
  longitude?: number | string | null
  description: string | null
  price_per_hour: number
  status: string
  rejection_reason?: string | null
  is_active: boolean
  average_rating: number
  reviews_count: number
  primary_image: string | null
  field_type?: FieldType
  owner?: { id: number; name: string; phone: string | null; business?: OwnerProfile | null }
  images?: Array<{ id: number; url: string; is_primary: boolean }>
  time_slots?: TimeSlot[]
  reviews?: Review[]
  closures?: FieldClosure[]
  bookings_count?: number
}

export interface FieldClosure {
  id: number
  sports_field_id: number
  start_date: string
  end_date: string
  reason: string
  created_at?: string
}

export interface TimeSlot {
  id: number
  start_time: string
  end_time: string
  is_active?: boolean
  is_booked?: boolean
  is_past?: boolean
  is_held?: boolean
  price_per_hour?: number | null
}

export interface Review {
  id: number
  rating: number
  comment: string | null
  is_visible: boolean
  owner_reply?: string | null
  owner_replied_at?: string | null
  user?: Pick<User, 'id' | 'name' | 'avatar'>
  field?: Pick<SportsField, 'id' | 'name' | 'slug'>
  created_at: string
}

export interface Booking {
  id: number
  booking_date: string
  status: string
  total_price: number
  notes: string | null
  recurring_group_id?: string | null
  cancelled_at?: string | null
  cancel_reason: string | null
  can_be_cancelled: boolean
  cancel_penalty?: boolean
  cancel_until?: string | null
  confirmed_at?: string | null
  no_show?: boolean
  check_in_code?: string | null
  checked_in_at?: string | null
  payment?: BookingPayment
  refund?: BookingRefund | null
  commission?: {
    rate: number
    amount: number
    base: number
    statement_id?: number | null
  }
  bank?: { bank_name: string | null; bank_bin?: string | null; account_number: string | null; account_holder: string | null }
  field?: SportsField
  customer?: User
  time_slot?: TimeSlot
  review?: Review | null
  created_at: string
}

export interface BookingRefund {
  status: 'pending' | 'done' | 'waived'
  amount: number
  refunded_at: string | null
}

/** Thanh toán chuyển khoản: null = chưa duyệt, awaiting = chờ khách chuyển, reported = khách báo đã chuyển, paid = đã nhận tiền. */
export interface BookingPayment {
  status: 'awaiting' | 'reported' | 'paid' | null
  option: 'deposit' | 'full' | null
  amount: number | null
  code: string | null
  due_at: string | null
  reported_at: string | null
  paid_at: string | null
  deposit_amount: number
  deposit_percent: number
  proof_url?: string | null
}

export interface OwnerStatement {
  id: number
  owner_profile_id: number
  period: string
  booking_count: number
  gmv: number
  commission_amount: number
  status: 'unpaid' | 'reported' | 'paid' | 'waived'
  due_at: string | null
  is_overdue: boolean
  transfer_code: string
  reported_at: string | null
  paid_at: string | null
  note: string | null
  platform_bank?: {
    bank_name: string
    account_number: string
    account_holder: string
    qr_url: string | null
  }
  owner?: {
    id: number
    business_name: string
    user_id: number
    user_name: string
    user_email: string
    user_phone: string
  }
  confirmed_by?: {
    id: number
    name: string
  }
  bookings?: Booking[]
  created_at: string
  updated_at: string
}

export interface StatementTotals {
  unpaid_amount: number
  unpaid_count: number
  paid_amount: number
  paid_count: number
}

export type StatementList = Paginated<OwnerStatement> & { meta: { totals: StatementTotals } }

export interface CurrentStatementSummary {
  period: string
  booking_count: number
  estimated_gmv: number
  estimated_commission: number
  commission_rate: number
  has_overdue_debt: boolean
}

export interface HomeData {
  categories: Category[]
  featured_fields: SportsField[]
}

export interface ApiEnvelope<T> {
  data: T
  message?: string
}

export interface Paginated<T> {
  data: T[]
  links: unknown
  meta: { current_page: number; last_page: number; total: number }
}

export interface OwnerDashboard {
  total_fields: number
  total_bookings: number
  pending_bookings: number
  total_gmv: number
  current_month_bookings: number
  current_month_gmv: number
  current_month_collected?: number
  current_month_commission?: number
  current_month_net_revenue?: number
  has_overdue_debt?: boolean
  statement_summary?: CurrentStatementSummary
  most_booked_field: SportsField | null
  recent_bookings: Booking[]
}

export interface AdminDashboard {
  total_gmv: number
  total_commission_collected: number
  total_commission_pending: number
}

export interface BookingWaitlist {
  id: number
  user_id: number
  sports_field_id: number
  time_slot_id: number
  booking_date: string
  status: 'waiting' | 'offered' | 'accepted' | 'expired' | 'cancelled'
  priority_score: number
  offered_at?: string | null
  offer_expires_at?: string | null
  sports_field?: SportsField
  time_slot?: TimeSlot
  created_at: string
}

export interface SlotSuggestion {
  type: string
  field_id: number
  field_name: string
  field_slug: string
  time_slot_id: number
  start_time: string
  end_time: string
  date: string
  date_label: string
  price_per_hour: number
  badge: string
}

export interface MatchParticipant {
  id: number
  match_request_id: number
  user_id: number
  status: 'pending' | 'accepted' | 'rejected'
  message?: string | null
  user?: Pick<User, 'id' | 'name' | 'avatar' | 'phone' | 'trust_score'>
  created_at: string
}

export interface MatchRequest {
  id: number
  user_id: number
  sport_category_id: number
  sports_field_id?: number | null
  title: string
  play_date: string
  start_time: string
  end_time: string
  skill_level: 'beginner' | 'intermediate' | 'advanced' | 'any'
  players_needed: number
  joined_count: number
  description?: string | null
  contact_phone: string | null
  status: 'open' | 'full' | 'cancelled' | 'completed'
  host?: Pick<User, 'id' | 'name' | 'avatar' | 'trust_score'>
  sport_category?: Category
  sports_field?: SportsField
  participants?: MatchParticipant[]
  created_at: string
}

export interface TrustScoreLog {
  id: number
  user_id: number
  change: number
  score_after: number
  reason: string
  booking_id: number | null
  sports_field_name?: string | null
  created_at: string
}

export interface PublicSettings {
  commission_percent: number
  statement_due_days: number
  deposit_percent: number
  payment_window_hours: number
  free_cancel_hours: number
  max_pending_bookings: number
  max_advance_days: number
  platform_bank_name: string
  platform_bank_code: string
  platform_bank_account: string
  platform_bank_holder: string
  support_email: string
  support_hotline: string
  owner_terms_version: string
}

export interface AdminLog {
  id: number
  admin_id: number | null
  action: string
  subject_type: string | null
  subject_id: string | null
  meta?: Record<string, unknown> | null
  created_at: string
  admin?: Pick<User, 'id' | 'name' | 'email'>
}

export interface Complaint {
  id: number
  user_id: number | null
  user?: Pick<User, 'id' | 'name' | 'email' | 'phone'> | null
  booking_id: number | null
  booking?: {
    id: number
    booking_date: string
    status: string
    total_price: number
    refund_status: 'pending' | 'done' | 'waived' | null
    refund_amount: number | null
    field_name?: string
  } | null
  subject_type: 'booking' | 'contact'
  subject_id: string | null
  category: string
  content: string
  status: 'open' | 'processing' | 'resolved' | 'rejected'
  resolution: string | null
  handled_by: number | null
  handler?: Pick<User, 'id' | 'name'> | null
  handled_at: string | null
  created_at: string
  updated_at: string
}

export interface AdminDashboardData {
  period: 'this_month' | 'last_month'
  from_date: string
  to_date: string
  kpis: {
    gmv: number
    paid_amount: number
    commission_accrued: number
    commission_collected: number
    commission_debt: number
    total_bookings: number
    cancelled_bookings: number
    cancel_rate: number
    no_show_bookings: number
    no_show_rate: number
    new_users: number
    new_owners: number
  }
  daily_series: Array<{
    date: string
    label: string
    gmv: number
    commission: number
    booking_count: number
  }>
  top_fields: SportsField[]
  action_queue: {
    pending_owners: number
    pending_fields: number
    open_complaints: number
    reported_statements: number
    pending_refunds: number
  }
  total_gmv?: number
  total_commission_collected?: number
  total_commission_pending?: number
}

export interface AdminUserDetail {
  user: User
  stats: {
    total_bookings: number
    cancelled_bookings: number
    no_show_bookings: number
    complaints_count: number
  }
  recent_bookings: Booking[]
  recent_trust_scores: TrustScoreLog[]
}

