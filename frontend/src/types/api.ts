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
  field_types_count: number
}

export interface FieldType {
  id: number
  name: string
  slug: string
  category?: Pick<Category, 'id' | 'name' | 'slug' | 'icon'>
}

export interface SportsField {
  id: number
  name: string
  slug: string
  address: string
  latitude?: number | string | null
  longitude?: number | string | null
  description: string | null
  price_per_hour: number
  status: string
  is_active: boolean
  average_rating: number
  reviews_count: number
  primary_image: string | null
  field_type?: FieldType
  owner?: { id: number; name: string; phone: string | null; business?: OwnerProfile | null }
  images?: Array<{ id: number; url: string; is_primary: boolean }>
  time_slots?: TimeSlot[]
  reviews?: Review[]
  bookings_count?: number
}

export interface TimeSlot {
  id: number
  start_time: string
  end_time: string
  is_active?: boolean
  is_booked?: boolean
  is_past?: boolean
  price_per_hour?: number | null
}

export interface Review {
  id: number
  rating: number
  comment: string | null
  is_visible: boolean
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
  cancel_reason: string | null
  can_be_cancelled: boolean
  check_in_code?: string | null
  checked_in_at?: string | null
  field?: SportsField
  customer?: User
  time_slot?: TimeSlot
  review?: Review | null
  created_at: string
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
  total_revenue: number
  current_month_bookings: number
  current_month_revenue: number
  most_booked_field: SportsField | null
  recent_bookings: Booking[]
}

export interface AdminDashboard {
  total_customers: number
  total_field_owners: number
  pending_owners: number
  total_fields: number
  pending_fields: number
  total_bookings: number
  total_revenue: number
  most_booked_fields: SportsField[]
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
  user?: Pick<User, 'id' | 'name' | 'avatar' | 'phone'>
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
  contact_phone: string
  status: 'open' | 'full' | 'cancelled' | 'completed'
  host?: Pick<User, 'id' | 'name' | 'avatar' | 'trust_score'>
  sport_category?: Category
  sports_field?: SportsField
  participants?: MatchParticipant[]
  created_at: string
}
