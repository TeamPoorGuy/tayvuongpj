import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Building2,
  CalendarDays,
  ChartNoAxesCombined,
  CreditCard,
  ExternalLink,
  Eye,
  FileText,
  FolderTree,
  Search,
  Trophy,
  MessageSquareText,
  ShieldAlert,
  ShieldCheck,
  UserRound,
  UsersRound,
} from 'lucide-react'
import { Fragment, useEffect, useState, type FormEvent } from 'react'
import { api } from '../../api/client'
import { errorMessage } from '../../api/errors'
import { AnimatedCounter } from '../../components/AnimatedCounter'
import { Link, useSearchParams } from 'react-router'
import { ConfirmModal } from '../../components/ConfirmModal'
import { Modal } from '../../components/Modal'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { Pagination } from '../../components/Pagination'
import { QueryState } from '../../components/QueryState'
import { StatusBadge } from '../../components/StatusBadge'
import { useToast } from '../../components/Toast'
import type {
  AdminDashboard,
  ApiEnvelope,
  Booking,
  Category,
  MatchRequest,
  OwnerProfile,
  Paginated,
  Review,
  SportsField,
  User,
} from '../../types/api'
import { Empty, PageTitle } from '../customer/CustomerPages'

const currency = new Intl.NumberFormat('vi-VN')

export function AdminDashboardPage() {
  const dashboard = useQuery({
    queryKey: ['admin', 'dashboard'],
    queryFn: async () => (await api.get<ApiEnvelope<AdminDashboard>>('/admin/dashboard')).data.data,
  })
  const data = dashboard.data
  return (
    <>
      <PageTitle
        icon={<ChartNoAxesCombined />}
        kicker="Toàn hệ thống"
        title="Trung tâm điều hành"
        copy="Một góc nhìn rõ ràng cho người dùng, nguồn cung và giao dịch."
      />
      <div className="stats-grid">
        <Stat label="Khách hàng" value={data?.total_customers ?? 0} />
        <Stat
          label="Chủ sân"
          value={data?.total_field_owners ?? 0}
          note={`${data?.pending_owners ?? 0} chờ duyệt`}
          to="/admin/owners?status=pending"
        />
        <Stat
          label="Sân thể thao"
          value={data?.total_fields ?? 0}
          note={`${data?.pending_fields ?? 0} chờ duyệt`}
          to="/admin/fields?status=pending"
        />
        <Stat label="Doanh thu" value={`${currency.format(data?.total_revenue ?? 0)}đ`} />
      </div>
      <section className="data-panel">
        <div className="data-panel__head">
          <div>
            <span>Xếp hạng</span>
            <h2>Sân được đặt nhiều nhất</h2>
          </div>
        </div>
        <div className="ranking">
          {data?.most_booked_fields.map((field, index) => (
            <article key={field.id}>
              <b>{String(index + 1).padStart(2, '0')}</b>
              <div>
                <strong>{field.name}</strong>
                <small>{field.address}</small>
              </div>
              <span>{field.bookings_count} lượt đặt</span>
            </article>
          ))}
        </div>
      </section>
    </>
  )
}

