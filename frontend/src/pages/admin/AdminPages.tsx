import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Ban,
  Building2,
  CalendarDays,
  CreditCard,
  Download,
  Edit3,
  ExternalLink,
  Eye,
  FileText,
  FolderTree,
  Phone,
  RotateCcw,
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
import { useSearchParams } from 'react-router'
import { ConfirmModal } from '../../components/ConfirmModal'
import { Modal } from '../../components/Modal'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { Pagination } from '../../components/Pagination'
import { PaymentProofModal } from '../../components/PaymentProofModal'
import { QueryState } from '../../components/QueryState'
import { StatusBadge } from '../../components/StatusBadge'
import { useToast } from '../../components/Toast'
import type {
  AdminUserDetail,
  Booking,
  Category,
  MatchRequest,
  OwnerProfile,
  Paginated,
  Review,
  SportsField,
  TrustScoreLog,
  User,
} from '../../types/api'
import { Empty, PageTitle } from '../customer/CustomerPages'
import { Avatar } from '../../components/Avatar'
import { formatDate, formatDateTime, formatMoney } from '../../utils/format'

export function AdminUsersPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [keyword, setKeyword] = useState('')
  const [debouncedKeyword, setDebouncedKeyword] = useState('')
  const [role, setRole] = useState('')
  const [page, setPage] = useState(1)
  const [togglingUser, setTogglingUser] = useState<User | null>(null)
  const [toggleReason, setToggleReason] = useState('')
  const [viewingTrustUser, setViewingTrustUser] = useState<User | null>(null)
  const [viewingDetailUser, setViewingDetailUser] = useState<User | null>(null)

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

  const trustLogs = useQuery({
    queryKey: ['admin', 'trust-score-logs', viewingTrustUser?.id],
    queryFn: async () =>
      (
        await api.get<Paginated<TrustScoreLog>>(
          `/admin/users/${viewingTrustUser?.id}/trust-score-logs`,
        )
      ).data,
    enabled: !!viewingTrustUser,
  })

  const userDetail = useQuery<{ data: AdminUserDetail }>({
    queryKey: ['admin', 'users', 'detail', viewingDetailUser?.id],
    queryFn: async () =>
      (await api.get<{ data: AdminUserDetail }>(`/admin/users/${viewingDetailUser?.id}/detail`)).data,
    enabled: !!viewingDetailUser,
  })

  const toggle = useMutation({
    mutationFn: async ({ user, reason }: { user: User; reason?: string }) =>
      api.patch(`/admin/users/${user.id}/toggle`, { reason: reason || undefined }),
    onSuccess: (_, { user }) => {
      toast.success(
        user.is_active
          ? `Đã khóa tài khoản ${user.name}.`
          : `Đã mở khóa tài khoản ${user.name}.`,
      )
      setTogglingUser(null)
      setToggleReason('')
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

  const handleOpenToggle = (user: User) => {
    setTogglingUser(user)
    setToggleReason('')
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
          heads={['Người dùng', 'Điểm uy tín', 'Liên hệ', 'Vai trò', 'Trạng thái', 'Thao tác']}
          rows={
            users.data?.data.map((user) => {
              const score = user.trust_score ?? 100
              const badgeClass =
                score >= 100 ? 'badge--success' : score >= 60 ? 'badge--warning' : 'badge--danger'
              return [
                <strong key="user">{user.name}</strong>,
                <button
                  key="trust"
                  type="button"
                  className={`badge ${badgeClass}`}
                  style={{ cursor: 'pointer', border: 'none', font: 'inherit', fontWeight: 600 }}
                  onClick={() => setViewingTrustUser(user)}
                  title="Bấm để xem lịch sử biến động điểm uy tín"
                >
                  {score} điểm
                </button>,
                <Fragment key="contact">
                  <span>{user.email}</span>
                  <small>{user.phone ?? '—'}</small>
                </Fragment>,
                user.is_field_owner ? 'Chủ sân' : 'Khách hàng',
                <StatusBadge key="status" status={user.is_active ? 'approved' : 'inactive'} />,
                <div key="action" style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                  <button
                    type="button"
                    className="button button--small button--ghost"
                    onClick={() => setViewingDetailUser(user)}
                    title="Xem chi tiết hồ sơ & lịch sử đơn"
                  >
                    <Eye size={14} /> Chi tiết
                  </button>
                  <button
                    type="button"
                    className={`button button--small ${user.is_active ? 'button--danger' : 'button--brand'}`}
                    onClick={() => handleOpenToggle(user)}
                    disabled={toggle.isPending}
                  >
                    {user.is_active ? 'Khóa' : 'Mở khóa'}
                  </button>
                </div>,
              ]
            }) ?? []
          }
        />
      </QueryState>
      <Pagination
        page={page}
        lastPage={users.data?.meta.last_page ?? 1}
        total={users.data?.meta.total}
        onPageChange={setPage}
      />

      {/* Modal Khóa / Mở khóa tài khoản có lý do */}
      <Modal
        isOpen={!!togglingUser}
        onClose={() => setTogglingUser(null)}
        title={togglingUser?.is_active ? 'Xác nhận khóa tài khoản' : 'Xác nhận mở khóa tài khoản'}
      >
        {togglingUser && (
          <form
            onSubmit={(e) => {
              e.preventDefault()
              toggle.mutate({ user: togglingUser, reason: toggleReason })
            }}
            style={{ maxWidth: 460 }}
          >
            <p style={{ margin: '0 0 14px', fontSize: 14, color: 'var(--ink)' }}>
              {togglingUser.is_active
                ? `Bạn có chắc muốn khóa tài khoản "${togglingUser.name}" (${togglingUser.email})? Người dùng này sẽ bị thu hồi phiên đăng nhập ngay lập tức và nhận email thông báo lý do.`
                : `Bạn có muốn mở lại quyền truy cập cho tài khoản "${togglingUser.name}"?`}
            </p>

            {togglingUser.is_active && (
              <label style={{ display: 'block', marginBottom: 16 }}>
                <span style={{ fontSize: 13, fontWeight: 600, display: 'block', marginBottom: 4 }}>
                  Lý do khóa tài khoản (bắt buộc gửi qua email):
                </span>
                <textarea
                  required
                  rows={3}
                  value={toggleReason}
                  onChange={(e) => setToggleReason(e.target.value)}
                  placeholder="Ví dụ: Vi phạm chính sách huỷ đơn nhiều lần, có dấu hiệu gian lận..."
                  style={{ width: '100%', boxSizing: 'border-box' }}
                />
              </label>
            )}

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
              <button
                type="button"
                className="button button--ghost"
                onClick={() => setTogglingUser(null)}
              >
                Hủy
              </button>
              <button
                type="submit"
                className={`button ${togglingUser.is_active ? 'button--danger' : 'button--brand'}`}
                disabled={toggle.isPending}
              >
                {toggle.isPending ? 'Đang xử lý...' : togglingUser.is_active ? 'Xác nhận khóa' : 'Mở khóa'}
              </button>
            </div>
          </form>
        )}
      </Modal>


      {/* Modal Chi tiết người dùng */}
      <Modal
        isOpen={!!viewingDetailUser}
        onClose={() => setViewingDetailUser(null)}
        title={`Hồ sơ người dùng: ${viewingDetailUser?.name}`}
      >
        {viewingDetailUser && (
          <div style={{ maxWidth: 640 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 14, marginBottom: 18 }}>
              <Avatar name={viewingDetailUser.name} className="avatar avatar--lg" />
              <div>
                <h3 style={{ margin: 0, fontSize: 18, color: 'var(--forest)' }}>{viewingDetailUser.name}</h3>
                <div style={{ fontSize: 13, color: 'var(--muted)', marginTop: 2 }}>
                  {viewingDetailUser.email} · {viewingDetailUser.phone ?? 'Chưa cập nhật SĐT'}
                </div>
                <div style={{ display: 'flex', gap: 6, marginTop: 6 }}>
                  <span className="badge badge--brand">{viewingDetailUser.is_field_owner ? 'Chủ sân' : 'Khách hàng'}</span>
                  <StatusBadge status={viewingDetailUser.is_active ? 'approved' : 'inactive'} />
                  <span className="badge badge--success">{viewingDetailUser.trust_score ?? 100} điểm uy tín</span>
                </div>
              </div>
            </div>

            {userDetail.isLoading ? (
              <div className="query-state query-state--loading">Đang tải thông tin chi tiết...</div>
            ) : userDetail.isError || !userDetail.data ? (
              <div className="query-state query-state--error">Không thể tải chi tiết người dùng.</div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
                {/* Stats */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 10 }}>
                  <div style={{ padding: 10, background: 'var(--surface)', borderRadius: 8, textAlign: 'center' }}>
                    <small style={{ color: 'var(--muted)', fontSize: 11, display: 'block' }}>Tổng đơn đặt</small>
                    <strong style={{ fontSize: 16 }}>{userDetail.data.data.stats.total_bookings}</strong>
                  </div>
                  <div style={{ padding: 10, background: 'var(--surface)', borderRadius: 8, textAlign: 'center' }}>
                    <small style={{ color: 'var(--muted)', fontSize: 11, display: 'block' }}>Đã huỷ</small>
                    <strong style={{ fontSize: 16, color: '#ef4444' }}>{userDetail.data.data.stats.cancelled_bookings}</strong>
                  </div>
                  <div style={{ padding: 10, background: 'var(--surface)', borderRadius: 8, textAlign: 'center' }}>
                    <small style={{ color: 'var(--muted)', fontSize: 11, display: 'block' }}>Vắng mặt (No-show)</small>
                    <strong style={{ fontSize: 16, color: 'var(--warn)' }}>{userDetail.data.data.stats.no_show_bookings}</strong>
                  </div>
                  <div style={{ padding: 10, background: 'var(--surface)', borderRadius: 8, textAlign: 'center' }}>
                    <small style={{ color: 'var(--muted)', fontSize: 11, display: 'block' }}>Khiếu nại</small>
                    <strong style={{ fontSize: 16 }}>{userDetail.data.data.stats.complaints_count}</strong>
                  </div>
                </div>

                {/* Recent bookings */}
                <div>
                  <h4 style={{ margin: '0 0 8px', fontSize: 14, color: 'var(--forest)' }}>Đơn đặt gần đây</h4>
                  {userDetail.data.data.recent_bookings.length === 0 ? (
                    <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>Chưa có đơn đặt nào.</p>
                  ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, maxHeight: 180, overflowY: 'auto' }}>
                      {userDetail.data.data.recent_bookings.map((b) => (
                        <div key={b.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '6px 10px', background: 'var(--surface)', borderRadius: 6, fontSize: 12 }}>
                          <div>
                            <strong>#BK-{b.id}</strong> · {b.field?.name ?? 'Sân'} · {formatDate(b.booking_date)}
                          </div>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span>{formatMoney(b.total_price)}</span>
                            <StatusBadge status={b.status} />
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {/* Trust score history */}
                <div>
                  <h4 style={{ margin: '0 0 8px', fontSize: 14, color: 'var(--forest)' }}>Lịch sử biến động điểm uy tín gần đây</h4>
                  {userDetail.data.data.recent_trust_scores.length === 0 ? (
                    <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>Chưa có biến động điểm uy tín.</p>
                  ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, maxHeight: 180, overflowY: 'auto' }}>
                      {userDetail.data.data.recent_trust_scores.map((log) => (
                        <div key={log.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '6px 10px', background: 'var(--surface)', borderRadius: 6, fontSize: 12 }}>
                          <div>
                            <span style={{ color: 'var(--muted)', marginRight: 6 }}>{formatDateTime(log.created_at)}:</span>
                            <span>{log.reason}</span>
                          </div>
                          <strong style={{ color: log.change >= 0 ? 'var(--pitch)' : '#ef4444', whiteSpace: 'nowrap' }}>
                            {log.change >= 0 ? `+${log.change}` : log.change} ({log.score_after}đ)
                          </strong>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}

            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 18 }}>
              <button type="button" className="button button--ghost" onClick={() => setViewingDetailUser(null)}>
                Đóng
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* Modal Lịch sử điểm uy tín */}
      <Modal
        isOpen={!!viewingTrustUser}
        onClose={() => setViewingTrustUser(null)}
        title={`Lịch sử điểm uy tín: ${viewingTrustUser?.name}`}
      >
        <div
          style={{
            marginBottom: 16,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: '12px 16px',
            background: 'var(--surface-muted, #f8f9fa)',
            borderRadius: 8,
          }}
        >
          <div>
            <span style={{ fontSize: '0.85rem', color: 'var(--muted)' }}>Điểm hiện tại</span>
            <div style={{ fontSize: '1.25rem', fontWeight: 700 }}>
              {viewingTrustUser?.trust_score ?? 100} / 150 điểm
            </div>
          </div>
          <span
            className={`badge ${(viewingTrustUser?.trust_score ?? 100) >= 100 ? 'badge--success' : (viewingTrustUser?.trust_score ?? 100) >= 60 ? 'badge--warning' : 'badge--danger'}`}
          >
            {(viewingTrustUser?.trust_score ?? 100) >= 120
              ? 'Rất uy tín'
              : (viewingTrustUser?.trust_score ?? 100) >= 90
              ? 'Bình thường'
              : (viewingTrustUser?.trust_score ?? 100) >= 60
              ? 'Cảnh báo'
              : 'Nguy hiểm'}
          </span>
        </div>

        <QueryState
          isPending={trustLogs.isPending}
          isError={trustLogs.isError}
          isEmpty={!trustLogs.data?.data?.length}
          error={errorMessage(trustLogs.error)}
          empty={
            <p className="empty-inline" style={{ padding: '24px 0', textAlign: 'center' }}>
              Chưa có lịch sử biến động điểm nào.
            </p>
          }
        >
          <div style={{ maxHeight: '360px', overflowY: 'auto' }}>
            <DataTable
              heads={['Thời gian', 'Biến động', 'Sau đổi', 'Lý do & Sân']}
              rows={
                trustLogs.data?.data?.map((log) => [
                  <small key="time" style={{ whiteSpace: 'nowrap' }}>
                    {formatDateTime(log.created_at)}
                  </small>,
                  <strong
                    key="change"
                    style={{
                      color:
                        log.change > 0 ? 'var(--color-success, #10b981)' : 'var(--color-danger, #ef4444)',
                    }}
                  >
                    {log.change > 0 ? `+${log.change}` : log.change}
                  </strong>,
                  <span key="after">{log.score_after}</span>,
                  <Fragment key="reason">
                    <div>{log.reason}</div>
                    {log.sports_field_name && (
                      <small style={{ color: 'var(--muted)' }}>Sân: {log.sports_field_name}</small>
                    )}
                  </Fragment>,
                ]) ?? []
              }
            />
          </div>
        </QueryState>

        <div className="modal__actions" style={{ marginTop: 20 }}>
          <button
            type="button"
            className="button button--ghost"
            onClick={() => setViewingTrustUser(null)}
          >
            Đóng
          </button>
        </div>
      </Modal>
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
                  ? formatDateTime(profile.terms_accepted_at)
                  : '—'}
              </strong>
            </div>
            <div className="profile-detail-field">
              <span>Thời gian duyệt</span>
              <strong>
                {profile.verified_at
                  ? formatDateTime(profile.verified_at)
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
          <option value="">Tất cả trạng thái</option>
          <option value="pending">Chờ duyệt</option>
          <option value="approved">Đã duyệt (Hoạt động)</option>
          <option value="inactive">Tạm ngưng / Inactive</option>
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
            formatMoney(field.price_per_hour),
            <StatusBadge key="status" status={!field.is_active ? 'inactive' : field.status} />,
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
  const client = useQueryClient()
  const toast = useToast()
  const [status, setStatus] = useState('')
  const [date, setDate] = useState('')
  const [keyword, setKeyword] = useState('')
  const search = useDebouncedValue(keyword.trim())
  const [page, setPage] = useState(1)

  // Modals state
  const [viewingBooking, setViewingBooking] = useState<Booking | null>(null)
  const [cancellingBooking, setCancellingBooking] = useState<Booking | null>(null)
  const [adminCancelReason, setAdminCancelReason] = useState('')
  const [refundingBooking, setRefundingBooking] = useState<Booking | null>(null)
  const [refundStatusInput, setRefundStatusInput] = useState<'pending' | 'done' | 'waived'>('done')
  const [refundAmountInput, setRefundAmountInput] = useState<number | ''>('')
  const [proofBooking, setProofBooking] = useState<Booking | null>(null)

  const bookings = useQuery({
    queryKey: ['admin', 'bookings', status, date, search, page],
    queryFn: async () =>
      (
        await api.get<Paginated<Booking>>('/admin/bookings', {
          params: { page, status: status || undefined, date: date || undefined, keyword: search || undefined },
        })
      ).data,
  })

  const cancelBookingMutation = useMutation({
    mutationFn: async ({ id, reason }: { id: number; reason: string }) =>
      api.post(`/admin/bookings/${id}/cancel`, { reason }),
    onSuccess: () => {
      toast.success('Admin đã huỷ đơn đặt sân thành công.')
      setCancellingBooking(null)
      setAdminCancelReason('')
      void client.invalidateQueries({ queryKey: ['admin', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['admin', 'dashboard'] })
      void client.invalidateQueries({ queryKey: ['admin', 'logs'] })
    },
    onError: (err) => toast.error(errorMessage(err), 'Không thể huỷ đơn'),
  })

  const updateRefundMutation = useMutation({
    mutationFn: async ({
      id,
      refund_status,
      refund_amount,
    }: {
      id: number
      refund_status: string
      refund_amount?: number
    }) =>
      api.patch(`/admin/bookings/${id}/refund`, {
        refund_status,
        refund_amount: typeof refund_amount === 'number' ? refund_amount : undefined,
      }),
    onSuccess: () => {
      toast.success('Đã cập nhật trạng thái hoàn tiền cọc thành công.')
      setRefundingBooking(null)
      void client.invalidateQueries({ queryKey: ['admin', 'bookings'] })
      void client.invalidateQueries({ queryKey: ['admin', 'dashboard'] })
      void client.invalidateQueries({ queryKey: ['admin', 'logs'] })
    },
    onError: (err) => toast.error(errorMessage(err), 'Không thể cập nhật hoàn tiền'),
  })

  const handleStatusChange = (newStatus: string) => {
    setStatus(newStatus)
    setPage(1)
  }

  const handleOpenCancel = (booking: Booking) => {
    setCancellingBooking(booking)
    setAdminCancelReason('')
  }

  const handleOpenRefund = (booking: Booking) => {
    setRefundingBooking(booking)
    setRefundStatusInput(booking.refund?.status === 'done' ? 'done' : 'done')
    setRefundAmountInput(booking.refund?.amount ?? booking.payment?.deposit_amount ?? 0)
  }

  return (
    <>
      <PageTitle
        icon={<CalendarDays />}
        kicker="Giao dịch"
        title="Toàn bộ lượt đặt"
        copy="Theo dõi lịch chơi và giá trị giao dịch trong hệ thống."
      />
      <div className="toolbar" style={{ flexWrap: 'wrap' }}>
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
        <a
          href={`/api/v1/admin/bookings/export-csv?status=${status}&date=${date}&keyword=${encodeURIComponent(search)}`}
          download
          className="button button--ghost"
          style={{ display: 'inline-flex', alignItems: 'center', gap: 6, textDecoration: 'none', marginLeft: 'auto' }}
          title="Tải về file Excel / CSV toàn bộ đơn theo bộ lọc"
        >
          <Download size={15} /> Xuất CSV
        </a>
      </div>
      <DataTable
        heads={['Mã & sân', 'Khách hàng', 'Lịch chơi', 'Giá trị', 'Trạng thái', 'Thao tác']}
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
              {formatDate(booking.booking_date)}
              <small>
                {booking.time_slot?.start_time} – {booking.time_slot?.end_time}
              </small>
            </Fragment>,
            <Fragment key="price">
              {formatMoney(booking.total_price)}
              {booking.payment?.amount != null && (
                <small>
                  {booking.payment.status === 'paid' ? 'Đã nhận' : 'Khách báo chuyển'} {formatMoney(booking.payment.amount)} ·{' '}
                  {booking.payment.code}
                </small>
              )}
              {booking.refund && (
                <small style={{ color: booking.refund.status === 'pending' ? 'var(--warn)' : 'var(--pitch)', display: 'block' }}>
                  Hoàn: {formatMoney(booking.refund.amount)} ({booking.refund.status === 'pending' ? 'Chờ hoàn' : booking.refund.status === 'done' ? 'Đã hoàn' : 'Miễn'})
                </small>
              )}
            </Fragment>,
            <StatusBadge key="status" status={booking.status} payment={booking.payment?.status} />,
            <div key="action" style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
              <button
                type="button"
                className="button button--small button--ghost"
                onClick={() => setViewingBooking(booking)}
                title="Xem chi tiết đơn & dòng thời gian"
              >
                <Eye size={14} /> Chi tiết
              </button>
              {booking.payment?.proof_url && (
                <button
                  type="button"
                  className="button button--small button--ghost"
                  onClick={() => setProofBooking(booking)}
                  title="Xem ảnh chứng từ chuyển khoản"
                >
                  <FileText size={14} /> Biên lai
                </button>
              )}
              {booking.status !== 'completed' && booking.status !== 'cancelled' && (
                <button
                  type="button"
                  className="button button--small button--danger"
                  onClick={() => handleOpenCancel(booking)}
                  title="Admin can thiệp huỷ đơn"
                >
                  <Ban size={14} /> Huỷ
                </button>
              )}
              {(booking.refund || (booking.status === 'cancelled' && booking.payment?.status === 'paid')) && (
                <button
                  type="button"
                  className="button button--small button--ghost"
                  onClick={() => handleOpenRefund(booking)}
                  title="Cập nhật trạng thái chuyển khoản hoàn tiền"
                >
                  <RotateCcw size={14} /> Hoàn cọc
                </button>
              )}
            </div>,
          ]) ?? []
        }
      />
      <Pagination
        page={page}
        lastPage={bookings.data?.meta.last_page ?? 1}
        total={bookings.data?.meta.total}
        onPageChange={setPage}
      />

      {/* Modal Chi tiết đơn & Timeline */}
      <Modal
        isOpen={!!viewingBooking}
        onClose={() => setViewingBooking(null)}
        title={`Chi tiết đơn đặt sân #BK-${viewingBooking?.id}`}
      >
        {viewingBooking && (
          <div style={{ maxWidth: 620, display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 16px', background: 'var(--surface)', borderRadius: 8 }}>
              <div>
                <strong style={{ fontSize: 16, color: 'var(--forest)' }}>#BK-{viewingBooking.id}</strong>
                <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 2 }}>Tạo lúc: {formatDateTime(viewingBooking.created_at)}</div>
              </div>
              <StatusBadge status={viewingBooking.status} payment={viewingBooking.payment?.status} />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, fontSize: 13 }}>
              <div style={{ padding: 12, background: 'var(--surface)', borderRadius: 8 }}>
                <span style={{ fontSize: 11, color: 'var(--muted)', fontWeight: 600, display: 'block', marginBottom: 4 }}>KHÁCH HÀNG</span>
                <strong>{viewingBooking.customer?.name ?? '—'}</strong>
                <div>{viewingBooking.customer?.email}</div>
                <div>{viewingBooking.customer?.phone ?? 'Chưa có SĐT'}</div>
              </div>

              <div style={{ padding: 12, background: 'var(--surface)', borderRadius: 8 }}>
                <span style={{ fontSize: 11, color: 'var(--muted)', fontWeight: 600, display: 'block', marginBottom: 4 }}>SÂN THỂ THAO</span>
                <strong>{viewingBooking.field?.name ?? 'Sân'}</strong>
                <div>{viewingBooking.field?.address}</div>
                <div style={{ color: 'var(--pitch)', marginTop: 2, fontWeight: 600 }}>
                  {formatDate(viewingBooking.booking_date)} · {viewingBooking.time_slot?.start_time} – {viewingBooking.time_slot?.end_time}
                </div>
              </div>
            </div>

            {/* Tài chính & Thanh toán */}
            <div style={{ padding: 14, background: 'var(--surface)', borderRadius: 8, fontSize: 13 }}>
              <span style={{ fontSize: 11, color: 'var(--muted)', fontWeight: 600, display: 'block', marginBottom: 8 }}>CHI TIẾT THANH TOÁN & HOA HỒNG</span>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
                <span>Tổng giá trị đơn:</span>
                <strong>{formatMoney(viewingBooking.total_price)}</strong>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
                <span>Khách đã thanh toán:</span>
                <strong style={{ color: viewingBooking.payment?.status === 'paid' ? 'var(--pitch)' : 'var(--warn)' }}>
                  {formatMoney(viewingBooking.payment?.amount ?? 0)} ({viewingBooking.payment?.status === 'paid' ? 'Đã nhận' : viewingBooking.payment?.status === 'reported' ? 'Báo chuyển' : 'Chờ'})
                </strong>
              </div>
              {viewingBooking.payment?.code && (
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
                  <span>Mã giao dịch / Nội dung:</span>
                  <code>{viewingBooking.payment.code}</code>
                </div>
              )}
              {viewingBooking.commission && (
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6, borderTop: '1px dashed var(--line)', paddingTop: 6 }}>
                  <span>Hoa hồng nền tảng ({viewingBooking.commission.rate}%):</span>
                  <strong style={{ color: 'var(--forest)' }}>{formatMoney(viewingBooking.commission.amount)}</strong>
                </div>
              )}
              {viewingBooking.refund && (
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 6, padding: '8px 10px', background: '#fef3c7', borderRadius: 6, color: '#92400e' }}>
                  <span>Tiền cọc cần hoàn trả:</span>
                  <strong>{formatMoney(viewingBooking.refund.amount)} ({viewingBooking.refund.status === 'done' ? 'Đã chuyển hoàn' : viewingBooking.refund.status === 'waived' ? 'Miễn hoàn' : 'Chờ chuyển'})</strong>
                </div>
              )}
              {viewingBooking.payment?.proof_url && (
                <div style={{ marginTop: 10, display: 'flex', justifyContent: 'flex-start' }}>
                  <button
                    type="button"
                    className="button button--ghost button--small"
                    onClick={() => setProofBooking(viewingBooking)}
                  >
                    <Eye size={13} /> Xem biên lai chuyển khoản
                  </button>
                </div>
              )}
            </div>

            {/* Dòng thời gian mốc sự kiện */}
            <div style={{ padding: 14, background: 'var(--surface)', borderRadius: 8, fontSize: 13 }}>
              <span style={{ fontSize: 11, color: 'var(--muted)', fontWeight: 600, display: 'block', marginBottom: 8 }}>DÒNG THỜI GIAN ĐƠN</span>
              <ul style={{ margin: 0, paddingLeft: 18, display: 'flex', flexDirection: 'column', gap: 6, color: 'var(--soft)' }}>
                <li>Tạo đơn: {formatDateTime(viewingBooking.created_at)}</li>
                {viewingBooking.confirmed_at && <li>Chủ sân xác nhận: {formatDateTime(viewingBooking.confirmed_at)}</li>}
                {viewingBooking.payment?.paid_at && <li>Nhận tiền cọc: {formatDateTime(viewingBooking.payment.paid_at)}</li>}
                {viewingBooking.checked_in_at && <li>Khách check-in: {formatDateTime(viewingBooking.checked_in_at)}</li>}
                {viewingBooking.cancelled_at && (
                  <li style={{ color: '#ef4444' }}>
                    Huỷ đơn: {formatDateTime(viewingBooking.cancelled_at)} · Lý do: {viewingBooking.cancel_reason ?? '—'}
                  </li>
                )}
                {viewingBooking.refund?.refunded_at && (
                  <li style={{ color: 'var(--pitch)' }}>
                    Hoàn cọc thành công: {formatDateTime(viewingBooking.refund.refunded_at)}
                  </li>
                )}
              </ul>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 6 }}>
              <button type="button" className="button button--ghost" onClick={() => setViewingBooking(null)}>
                Đóng
              </button>
            </div>
          </div>
        )}
      </Modal>

      <PaymentProofModal
        isOpen={!!proofBooking}
        onClose={() => setProofBooking(null)}
        proofUrl={proofBooking?.payment?.proof_url}
        title={`Biên lai đơn #BK-${proofBooking?.id ?? ''}`}
      />

      {/* Modal Admin huỷ đơn */}
      <Modal
        isOpen={!!cancellingBooking}
        onClose={() => setCancellingBooking(null)}
        title={`Admin can thiệp huỷ đơn #BK-${cancellingBooking?.id}`}
      >
        {cancellingBooking && (
          <form
            onSubmit={(e) => {
              e.preventDefault()
              cancelBookingMutation.mutate({
                id: cancellingBooking.id,
                reason: adminCancelReason,
              })
            }}
            style={{ maxWidth: 480 }}
          >
            <div style={{ padding: 12, background: 'var(--surface)', borderRadius: 8, marginBottom: 14, fontSize: 13 }}>
              <div>Sân: <strong>{cancellingBooking.field?.name}</strong></div>
              <div>Chủ sân: <strong>{cancellingBooking.field?.owner?.name ?? '—'}</strong> {cancellingBooking.field?.owner?.phone && <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>(<Phone size={12} style={{ color: 'var(--pitch)' }} /> <a href={`tel:${cancellingBooking.field?.owner?.phone}`} style={{ color: 'var(--pitch)', fontWeight: 600 }}>{cancellingBooking.field?.owner?.phone}</a>)</span>}</div>
              <div>Khách: <strong>{cancellingBooking.customer?.name}</strong> ({cancellingBooking.customer?.phone || cancellingBooking.customer?.email})</div>
              <div>Ngày: <strong>{formatDate(cancellingBooking.booking_date)}</strong> ({cancellingBooking.time_slot?.start_time} – {cancellingBooking.time_slot?.end_time})</div>
              <div>Đã thanh toán: <strong>{formatMoney(cancellingBooking.payment?.amount ?? 0)}</strong></div>
            </div>

            <div className="form-stack">
              <label>
                <span>Lý do huỷ đơn (bắt buộc, lưu vào nhật ký và gửi cho các bên)</span>
                <textarea
                  required
                  rows={3}
                  value={adminCancelReason}
                  onChange={(e) => setAdminCancelReason(e.target.value)}
                  placeholder="Ví dụ: Theo yêu cầu can thiệp tranh chấp, sự cố sân đột xuất..."
                />
              </label>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 12 }}>
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setCancellingBooking(null)}
                >
                  Hủy bỏ
                </button>
                <button
                  type="submit"
                  className="button button--danger"
                  disabled={cancelBookingMutation.isPending || adminCancelReason.trim().length < 3}
                >
                  {cancelBookingMutation.isPending ? 'Đang huỷ...' : 'Xác nhận huỷ đơn'}
                </button>
              </div>
            </div>
          </form>
        )}
      </Modal>

      {/* Modal Cập nhật hoàn tiền cọc */}
      <Modal
        isOpen={!!refundingBooking}
        onClose={() => setRefundingBooking(null)}
        title={`Cập nhật hoàn tiền đơn #BK-${refundingBooking?.id}`}
      >
        {refundingBooking && (
          <form
            onSubmit={(e) => {
              e.preventDefault()
              updateRefundMutation.mutate({
                id: refundingBooking.id,
                refund_status: refundStatusInput,
                refund_amount: refundAmountInput !== '' ? Number(refundAmountInput) : undefined,
              })
            }}
            style={{ maxWidth: 460 }}
          >
            <div style={{ padding: 12, background: 'var(--surface)', borderRadius: 8, marginBottom: 14, fontSize: 13 }}>
              <div>Khách hàng: <strong>{refundingBooking.customer?.name}</strong></div>
              <div>Tổng tiền đơn: <strong>{formatMoney(refundingBooking.total_price)}</strong></div>
              <div>Đã trả cọc: <strong>{formatMoney(refundingBooking.payment?.amount ?? 0)}</strong></div>
            </div>

            <div className="form-stack">
              <label>
                <span>Trạng thái hoàn tiền</span>
                <select
                  value={refundStatusInput}
                  onChange={(e) => setRefundStatusInput(e.target.value as any)}
                >
                  <option value="done">Đã hoàn tiền (đã chuyển khoản trả khách)</option>
                  <option value="pending">Chờ hoàn tiền (chưa chuyển khoản)</option>
                  <option value="waived">Miễn hoàn tiền (khách tự ý bỏ/vi phạm)</option>
                </select>
              </label>

              <label>
                <span>Số tiền hoàn (VNĐ)</span>
                <input
                  type="number"
                  min="0"
                  step="1000"
                  value={refundAmountInput}
                  onChange={(e) => setRefundAmountInput(e.target.value === '' ? '' : Number(e.target.value))}
                />
              </label>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 12 }}>
                <button
                  type="button"
                  className="button button--ghost"
                  onClick={() => setRefundingBooking(null)}
                >
                  Đóng
                </button>
                <button
                  type="submit"
                  className="button button--brand"
                  disabled={updateRefundMutation.isPending}
                >
                  {updateRefundMutation.isPending ? 'Đang lưu...' : 'Lưu kết quả hoàn tiền'}
                </button>
              </div>
            </div>
          </form>
        )}
      </Modal>
    </>
  )
}

