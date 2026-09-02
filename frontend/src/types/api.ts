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
  field_owner_profile?: OwnerProfile | null
}

export interface OwnerProfile {
  id: number
  business_name: string
  business_address: string
  business_phone: string
  business_license: string | null
  description: string | null
  verification_status: string
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