export function AdminUsersPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [keyword, setKeyword] = useState('')
  const [debouncedKeyword, setDebouncedKeyword] = useState('')
  const [role, setRole] = useState('')
  const [page, setPage] = useState(1)
  const [togglingUser, setTogglingUser] = useState<User | null>(null)

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedKeyword(keyword)
      setPage(1)
    }, 320)
    return () => clearTimeout(handler)
  }, [keyword])

  const users = useQuery({
    queryKey: ['admin', 'users', debouncedKeyword, role, page],
    queryFn: async () =>
      (
        await api.get<Paginated<User>>(
          `/admin/users?page=${page}&keyword=${encodeURIComponent(debouncedKeyword)}&role=${role}`,
        )
      ).data,
  })

  const toggle = useMutation({
    mutationFn: async (user: User) => api.patch(`/admin/users/${user.id}/toggle`),
    onSuccess: (_, user) => {
      toast.success(
        user.is_active
          ? `Đã khóa tài khoản ${user.name}.`
          : `Đã mở khóa tài khoản ${user.name}.`,
      )
      setTogglingUser(null)
      void client.invalidateQueries({ queryKey: ['admin', 'users'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Lỗi đổi trạng thái tài khoản')
    },
  })

  const handleRoleChange = (newRole: string) => {
    setRole(newRole)
    setPage(1)
  }

  return (
    <>
      <PageTitle
        icon={<UsersRound />}
        kicker="Tài khoản"
        title="Cộng đồng SportHub"
        copy="Tra cứu và kiểm soát trạng thái truy cập của người dùng."
      />
      <div className="toolbar">
        <input
          value={keyword}
          onChange={(e) => setKeyword(e.target.value)}
          placeholder="Tìm theo tên, email, số điện thoại..."
        />
        <select value={role} onChange={(e) => handleRoleChange(e.target.value)}>
          <option value="">Tất cả vai trò</option>
          <option value="customer">Khách hàng</option>
          <option value="field_owner">Chủ sân</option>
        </select>
      </div>
      <QueryState
        isPending={users.isPending}
        isError={users.isError}
        isEmpty={!users.data?.data.length}
        error={errorMessage(users.error)}
        empty={<Empty text="Chưa có người dùng phù hợp." />}
      >
        <DataTable
          heads={['Người dùng', 'Liên hệ', 'Vai trò', 'Trạng thái', 'Thao tác']}
          rows={
            users.data?.data.map((user) => [
            <strong key="user">{user.name}</strong>,
            <Fragment key="contact">
              <span>{user.email}</span>
              <small>{user.phone ?? '—'}</small>
            </Fragment>,
            user.role === 'field_owner' ? 'Chủ sân' : 'Khách hàng',
            <StatusBadge key="status" status={user.is_active ? 'approved' : 'inactive'} />,
            <button
              key="action"
              className={`button button--small ${user.is_active ? 'button--danger' : 'button--brand'}`}
              onClick={() => setTogglingUser(user)}
              disabled={toggle.isPending}
            >
              {user.is_active ? 'Khóa' : 'Mở khóa'}
            </button>,
            ]) ?? []
          }
        />
      </QueryState>
      <Pagination
        page={page}
        lastPage={users.data?.meta.last_page ?? 1}
        total={users.data?.meta.total}
        onPageChange={setPage}
      />

      <ConfirmModal
        isOpen={!!togglingUser}
        title={togglingUser?.is_active ? 'Xác nhận khóa tài khoản' : 'Xác nhận mở khóa tài khoản'}
        message={
          togglingUser?.is_active
            ? `Bạn có chắc muốn khóa tài khoản "${togglingUser?.name}" (${togglingUser?.email})? Người dùng này sẽ không thể tiếp tục đăng nhập.`
            : `Bạn có muốn mở lại quyền truy cập cho tài khoản "${togglingUser?.name}"?`
        }
        confirmText={togglingUser?.is_active ? 'Khóa tài khoản' : 'Mở khóa'}
        cancelText="Đóng"
        danger={!!togglingUser?.is_active}
        busy={toggle.isPending}
        onClose={() => setTogglingUser(null)}
        onConfirm={() => {
          if (togglingUser) toggle.mutate(togglingUser)
        }}
      />
    </>
  )
}

export function AdminOwnersPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [params] = useSearchParams()
  const [status, setStatus] = useState(params.get('status') ?? '')
  const [page, setPage] = useState(1)
  const [rejecting, setRejecting] = useState<OwnerProfile | null>(null)
  const [suspending, setSuspending] = useState<OwnerProfile | null>(null)
  const [approving, setApproving] = useState<OwnerProfile | null>(null)
  const [viewingProfile, setViewingProfile] = useState<OwnerProfile | null>(null)

  const profiles = useQuery({
    queryKey: ['admin', 'owners', status, page],
    queryFn: async () =>
      (
        await api.get<Paginated<OwnerProfile>>(
          `/admin/field-owners?page=${page}${status ? `&status=${status}` : ''}`,
        )
      ).data,
  })

  const currentViewingProfile =
    profiles.data?.data.find((p) => p.id === viewingProfile?.id) ?? viewingProfile

  const verify = useMutation({
    mutationFn: async ({
      profile,
      status: next,
      rejection_reason,
    }: {
      profile: OwnerProfile
      status: string
      rejection_reason?: string
    }) =>
      api.patch(`/admin/field-owners/${profile.id}/verify`, {
        status: next,
        ...(rejection_reason ? { rejection_reason } : {}),
      }),
    onSuccess: (_, vars) => {
      const msg =
        vars.status === 'approved'
          ? `Đã duyệt cấp quyền chủ sân cho ${vars.profile.business_name}.`
          : vars.status === 'suspended'
            ? `Đã thu hồi quyền chủ sân của ${vars.profile.business_name}.`
            : `Đã từ chối hồ sơ của ${vars.profile.business_name}.`
      toast.success(msg)
      setRejecting(null)
      setSuspending(null)
      setApproving(null)
      void client.invalidateQueries({ queryKey: ['admin', 'owners'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Xử lý hồ sơ thất bại')
    },
  })

  const submitReject = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!rejecting) return
    verify.mutate({
      profile: rejecting,
      status: 'rejected',
      rejection_reason: String(new FormData(event.currentTarget).get('rejection_reason')),
    })
  }

  const submitSuspend = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!suspending) return
    verify.mutate({
      profile: suspending,
      status: 'suspended',
      rejection_reason: String(new FormData(event.currentTarget).get('rejection_reason')),
    })
  }

  const handleStatusChange = (newStatus: string) => {
    setStatus(newStatus)
    setPage(1)
  }

  return (
    <>
      <PageTitle
        icon={<ShieldCheck />}
        kicker="Kiểm duyệt"
        title="Hồ sơ chủ sân"
        copy="Xác minh thông tin cơ sở trước khi cấp quyền hoặc thu hồi khi có vi phạm."
      />
      <div className="toolbar">
        <select value={status} onChange={(e) => handleStatusChange(e.target.value)}>
          <option value="">Tất cả trạng thái</option>
          <option value="pending">Chờ duyệt</option>
          <option value="approved">Đã duyệt</option>
          <option value="suspended">Đã thu hồi / đình chỉ</option>
          <option value="rejected">Từ chối</option>
        </select>
      </div>
      <QueryState
        isPending={profiles.isPending}
        isError={profiles.isError}
        isEmpty={!profiles.data?.data.length}
        error={errorMessage(profiles.error)}
        empty={<Empty text="Không có hồ sơ chủ sân phù hợp." />}
      >
        <DataTable
          heads={['Chủ sân', 'Cơ sở', 'Địa chỉ', 'Giấy tờ', 'Trạng thái', 'Hành động']}
          rows={
            profiles.data?.data.map((profile) => [
            <Fragment key="owner">
              <strong>{profile.user?.name}</strong>
              <small>{profile.user?.email}</small>
            </Fragment>,
            <Fragment key="business">
              <strong>{profile.business_name}</strong>
              <small>{profile.business_phone}</small>
            </Fragment>,
            profile.business_address,
            <Fragment key="license">
              <small>{profile.business_license ?? 'Chưa có'}</small>
              <div className="doc-links">
                {profile.documents?.map((doc) => (
                  <a key={doc.id} href={doc.url} target="_blank" rel="noreferrer">
                    {
                      (
                        {
                          id_front: 'CCCD trước',
                          id_back: 'CCCD sau',
                          business_license: 'GPKD',
                          land_right: 'Quyền sân',
                        } as Record<string, string>
                      )[doc.type] ?? doc.type
                    }
                  </a>
                ))}
              </div>
            </Fragment>,
            <Fragment key="status">
              <StatusBadge status={profile.verification_status} />
              {profile.rejection_reason && (
                <small className="form-help" style={{ display: 'block', maxWidth: 180 }}>
                  Lý do: {profile.rejection_reason}
                </small>
              )}
            </Fragment>,
            <div key="action" className="row-actions">
              <button
                className="button button--ghost button--small"
                onClick={() => setViewingProfile(profile)}
                title="Xem chi tiết hồ sơ & giấy tờ"
              >
                <Eye size={13} /> Chi tiết
              </button>
              {profile.verification_status === 'pending' && (
                <>
                  <button
                    className="button button--brand button--small"
                    onClick={() => setApproving(profile)}
                    disabled={verify.isPending}
                  >
                    Duyệt
                  </button>
                  <button
                    className="button button--danger button--small"
                    onClick={() => setRejecting(profile)}
                    disabled={verify.isPending}
                  >
                    Từ chối
                  </button>
                </>
              )}
              {profile.verification_status === 'approved' && (
                <button
                  className="button button--danger-ghost button--small"
                  onClick={() => setSuspending(profile)}
                  disabled={verify.isPending}
                  title="Thu hồi quyền quản lý sân khi có sai phạm"
                >
                  <ShieldAlert size={14} /> Thu hồi quyền
                </button>
              )}
              {profile.verification_status === 'suspended' && (
                <button
                  className="button button--brand button--small"
                  onClick={() => setApproving(profile)}
                  disabled={verify.isPending}
                >
                  Cấp lại quyền
                </button>
              )}
              {profile.verification_status === 'rejected' && (
                <small className="muted-text">Đã từ chối</small>
              )}
            </div>,
            ]) ?? []
          }
        />
      </QueryState>
      <Pagination
        page={page}
        lastPage={profiles.data?.meta.last_page ?? 1}
        total={profiles.data?.meta.total}
        onPageChange={setPage}
      />

      <ConfirmModal
        isOpen={!!approving}
        title="Xác nhận duyệt hồ sơ"
        message={`Bạn có chắc muốn cấp quyền chủ sân cho "${approving?.business_name}" (${approving?.user?.name})?`}
        confirmText="Duyệt hồ sơ"
        cancelText="Hủy"
        danger={false}
        busy={verify.isPending}
        onClose={() => setApproving(null)}
        onConfirm={() => {
          if (approving) verify.mutate({ profile: approving, status: 'approved' })
        }}
      />

      {/* Reject Modal */}
      <Modal isOpen={!!rejecting} onClose={() => setRejecting(null)} asForm onSubmit={submitReject}>
        <h2>Từ chối hồ sơ {rejecting?.business_name}</h2>
        <p className="form-help">
          Vui lòng nhập lý do từ chối để thông báo cho người đăng ký biết và bổ sung hồ sơ nếu cần.
        </p>
        <label>
          <span>Lý do từ chối</span>
          <textarea name="rejection_reason" required rows={3} placeholder="Ví dụ: Giấy phép kinh doanh mờ, CCCD không khớp..." />
        </label>
        {verify.isError && <div className="notice notice--error">{errorMessage(verify.error)}</div>}
        <div className="modal__actions">
          <button type="button" className="button button--ghost" onClick={() => setRejecting(null)}>
            Đóng
          </button>
          <button disabled={verify.isPending} className="button button--danger">
            {verify.isPending ? 'Đang xử lý...' : 'Xác nhận từ chối'}
          </button>
        </div>
      </Modal>

      {/* Suspend / Revoke Modal */}
      <Modal isOpen={!!suspending} onClose={() => setSuspending(null)} asForm onSubmit={submitSuspend}>
        <h2>Thu hồi quyền chủ sân: {suspending?.business_name}</h2>
        <p className="form-help">
          Khi thu hồi quyền, chủ sân này sẽ không thể truy cập bảng quản trị chủ sân hoặc chỉnh sửa sân bóng nữa.
        </p>
        <label>
          <span>Lý do thu hồi quyền</span>
          <textarea
            name="rejection_reason"
            required
            rows={3}
            placeholder="Ví dụ: Vi phạm chính sách giá, nhận đặt trùng lịch nhiều lần..."
          />
        </label>
        {verify.isError && <div className="notice notice--error">{errorMessage(verify.error)}</div>}
        <div className="modal__actions">
          <button type="button" className="button button--ghost" onClick={() => setSuspending(null)}>
            Đóng
          </button>
          <button disabled={verify.isPending} className="button button--danger">
            {verify.isPending ? 'Đang xử lý...' : 'Xác nhận thu hồi quyền'}
          </button>
        </div>
      </Modal>

      <OwnerDetailModal
        profile={currentViewingProfile}
        onClose={() => setViewingProfile(null)}
        onApprove={(p) => {
          setViewingProfile(null)
          setApproving(p)
        }}
        onReject={(p) => {
          setViewingProfile(null)
          setRejecting(p)
        }}
        onSuspend={(p) => {
          setViewingProfile(null)
          setSuspending(p)
        }}
      />
    </>
  )
}