export function AdminReviewsPage() {
  const client = useQueryClient()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [rating, setRating] = useState('')
  const [togglingReview, setTogglingReview] = useState<Review | null>(null)

  const reviews = useQuery({
    queryKey: ['admin', 'reviews', page, rating],
    queryFn: async () =>
      (await api.get<Paginated<Review>>(`/admin/reviews?page=${page}${rating ? `&rating=${rating}` : ''}`)).data,
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
      <div className="toolbar" style={{ marginBottom: 20 }}>
        <select
          value={rating}
          onChange={(e) => {
            setRating(e.target.value)
            setPage(1)
          }}
          aria-label="Lọc đánh giá theo số sao"
        >
          <option value="">Tất cả đánh giá (1 – 5 sao)</option>
          <option value="5">5 sao (★★★★★)</option>
          <option value="4">4 sao (★★★★☆)</option>
          <option value="3">3 sao (★★★☆☆)</option>
          <option value="2">2 sao (★★☆☆☆)</option>
          <option value="1">1 sao (★☆☆☆☆)</option>
        </select>
      </div>
      <div className="review-grid">
        {reviews.data?.data.map((review) => (
          <article className="review-card" key={review.id}>
            <div>
              <Avatar name={review.user?.name} src={review.user?.avatar} />
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
  const [editingCategory, setEditingCategory] = useState<Category | null>(null)
  const [editName, setEditName] = useState('')
  const [editIcon, setEditIcon] = useState('')
  const [editDescription, setEditDescription] = useState('')
  const [editIsActive, setEditIsActive] = useState(true)

  const categories = useQuery({
    queryKey: ['admin', 'categories'],
    queryFn: async () => (await api.get<{ data: Category[] }>('/admin/categories')).data.data,
  })

  const openEditModal = (cat: Category) => {
    setEditingCategory(cat)
    setEditName(cat.name)
    setEditIcon(cat.icon ?? '')
    setEditDescription(cat.description ?? '')
    setEditIsActive(cat.is_active)
  }

  const updateCategory = useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: Record<string, unknown> }) =>
      api.put(`/admin/categories/${id}`, payload),
    onSuccess: () => {
      toast.success('Đã cập nhật danh mục thể thao.')
      setEditingCategory(null)
      void client.invalidateQueries({ queryKey: ['admin', 'categories'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể cập nhật danh mục')
    },
  })

  const toggleCategoryActive = (cat: Category) => {
    updateCategory.mutate({
      id: cat.id,
      payload: { is_active: !cat.is_active },
    })
  }

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
    const form = event.currentTarget
    mutation.mutate(
      Object.fromEntries(new FormData(form).entries()) as Record<string, string>,
      // Chỉ xoá form khi lưu thành công, tránh mất dữ liệu đã nhập nếu API báo lỗi.
      { onSuccess: () => form.reset() },
    )
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
              <article key={category.id} style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between', opacity: category.is_active ? 1 : 0.65 }}>
                <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
                  <b style={{ fontSize: 24 }}>{category.icon}</b>
                  <div style={{ flex: 1 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <strong>{category.name}</strong>
                      <span className={`status-pill ${category.is_active ? 'status-pill--confirmed' : 'status-pill--cancelled'}`} style={{ fontSize: 11, padding: '2px 8px' }}>
                        {category.is_active ? 'Đang bật' : 'Tạm tắt'}
                      </span>
                    </div>
                    <p style={{ margin: '4px 0', fontSize: 13, color: 'var(--text-muted)' }}>{category.description || 'Chưa có mô tả'}</p>
                    <small style={{ color: 'var(--text-muted)' }}>{category.field_types_count} loại sân</small>
                  </div>
                </div>
                <div style={{ display: 'flex', gap: 6, marginTop: 12, borderTop: '1px solid var(--line, #e2e8f0)', paddingTop: 10, justifyContent: 'flex-end' }}>
                  <button
                    type="button"
                    className="button button--small button--ghost"
                    onClick={() => openEditModal(category)}
                  >
                    <Edit3 size={13} /> Sửa
                  </button>
                  <button
                    type="button"
                    className={`button button--small ${category.is_active ? 'button--secondary' : 'button--brand'}`}
                    onClick={() => toggleCategoryActive(category)}
                    disabled={updateCategory.isPending}
                  >
                    {category.is_active ? 'Tắt' : 'Bật'}
                  </button>
                </div>
              </article>
            ))}
          </div>
        </section>
      </div>

      <Modal
        isOpen={Boolean(editingCategory)}
        onClose={() => setEditingCategory(null)}
        title={`Chỉnh sửa danh mục: ${editingCategory?.name}`}
      >
        {editingCategory && (
          <form
            onSubmit={(e) => {
              e.preventDefault()
              updateCategory.mutate({
                id: editingCategory.id,
                payload: {
                  name: editName,
                  icon: editIcon,
                  description: editDescription,
                  is_active: editIsActive,
                },
              })
            }}
            className="form-stack"
            style={{ maxWidth: 440 }}
          >
            <label>
              <span>Tên danh mục</span>
              <input
                value={editName}
                onChange={(e) => setEditName(e.target.value)}
                required
                placeholder="Bóng đá, Cầu lông..."
              />
            </label>
            <label>
              <span>Biểu tượng (Icon / Emoji)</span>
              <input
                value={editIcon}
                onChange={(e) => setEditIcon(e.target.value)}
                placeholder="⚽, 🏸, 🎾..."
              />
            </label>
            <label>
              <span>Mô tả</span>
              <textarea
                value={editDescription}
                onChange={(e) => setEditDescription(e.target.value)}
                rows={3}
                placeholder="Mô tả danh mục bộ môn..."
              />
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', marginTop: 4 }}>
              <input
                type="checkbox"
                checked={editIsActive}
                onChange={(e) => setEditIsActive(e.target.checked)}
              />
              <span style={{ fontWeight: 600 }}>Cho phép hiển thị và đặt sân môn này</span>
            </label>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 16 }}>
              <button
                type="button"
                className="button button--ghost"
                onClick={() => setEditingCategory(null)}
              >
                Hủy bỏ
              </button>
              <button
                type="submit"
                className="button button--brand"
                disabled={updateCategory.isPending || !editName.trim()}
              >
                {updateCategory.isPending ? 'Đang lưu...' : 'Lưu thay đổi'}
              </button>
            </div>
          </form>
        )}
      </Modal>
    </>
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
  const [cancelReason, setCancelReason] = useState('')

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
    mutationFn: async ({ match, reason }: { match: MatchRequest; reason?: string }) =>
      api.delete(`/admin/matches/${match.id}`, { data: { reason } }),
    onSuccess: () => {
      toast.success('Đã huỷ phòng tìm đối. Người đã được chấp nhận và chủ phòng sẽ nhận email thông báo.')
      setCancelling(null)
      setCancelReason('')
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
                {formatDate(match.play_date)}
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
      <Modal
        isOpen={!!cancelling}
        onClose={() => {
          setCancelling(null)
          setCancelReason('')
        }}
        title="Gỡ phòng tìm đối vi phạm"
        asForm
        onSubmit={(e) => {
          e.preventDefault()
          if (cancelling) {
            cancel.mutate({
              match: cancelling,
              reason: cancelReason.trim() || undefined,
            })
          }
        }}
      >
        <p>
          Bạn có chắc muốn gỡ phòng <strong>{cancelling?.title}</strong> của{' '}
          {cancelling?.host?.name ?? 'chủ phòng'}?
        </p>
        <p className="form-help">
          Chủ phòng và các thành viên đã được chấp nhận sẽ nhận email thông báo kèm lý do huỷ này.
        </p>
        <label style={{ marginTop: 12 }}>
          <span>Lý do gỡ phòng (sẽ gửi vào email người tham gia)</span>
          <textarea
            value={cancelReason}
            onChange={(e) => setCancelReason(e.target.value)}
            placeholder="Ví dụ: Phòng vi phạm tiêu chuẩn cộng đồng, ngôn từ không phù hợp..."
            rows={3}
          />
        </label>
        <div className="modal__actions" style={{ marginTop: 20 }}>
          <button
            type="button"
            className="button button--ghost"
            onClick={() => {
              setCancelling(null)
              setCancelReason('')
            }}
          >
            Đóng
          </button>
          <button type="submit" disabled={cancel.isPending} className="button button--danger">
            {cancel.isPending ? 'Đang xử lý...' : 'Xác nhận gỡ phòng'}
          </button>
        </div>
      </Modal>
    </>
  )
}