function OwnerDetailModal({
  profile,
  onClose,
  onApprove,
  onReject,
  onSuspend,
}: {
  profile: OwnerProfile | null
  onClose: () => void
  onApprove: (profile: OwnerProfile) => void
  onReject: (profile: OwnerProfile) => void
  onSuspend: (profile: OwnerProfile) => void
}) {
  if (!profile) return null

  return (
    <Modal
      isOpen={!!profile}
      onClose={onClose}
      wide
      title={`Chi tiết hồ sơ đối tác · ${profile.business_name}`}
    >
      <div className="owner-detail-modal">
        <div className="owner-detail-head">
          <div>
            <h3>{profile.business_name}</h3>
            <p>{profile.description || 'Chưa có thông tin giới thiệu cơ sở.'}</p>
          </div>
          <StatusBadge status={profile.verification_status} />
        </div>

        {/* 1. Người đại diện */}
        <div className="profile-section">
          <h4>
            <UserRound size={14} /> Thông tin người đại diện pháp lý
          </h4>
          <div className="profile-detail-grid">
            <div className="profile-detail-field">
              <span>Họ và tên chủ sân</span>
              <strong>{profile.owner_name || profile.user?.name || '—'}</strong>
            </div>
            <div className="profile-detail-field">
              <span>Số CCCD / CMND</span>
              <strong>{profile.owner_id_number || '—'}</strong>
            </div>
            <div className="profile-detail-field">
              <span>Email tài khoản</span>
              <strong>{profile.user?.email || '—'}</strong>
            </div>
            <div className="profile-detail-field">
              <span>Số điện thoại</span>
              <strong>{profile.user?.phone || profile.business_phone || '—'}</strong>
            </div>
            <div className="profile-detail-field" style={{ gridColumn: 'span 2' }}>
              <span>Địa chỉ cá nhân</span>
              <strong>{profile.user?.address || '—'}</strong>
            </div>
          </div>
        </div>

        {/* 2. Cơ sở kinh doanh */}
        <div className="profile-section">
          <h4>
            <Building2 size={14} /> Thông tin cơ sở kinh doanh
          </h4>
          <div className="profile-detail-grid">
            <div className="profile-detail-field">
              <span>Tên cơ sở</span>
              <strong>{profile.business_name}</strong>
            </div>
            <div className="profile-detail-field">
              <span>Hotline kinh doanh</span>
              <strong>{profile.business_phone}</strong>
            </div>
            <div className="profile-detail-field" style={{ gridColumn: 'span 2' }}>
              <span>Địa chỉ kinh doanh sân</span>
              <strong>{profile.business_address}</strong>
            </div>
            <div className="profile-detail-field">
              <span>Mã Giấy phép KD (GPKD)</span>
              <strong>{profile.business_license || 'Chưa cung cấp'}</strong>
            </div>
          </div>
        </div>

        {/* 3. Ngân hàng & Đối soát */}
        <div className="profile-section">
          <h4>
            <CreditCard size={14} /> Tài khoản ngân hàng & Đối soát doanh thu
          </h4>
          <div className="profile-detail-grid">
            <div className="profile-detail-field">
              <span>Ngân hàng thụ hưởng</span>
              <strong>{profile.bank_name || 'Chưa thiết lập'}</strong>
            </div>
            <div className="profile-detail-field">
              <span>Số tài khoản</span>
              <strong>{profile.bank_account_number || 'Chưa thiết lập'}</strong>
            </div>
            <div className="profile-detail-field" style={{ gridColumn: 'span 2' }}>
              <span>Tên chủ tài khoản</span>
              <strong>{profile.bank_account_holder || 'Chưa thiết lập'}</strong>
            </div>
          </div>
        </div>

        {/* 4. Tài liệu & Giấy tờ đính kèm */}
        <div className="profile-section">
          <h4>
            <FileText size={14} /> Giấy tờ & Tài liệu đính kèm ({profile.documents?.length ?? 0})
          </h4>
          {profile.documents && profile.documents.length > 0 ? (
            <div className="doc-cards-grid">
              {profile.documents.map((doc) => {
                const label =
                  ({
                    id_front: 'CCCD mặt trước',
                    id_back: 'CCCD mặt sau',
                    business_license: 'Giấy phép kinh doanh (GPKD)',
                    land_right: 'Quyền sử dụng sân / đất',
                  } as Record<string, string>)[doc.type] ?? doc.type
                return (
                  <div key={doc.id} className="doc-card">
                    <span className="badge badge--soft">{label}</span>
                    <small>{doc.original_name}</small>
                    <a
                      href={doc.url}
                      target="_blank"
                      rel="noreferrer"
                      className="button button--ghost button--small"
                      style={{ textDecoration: 'none', justifyContent: 'center' }}
                    >
                      <ExternalLink size={13} /> Mở xem tài liệu
                    </a>
                  </div>
                )
              })}
            </div>
          ) : (
            <p className="muted-text">Chưa có tài liệu/ảnh giấy tờ đính kèm.</p>
          )}
        </div>

        {/* 5. Điều khoản & Lịch sử duyệt */}
        <div className="profile-section">
          <h4>
            <ShieldCheck size={14} /> Điều khoản & Pháp lý
          </h4>
          <div className="profile-detail-grid">
            <div className="profile-detail-field">
              <span>Phiên bản điều khoản</span>
              <strong>{profile.terms_version ? `v${profile.terms_version}` : 'Chưa ghi nhận'}</strong>
            </div>
            <div className="profile-detail-field">
              <span>Thời gian chấp thuận</span>
              <strong>
                {profile.terms_accepted_at
                  ? new Date(profile.terms_accepted_at).toLocaleString('vi-VN')
                  : '—'}
              </strong>
            </div>
            <div className="profile-detail-field">
              <span>Thời gian duyệt</span>
              <strong>
                {profile.verified_at
                  ? new Date(profile.verified_at).toLocaleString('vi-VN')
                  : 'Chưa duyệt'}
              </strong>
            </div>
          </div>
          {profile.rejection_reason && (
            <div className="notice notice--error" style={{ marginTop: 10 }}>
              <strong>Lý do từ chối / thu hồi:</strong> {profile.rejection_reason}
            </div>
          )}
        </div>

        <div className="modal__actions" style={{ marginTop: 12 }}>
          <button type="button" className="button button--ghost" onClick={onClose}>
            Đóng
          </button>
          {profile.verification_status === 'pending' && (
            <>
              <button
                type="button"
                className="button button--danger"
                onClick={() => onReject(profile)}
              >
                Từ chối hồ sơ
              </button>
              <button
                type="button"
                className="button button--brand"
                onClick={() => onApprove(profile)}
              >
                Duyệt hồ sơ
              </button>
            </>
          )}
          {profile.verification_status === 'approved' && (
            <button
              type="button"
              className="button button--danger-ghost"
              onClick={() => onSuspend(profile)}
            >
              <ShieldAlert size={14} /> Thu hồi quyền
            </button>
          )}
          {profile.verification_status === 'suspended' && (
            <button
              type="button"
              className="button button--brand"
              onClick={() => onApprove(profile)}
            >
              Cấp lại quyền
            </button>
          )}
        </div>
      </div>
    </Modal>
  )
}

export function AdminFieldsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [params] = useSearchParams()
  const [status, setStatus] = useState(params.get('status') ?? '')
  const [page, setPage] = useState(1)
  const [approvingField, setApprovingField] = useState<SportsField | null>(null)
  const [rejectingField, setRejectingField] = useState<SportsField | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [cancelFutureBookings, setCancelFutureBookings] = useState(false)
  const [keyword, setKeyword] = useState('')
  const search = useDebouncedValue(keyword.trim())

  const fields = useQuery({
    queryKey: ['admin', 'fields', status, search, page],
    queryFn: async () =>
      (
        await api.get<Paginated<SportsField>>('/admin/fields', {
          params: { page, status: status || undefined, keyword: search || undefined },
        })
      ).data,
  })

  const verify = useMutation({
    mutationFn: async ({
      field,
      status: next,
      rejection_reason,
      cancel_future_bookings,
    }: {
      field: SportsField
      status: string
      rejection_reason?: string
      cancel_future_bookings?: boolean
    }) =>
      api.patch(`/admin/fields/${field.id}/verify`, {
        status: next,
        ...(rejection_reason ? { rejection_reason } : {}),
        ...(cancel_future_bookings !== undefined ? { cancel_future_bookings } : {}),
      }),
    onSuccess: (_, vars) => {
      toast.success(
        vars.status === 'approved'
          ? `Đã duyệt sân "${vars.field.name}".`
          : vars.cancel_future_bookings
          ? `Đã gỡ sân "${vars.field.name}" và huỷ các đơn đặt trong tương lai.`
          : `Đã từ chối / gỡ sân "${vars.field.name}".`,
      )
      setApprovingField(null)
      setRejectingField(null)
      setRejectReason('')
      setCancelFutureBookings(false)
      void client.invalidateQueries({ queryKey: ['admin', 'fields'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Lỗi duyệt sân')
    },
  })

  const handleStatusChange = (newStatus: string) => {
    setStatus(newStatus)
    setPage(1)
  }

  return (
    <>
      <PageTitle
        icon={<Building2 />}
        kicker="Nguồn cung"
        title="Kiểm duyệt sân"
        copy="Đảm bảo mỗi sân xuất hiện đều có thông tin đáng tin cậy."
      />
      <div className="toolbar">
        <select value={status} onChange={(e) => handleStatusChange(e.target.value)}>
          <option value="">Tất cả</option>
          <option value="pending">Chờ duyệt</option>
          <option value="approved">Đã duyệt</option>
          <option value="rejected">Từ chối</option>
        </select>
        <label className="search-control">
          <Search size={16} />
          <input
            value={keyword}
            onChange={(e) => {
              setKeyword(e.target.value)
              setPage(1)
            }}
            placeholder="Tìm theo tên sân, địa chỉ hoặc chủ sân..."
            aria-label="Tìm sân"
          />
        </label>
      </div>
      <DataTable
        heads={['Sân', 'Chủ sân', 'Phân loại', 'Giá/giờ', 'Trạng thái', 'Hành động']}
        rows={
          fields.data?.data.map((field) => [
            <Fragment key="field">
              <strong>{field.name}</strong>
              <small>{field.address}</small>
            </Fragment>,
            field.owner?.name,
            field.field_type?.name,
            `${currency.format(field.price_per_hour)}đ`,
            <StatusBadge key="status" status={field.status} />,
            field.status === 'pending' ? (
              <div key="action" className="row-actions">
                <button
                  className="button button--brand button--small"
                  onClick={() => setApprovingField(field)}
                  disabled={verify.isPending}
                >
                  Duyệt
                </button>
                <button
                  className="button button--danger button--small"
                  onClick={() => setRejectingField(field)}
                  disabled={verify.isPending}
                >
                  Từ chối
                </button>
              </div>
            ) : field.status === 'approved' ? (
              <button
                key="revoke"
                className="button button--ghost button--danger button--small"
                onClick={() => setRejectingField(field)}
                disabled={verify.isPending}
                title="Gỡ sân khỏi trang tìm sân khi có vi phạm"
              >
                Gỡ sân
              </button>
            ) : (
              'Đã xử lý'
            ),
          ]) ?? []
        }
      />
      <Pagination
        page={page}
        lastPage={fields.data?.meta.last_page ?? 1}
        total={fields.data?.meta.total}
        onPageChange={setPage}
      />

      <ConfirmModal
        isOpen={!!approvingField}
        title="Xác nhận duyệt sân thể thao"
        message={`Bạn có chắc muốn phê duyệt sân "${approvingField?.name}" để hiển thị công khai cho khách hàng đặt sân?`}
        confirmText="Duyệt sân"
        cancelText="Đóng"
        danger={false}
        busy={verify.isPending}
        onClose={() => setApprovingField(null)}
        onConfirm={() => {
          if (approvingField) verify.mutate({ field: approvingField, status: 'approved' })
        }}
      />

      <Modal
        isOpen={!!rejectingField}
        onClose={() => {
          setRejectingField(null)
          setCancelFutureBookings(false)
        }}
        title={rejectingField?.status === 'approved' ? 'Gỡ sân khỏi hệ thống' : 'Từ chối sân thể thao'}
        asForm
        onSubmit={(e) => {
          e.preventDefault()
          if (rejectingField) {
            verify.mutate({
              field: rejectingField,
              status: 'rejected',
              rejection_reason: rejectReason || 'Thông tin sân chưa đạt yêu cầu.',
              cancel_future_bookings: rejectingField.status === 'approved' ? cancelFutureBookings : undefined,
            })
          }
        }}
      >
        <p>
          {rejectingField?.status === 'approved' ? 'Bạn có chắc muốn gỡ' : 'Bạn có chắc muốn từ chối kiểm duyệt'} sân{' '}
          <strong>{rejectingField?.name}</strong>?
        </p>
        {rejectingField?.status === 'approved' && (
          <>
            <p className="form-help">
              Sân sẽ biến mất khỏi trang tìm sân và không nhận đặt mới.
            </p>
            <label className="check-line" style={{ display: 'flex', alignItems: 'flex-start', marginTop: 12 }}>
              <input
                type="checkbox"
                checked={cancelFutureBookings}
                onChange={(e) => setCancelFutureBookings(e.target.checked)}
                style={{ marginTop: 3 }}
              />
              <span style={{ fontSize: '0.875rem' }}>
                <strong>Tự động huỷ các đơn đặt trong tương lai</strong> và gửi email thông báo cho khách hàng
                <span style={{ display: 'block', color: 'var(--muted)', fontSize: '0.8rem', marginTop: 2 }}>
                  (Nếu không chọn, các đơn đã đặt vẫn giữ nguyên và chủ sân cần tự liên hệ xử lý với khách).
                </span>
              </span>
            </label>
          </>
        )}
        <label style={{ marginTop: 14 }}>
          <span>Lý do {rejectingField?.status === 'approved' ? 'gỡ sân' : 'từ chối'} (bắt buộc)</span>
          <textarea
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
            placeholder="Ví dụ: Hình ảnh mờ, thông tin địa chỉ chưa chính xác..."
            required
            rows={3}
          />
        </label>
        <div className="modal__actions" style={{ marginTop: 20 }}>
          <button
            type="button"
            className="button button--ghost"
            onClick={() => {
              setRejectingField(null)
              setCancelFutureBookings(false)
            }}
          >
            Đóng
          </button>
          <button type="submit" disabled={verify.isPending} className="button button--danger">
            {verify.isPending ? 'Đang xử lý...' : rejectingField?.status === 'approved' ? 'Xác nhận gỡ sân' : 'Xác nhận từ chối'}
          </button>
        </div>
      </Modal>
    </>
  )
}

export function AdminBookingsPage() {
  const [status, setStatus] = useState('')
  const [date, setDate] = useState('')
  const [keyword, setKeyword] = useState('')
  const search = useDebouncedValue(keyword.trim())
  const [page, setPage] = useState(1)
  const bookings = useQuery({
    queryKey: ['admin', 'bookings', status, date, search, page],
    queryFn: async () =>
      (
        await api.get<Paginated<Booking>>('/admin/bookings', {
          params: { page, status: status || undefined, date: date || undefined, keyword: search || undefined },
        })
      ).data,
  })

  const handleStatusChange = (newStatus: string) => {
    setStatus(newStatus)
    setPage(1)
  }

  return (
    <>
      <PageTitle
        icon={<CalendarDays />}
        kicker="Giao dịch"
        title="Toàn bộ lượt đặt"
        copy="Theo dõi lịch chơi và giá trị giao dịch trong hệ thống."
      />
      <div className="toolbar">
        <select value={status} onChange={(e) => handleStatusChange(e.target.value)}>
          <option value="">Mọi trạng thái</option>
          <option value="pending">Chờ xử lý</option>
          <option value="confirmed">Đã xác nhận</option>
          <option value="completed">Hoàn thành</option>
          <option value="cancelled">Đã hủy</option>
          <option value="rejected">Từ chối</option>
        </select>
        <input
          type="date"
          value={date}
          aria-label="Lọc theo ngày chơi"
          onChange={(e) => {
            setDate(e.target.value)
            setPage(1)
          }}
        />
        <label className="search-control">
          <Search size={16} />
          <input
            value={keyword}
            onChange={(e) => {
              setKeyword(e.target.value)
              setPage(1)
            }}
            placeholder="Tìm theo tên/SĐT/email khách hoặc tên sân..."
            aria-label="Tìm đơn đặt"
          />
        </label>
      </div>
      <DataTable
        heads={['Mã & sân', 'Khách hàng', 'Lịch chơi', 'Giá trị', 'Trạng thái']}
        rows={
          bookings.data?.data.map((booking) => [
            <Fragment key="booking">
              <small>#BK-{booking.id}</small>
              <strong>{booking.field?.name}</strong>
            </Fragment>,
            <Fragment key="customer">
              {booking.customer?.name ?? '—'}
              {booking.customer?.phone && <small>{booking.customer.phone}</small>}
            </Fragment>,
            <Fragment key="schedule">
              {new Date(booking.booking_date).toLocaleDateString('vi-VN')}
              <small>
                {booking.time_slot?.start_time} – {booking.time_slot?.end_time}
              </small>
            </Fragment>,
            `${currency.format(booking.total_price)}đ`,
            <StatusBadge key="status" status={booking.status} />,
          ]) ?? []
        }
      />
      <Pagination
        page={page}
        lastPage={bookings.data?.meta.last_page ?? 1}
        total={bookings.data?.meta.total}
        onPageChange={setPage}
      />
    </>
  )
}

export function AdminReviewsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [togglingReview, setTogglingReview] = useState<Review | null>(null)

  const reviews = useQuery({
    queryKey: ['admin', 'reviews', page],
    queryFn: async () =>
      (await api.get<Paginated<Review>>(`/admin/reviews?page=${page}`)).data,
  })

  const toggle = useMutation({
    mutationFn: async (review: Review) => api.patch(`/admin/reviews/${review.id}/toggle`),
    onSuccess: (_, review) => {
      toast.success(
        review.is_visible
          ? 'Đã ẩn đánh giá khỏi giao diện người dùng.'
          : 'Đã hiển thị lại đánh giá.',
      )
      setTogglingReview(null)
      void client.invalidateQueries({ queryKey: ['admin', 'reviews'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Lỗi đổi trạng thái đánh giá')
    },
  })

  return (
    <>
      <PageTitle
        icon={<MessageSquareText />}
        kicker="Nội dung"
        title="Đánh giá cộng đồng"
        copy="Lắng nghe phản hồi và kiểm soát nội dung hiển thị."
      />
      <div className="review-grid">
        {reviews.data?.data.map((review) => (
          <article className="review-card" key={review.id}>
            <div>
              <div className="avatar">{review.user?.name.slice(0, 1)}</div>
              <span>
                <strong>{review.user?.name}</strong>
                <small>{review.field?.name}</small>
              </span>
              <StatusBadge status={review.is_visible ? 'approved' : 'inactive'} />
            </div>
            <b>{'★'.repeat(review.rating)}</b>
            <p>“{review.comment}”</p>
            <button
              className={`button button--small ${review.is_visible ? 'button--danger' : 'button--brand'}`}
              onClick={() => setTogglingReview(review)}
              disabled={toggle.isPending}
            >
              {review.is_visible ? 'Ẩn đánh giá' : 'Hiển thị lại'}
            </button>
          </article>
        ))}
      </div>
      {!reviews.isPending && !reviews.data?.data.length && (
        <Empty text="Chưa có đánh giá trong hệ thống." />
      )}
      <Pagination
        page={page}
        lastPage={reviews.data?.meta.last_page ?? 1}
        total={reviews.data?.meta.total}
        onPageChange={setPage}
      />

      <ConfirmModal
        isOpen={!!togglingReview}
        title={togglingReview?.is_visible ? 'Ẩn đánh giá' : 'Hiển thị đánh giá'}
        message={
          togglingReview?.is_visible
            ? 'Bạn có chắc muốn ẩn đánh giá này khỏi trang chi tiết sân?'
            : 'Bạn có muốn hiển thị công khai lại đánh giá này?'
        }
        confirmText={togglingReview?.is_visible ? 'Ẩn đánh giá' : 'Hiển thị'}
        cancelText="Đóng"
        danger={!!togglingReview?.is_visible}
        busy={toggle.isPending}
        onClose={() => setTogglingReview(null)}
        onConfirm={() => {
          if (togglingReview) toggle.mutate(togglingReview)
        }}
      />
    </>
  )
}

export function AdminCategoriesPage() {
  const client = useQueryClient()
  const toast = useToast()
  const categories = useQuery({
    queryKey: ['admin', 'categories'],
    queryFn: async () => (await api.get<{ data: Category[] }>('/admin/categories')).data.data,
  })

  const createCategory = useMutation({
    mutationFn: async (payload: Record<string, string>) => api.post('/admin/categories', payload),
    onSuccess: () => {
      toast.success('Đã tạo danh mục thể thao mới.')
      void client.invalidateQueries({ queryKey: ['admin', 'categories'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể tạo danh mục')
    },
  })

  const createType = useMutation({
    mutationFn: async (payload: Record<string, string>) =>
      api.post('/admin/field-types', payload),
    onSuccess: () => {
      toast.success('Đã tạo loại sân mới.')
      void client.invalidateQueries({ queryKey: ['admin', 'categories'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể tạo loại sân')
    },
  })

  const submit = (
    event: FormEvent<HTMLFormElement>,
    mutation: typeof createCategory | typeof createType,
  ) => {
    event.preventDefault()
    mutation.mutate(
      Object.fromEntries(new FormData(event.currentTarget).entries()) as Record<string, string>,
    )
    event.currentTarget.reset()
  }

  return (
    <>
      <PageTitle
        icon={<FolderTree />}
        kicker="Cấu trúc dữ liệu"
        title="Danh mục & loại sân"
        copy="Tổ chức các môn chơi theo cách khách hàng dễ tìm nhất."
      />
      <div className="category-admin">
        <div>
          <form className="panel-form" onSubmit={(e) => submit(e, createCategory)}>
            <h2>Thêm môn thể thao</h2>
            <label>
              <span>Tên danh mục</span>
              <input name="name" required placeholder="Bóng đá, Cầu lông..." />
            </label>
            <label>
              <span>Biểu tượng</span>
              <input name="icon" placeholder="⚽, 🏸, 🎾..." />
            </label>
            <label>
              <span>Mô tả</span>
              <textarea name="description" rows={2} />
            </label>
            {createCategory.isError && (
              <div className="notice notice--error">{errorMessage(createCategory.error)}</div>
            )}
            <button disabled={createCategory.isPending} className="button button--dark">
              {createCategory.isPending ? 'Đang tạo...' : 'Tạo danh mục'}
            </button>
          </form>

          <form className="panel-form" onSubmit={(e) => submit(e, createType)}>
            <h2>Thêm loại sân</h2>
            <label>
              <span>Danh mục</span>
              <select name="sport_category_id" required>
                <option value="">Chọn danh mục</option>
                {categories.data?.map((cat) => (
                  <option value={cat.id} key={cat.id}>
                    {cat.icon} {cat.name}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span>Tên loại sân</span>
              <input name="name" required placeholder="Sân 5 người, Sân đất nện..." />
            </label>
            {createType.isError && (
              <div className="notice notice--error">{errorMessage(createType.error)}</div>
            )}
            <button disabled={createType.isPending} className="button button--brand">
              {createType.isPending ? 'Đang tạo...' : 'Tạo loại sân'}
            </button>
          </form>
        </div>

        <section className="data-panel">
          <div className="data-panel__head">
            <div>
              <span>Cấu trúc hiện tại</span>
              <h2>{categories.data?.length ?? 0} bộ môn</h2>
            </div>
          </div>
          <div className="category-tree">
            {categories.data?.map((category) => (
              <article key={category.id}>
                <b>{category.icon}</b>
                <div>
                  <strong>{category.name}</strong>
                  <p>{category.description}</p>
                  <small>{category.field_types_count} loại sân</small>
                </div>
              </article>
            ))}
          </div>
        </section>
      </div>
    </>
  )
}

function Stat({ label, value, note, to }: { label: string; value: string | number; note?: string; to?: string }) {
  return (
    <article className="stat">
      <span>{label}</span>
      <strong>
        <AnimatedCounter value={value} />
      </strong>
      {note && (to ? <Link className="stat__link" to={to}>{note} →</Link> : <small>{note}</small>)}
    </article>
  )
}

function DataTable({ heads, rows }: { heads: string[]; rows: React.ReactNode[][] }) {
  return (
    <section className="data-panel">
      <div className="table-wrap">
        <table>
          <thead>
            <tr>
              {heads.map((head, index) => (
                <th key={index}>{head}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((cells, row) => (
              <tr key={row}>
                {cells.map((cell, column) => (
                  <td key={column}>{cell}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {rows.length === 0 && <Empty text="Không có dữ liệu phù hợp." />}
    </section>
  )
}

export function AdminMatchesPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [status, setStatus] = useState('')
  const [keyword, setKeyword] = useState('')
  const search = useDebouncedValue(keyword.trim())
  const [page, setPage] = useState(1)
  const [cancelling, setCancelling] = useState<MatchRequest | null>(null)

  const matches = useQuery({
    queryKey: ['admin', 'matches', status, search, page],
    queryFn: async () =>
      (
        await api.get<Paginated<MatchRequest>>('/admin/matches', {
          params: { page, status: status || undefined, keyword: search || undefined },
        })
      ).data,
  })

  const cancel = useMutation({
    mutationFn: async (match: MatchRequest) => api.delete(`/admin/matches/${match.id}`),
    onSuccess: () => {
      toast.success('Đã huỷ phòng tìm đối. Người đã được chấp nhận sẽ nhận email thông báo.')
      setCancelling(null)
      void client.invalidateQueries({ queryKey: ['admin', 'matches'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể huỷ phòng')
    },
  })

  const statusLabel: Record<string, string> = {
    open: 'Đang mở',
    full: 'Đã đủ người',
    completed: 'Đã kết thúc',
    cancelled: 'Đã hủy',
  }

  return (
    <>
      <PageTitle
        icon={<Trophy />}
        kicker="Cộng đồng"
        title="Phòng tìm đối"
        copy="Theo dõi các phòng ghép đội và gỡ những phòng vi phạm."
      />
      <div className="toolbar">
        <select
          value={status}
          aria-label="Lọc theo trạng thái phòng"
          onChange={(e) => {
            setStatus(e.target.value)
            setPage(1)
          }}
        >
          <option value="">Mọi trạng thái</option>
          <option value="open">Đang mở</option>
          <option value="full">Đã đủ người</option>
          <option value="completed">Đã kết thúc</option>
          <option value="cancelled">Đã hủy</option>
        </select>
        <label className="search-control">
          <Search size={16} />
          <input
            value={keyword}
            onChange={(e) => {
              setKeyword(e.target.value)
              setPage(1)
            }}
            placeholder="Tìm theo tiêu đề hoặc tên chủ phòng..."
            aria-label="Tìm phòng tìm đối"
          />
        </label>
      </div>
      <QueryState
        isPending={matches.isPending}
        isError={matches.isError}
        isEmpty={!matches.data?.data.length}
        error={errorMessage(matches.error)}
        empty={<Empty text="Không có phòng tìm đối nào phù hợp." />}
      >
        <DataTable
          heads={['Phòng', 'Chủ phòng', 'Lịch chơi', 'Người chơi', 'Trạng thái', 'Hành động']}
          rows={
            matches.data?.data.map((match) => [
              <Fragment key="title">
                <strong>{match.title}</strong>
                <small>
                  {match.sport_category?.name}
                  {match.sports_field ? ` · ${match.sports_field.name}` : ''}
                </small>
              </Fragment>,
              <Fragment key="host">
                {match.host?.name ?? '—'}
                {match.contact_phone && <small>{match.contact_phone}</small>}
              </Fragment>,
              <Fragment key="when">
                {new Date(match.play_date).toLocaleDateString('vi-VN')}
                <small>
                  {match.start_time} – {match.end_time}
                </small>
              </Fragment>,
              `${match.joined_count} / ${match.players_needed + 1}`,
              statusLabel[match.status] ?? match.status,
              match.status === 'open' || match.status === 'full' ? (
                <button
                  key="cancel"
                  className="button button--ghost button--danger button--small"
                  onClick={() => setCancelling(match)}
                >
                  Gỡ phòng
                </button>
              ) : (
                '—'
              ),
            ]) ?? []
          }
        />
      </QueryState>
      <Pagination
        page={page}
        lastPage={matches.data?.meta.last_page ?? 1}
        total={matches.data?.meta.total}
        onPageChange={setPage}
      />
      <ConfirmModal
        isOpen={!!cancelling}
        title="Gỡ phòng tìm đối"
        message={`Huỷ phòng "${cancelling?.title}" của ${cancelling?.host?.name ?? 'chủ phòng'}? Người đã được chấp nhận sẽ nhận email.`}
        confirmText="Gỡ phòng"
        cancelText="Giữ lại"
        danger
        busy={cancel.isPending}
        onClose={() => setCancelling(null)}
        onConfirm={() => {
          if (cancelling) cancel.mutate(cancelling)
        }}
      />
    </>
  )
}
