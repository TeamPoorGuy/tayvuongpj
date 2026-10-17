import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  CalendarDays,
  CheckCircle2,
  Clock3,
  Filter,
  MapPin,
  Phone,
  Plus,
  Search,
  ShieldCheck,
  Trophy,
  UserCheck,
  UserPlus,
  Users,
  XCircle,
} from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import { Modal } from '../components/Modal'
import { Pagination } from '../components/Pagination'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/auth-context'
import type { ApiEnvelope, Category, MatchParticipant, MatchRequest, Paginated, SportsField, TimeSlot } from '../types/api'
import { formatDay, getLocalTodayString } from '../utils/date'
import { formatMoney } from '../utils/format'


const matchStatusLabels: Record<string, string> = {
  open: 'Đang mở',
  full: 'Đã đủ',
  completed: 'Đã kết thúc',
  cancelled: 'Đã hủy',
}

const skillLevelLabels: Record<string, string> = {
  beginner: 'Mới chơi / Giao lưu vui',
  intermediate: 'Trung bình / Vừa sức',
  advanced: 'Khá / Bán chuyên',
  any: 'Mọi trình độ',
}

export function MatchesPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const client = useQueryClient()
  const toast = useToast()

  // Filter states
  const [categoryId, setCategoryId] = useState<string>('')
  const [skillLevel, setSkillLevel] = useState<string>('')
  const [matchStatus, setMatchStatus] = useState<string>('')
  const [date, setDate] = useState<string>('')
  const [mine, setMine] = useState(false)
  const [page, setPage] = useState<number>(1)
  const todayStr = getLocalTodayString()

  // Modals
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [createKey, setCreateKey] = useState(0) // đổi key để form tạo phòng về trạng thái trống sau khi tạo xong
  const [joiningMatch, setJoiningMatch] = useState<MatchRequest | null>(null)
  const [joinMessage, setJoinMessage] = useState('')
  const [managingMatch, setManagingMatch] = useState<MatchRequest | null>(null)
  const [cancellingMatch, setCancellingMatch] = useState<MatchRequest | null>(null)
  const [matchCancelReason, setMatchCancelReason] = useState('')

  // Categories & Fields queries
  const categoriesQuery = useQuery({
    queryKey: ['categories'],
    queryFn: async () => (await api.get<{ data: Category[] }>('/categories')).data.data,
  })

  // Matches query
  const matchesQuery = useQuery({
    queryKey: ['matches', categoryId, skillLevel, matchStatus, date, mine && user?.id, page],
    queryFn: async () => {
      const params = new URLSearchParams()
      params.set('page', String(page))
      if (categoryId) params.set('category_id', categoryId)
      if (skillLevel) params.set('skill_level', skillLevel)
      if (matchStatus) params.set('status', matchStatus)
      if (date) params.set('date', date)
      if (mine && user) params.set('mine', '1')
      return (await api.get<Paginated<MatchRequest>>(`/matches?${params.toString()}`)).data
    },
    // Chủ phòng thấy yêu cầu tham gia mới và người xin thấy kết quả mà không cần tải lại.
    refetchInterval: 30_000,
  })

  // Mutations
  const createMatch = useMutation({
    mutationFn: async (payload: Record<string, unknown>) =>
      (await api.post<{ message: string; data: MatchRequest }>('/customer/matches', payload)).data,
    onSuccess: (res) => {
      toast.success(res.message || 'Tạo phòng tìm đối thành công!')
      setShowCreateModal(false)
      setCreateKey((key) => key + 1)
      void client.invalidateQueries({ queryKey: ['matches'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể tạo phòng')
    },
  })

  const joinMatch = useMutation({
    mutationFn: async ({ matchId, message }: { matchId: number; message?: string }) =>
      (await api.post(`/customer/matches/${matchId}/join`, { message })).data,
    onSuccess: () => {
      toast.success('Đã gửi yêu cầu tham gia đến chủ phòng!')
      setJoiningMatch(null)
      setJoinMessage('')
      void client.invalidateQueries({ queryKey: ['matches'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể gửi yêu cầu')
    },
  })

  const updateParticipant = useMutation({
    mutationFn: async ({
      matchId,
      participantId,
      status,
    }: {
      matchId: number
      participantId: number
      status: 'accepted' | 'rejected'
    }) =>
      (
        await api.patch(`/customer/matches/${matchId}/participants/${participantId}`, { status })
      ).data,
    onSuccess: () => {
      toast.success('Đã cập nhật trạng thái người tham gia.')
      void client.invalidateQueries({ queryKey: ['matches'] })
      // Keep managing modal updated by refreshing current match
      if (managingMatch) {
        void api.get<{ data: MatchRequest }>(`/matches/${managingMatch.id}`).then((res) => {
          setManagingMatch(res.data.data)
        })
      }
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Cập nhật thất bại')
    },
  })

  const cancelMatch = useMutation({
    mutationFn: async ({ matchId, reason }: { matchId: number; reason?: string }) =>
      (await api.delete(`/customer/matches/${matchId}`, { data: { reason } })).data,
    onSuccess: () => {
      toast.success('Đã huỷ bài tìm đối và thông báo cho người tham gia.')
      setManagingMatch(null)
      setCancellingMatch(null)
      setMatchCancelReason('')
      void client.invalidateQueries({ queryKey: ['matches'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể huỷ bài')
    },
  })

  const leaveMatch = useMutation({
    mutationFn: async (matchId: number) =>
      (await api.delete(`/customer/matches/${matchId}/participation`)).data,
    onSuccess: () => {
      toast.success('Đã rút khỏi trận đấu.')
      void client.invalidateQueries({ queryKey: ['matches'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể rút lui')
    },
  })

  const handleJoinClick = (match: MatchRequest) => {
    if (!user) {
      toast.error('Vui lòng đăng nhập để tham gia tìm đối/ghép đội.')
      navigate('/login', { state: { from: '/matches' } })
      return
    }
    setJoiningMatch(match)
  }

  const handleOpenCreateModal = () => {
    if (!user) {
      toast.error('Vui lòng đăng nhập để tạo phòng tìm đối.')
      navigate('/login', { state: { from: '/matches' } })
      return
    }
    setShowCreateModal(true)
  }

  const matches = matchesQuery.data?.data ?? []

  return (
    <div className="matches-page">
      <div className="shell">
        {/* Hero Section */}
        <section className="matches-hero">
          <div className="matches-hero__content">
            <span className="badge badge--brand">
              <Trophy size={14} /> SportHub Community Matchday
            </span>
            <h1>Ghép Đội & Tìm Đối Thể Thao</h1>
            <p>
              Tìm thêm bạn chơi bóng đá, cầu lông, tennis, bóng rổ hoặc tổ chức kèo giao hữu cuối tuần
              dễ dàng và kết nối cộng đồng thể thao uy tín.
            </p>
          </div>
          <div className="matches-hero__action">
            <button
              type="button"
              className="button button--brand button--large"
              onClick={handleOpenCreateModal}
            >
              <Plus size={18} /> Tạo phòng tìm đối
            </button>
          </div>
        </section>

        {/* Filter Toolbar */}
        <div className="matches-filter-bar">
          <div className="filter-group">
            <Filter size={16} />
            <select
              value={categoryId}
              onChange={(e) => {
                setCategoryId(e.target.value)
                setPage(1)
              }}
            >
              <option value="">Tất cả môn thể thao</option>
              {categoriesQuery.data?.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {cat.name}
                </option>
              ))}
            </select>
          </div>

          <div className="filter-group">
            <select
              value={skillLevel}
              onChange={(e) => {
                setSkillLevel(e.target.value)
                setPage(1)
              }}
            >
              <option value="">Mọi trình độ</option>
              <option value="beginner">Mới chơi / Vui vẻ</option>
              <option value="intermediate">Trung bình / Vừa sức</option>
              <option value="advanced">Khá / Bán chuyên</option>
              <option value="any">Tự do</option>
            </select>
          </div>

          <div className="filter-group">
            <select
              value={matchStatus}
              onChange={(e) => {
                setMatchStatus(e.target.value)
                setPage(1)
              }}
            >
              <option value="">Trạng thái: Tất cả</option>
              <option value="open">Đang tìm người</option>
              <option value="full">Đã đủ người</option>
              {mine && <option value="completed">Đã kết thúc</option>}
              {mine && <option value="cancelled">Đã hủy</option>}
            </select>
          </div>

          <div className="filter-group">
            <input
              type="date"
              value={date}
              min={mine ? undefined : todayStr}
              aria-label="Ngày thi đấu"
              onChange={(e) => {
                setDate(e.target.value)
                setPage(1)
              }}
            />
          </div>

          {user && (
            <button
              type="button"
              className={`button button--small ${mine ? 'button--dark' : 'button--ghost'}`}
              aria-pressed={mine}
              title="Phòng bạn tạo hoặc đã xin tham gia, kể cả đã kết thúc"
              onClick={() => {
                setMine(!mine)
                setPage(1)
              }}
            >
              <UserCheck size={14} /> Kèo của tôi
            </button>
          )}

          {(categoryId || skillLevel || matchStatus || date || mine) && (
            <button
              type="button"
              className="button button--ghost button--small"
              onClick={() => {
                setCategoryId('')
                setSkillLevel('')
                setMatchStatus('')
                setDate('')
                setMine(false)
                setPage(1)
              }}
            >
              Đặt lại lọc
            </button>
          )}
        </div>

        {/* Matches Grid */}
        <div className="matches-grid">
          {matches.map((match) => {
            const isHost = user?.id === match.user_id
            const myParticipant = match.participants?.find((p) => p.user_id === user?.id)
            const neededTotal = match.players_needed
            const currentTotal = match.joined_count - 1 // minus host
            const slotsRemaining = Math.max(0, neededTotal - currentTotal)

            return (
              <article key={match.id} className="match-card">
                <div className="match-card__header">
                  <div className="match-card__tags">
                    <span className="match-sport-badge">
                      {match.sport_category?.name ?? 'Thể thao'}
                    </span>
                    <span className="match-skill-badge">
                      {skillLevelLabels[match.skill_level] ?? match.skill_level}
                    </span>
                  </div>
                  <div>
                    {match.status === 'open' ? (
                      <span className="badge badge--success">Đang mở</span>
                    ) : match.status === 'full' ? (
                      <span className="badge badge--warning">Đã đủ</span>
                    ) : match.status === 'completed' ? (
                      <span className="badge badge--soft">Đã kết thúc</span>
                    ) : (
                      <span className="badge badge--soft">Đã hủy</span>
                    )}
                  </div>
                </div>

                <h3 className="match-card__title">{match.title}</h3>

                <div className="match-card__meta">
                  <span>
                    <CalendarDays size={15} />
                    {formatDay(match.play_date)}
                  </span>
                  <span>
                    <Clock3 size={15} />
                    {match.start_time} – {match.end_time}
                  </span>
                  {match.sports_field && (
                    <span>
                      <MapPin size={15} />
                      {match.sports_field.name}
                      {match.sports_field.address ? ` · ${match.sports_field.address}` : ''}
                    </span>
                  )}
                </div>

                {match.description && (
                  <p className="match-card__desc">{match.description}</p>
                )}

                {match.sports_field?.slug && match.status !== 'cancelled' && match.status !== 'completed' && (
                  <Link
                    className="button button--ghost button--small"
                    to={`/fields/${match.sports_field.slug}?date=${match.play_date}&start=${match.start_time}`}
                    title={`Mở sân với ngày ${formatDay(match.play_date)}, khung ${match.start_time} – ${match.end_time}`}
                  >
                    Đặt sân này
                  </Link>
                )}

                {/* Progress bar for slots */}
                <div className="match-slots-progress">
                  <div className="match-slots-progress__text">
                    <span>
                      <Users size={14} /> Tiến độ ghép đội:
                    </span>
                    <strong>
                      {match.joined_count} / {match.players_needed + 1} người
                    </strong>
                  </div>
                  <div className="match-slots-bar">
                    <div
                      className="match-slots-bar__fill"
                      style={{
                        width: `${Math.min(
                          100,
                          (match.joined_count / (match.players_needed + 1)) * 100,
                        )}%`,
                      }}
                    />
                  </div>
                  <small className="match-slots-rem">
                    {slotsRemaining > 0
                      ? `Cần tìm thêm ${slotsRemaining} người`
                      : 'Đội hình đã chốt đủ'}
                  </small>
                </div>

                {/* Host Info */}
                <div className="match-card__host">
                  <div className="host-avatar">
                    {match.host?.name ? match.host.name.charAt(0).toUpperCase() : 'H'}
                  </div>
                  <div className="host-info">
                    <span className="host-name">{match.host?.name ?? 'Chủ phòng'}</span>
                    <span className="host-trust">
                      <ShieldCheck size={12} /> {match.host?.trust_score ?? 100} điểm uy tín
                    </span>
                  </div>
                  <div className="host-contact">
                    <Phone size={13} /> {match.contact_phone ?? 'SĐT hiện sau khi chủ phòng chấp nhận'}
                  </div>
                </div>

                {/* Card Actions */}
                <div className="match-card__footer">
                  {isHost ? (
                    <button
                      type="button"
                      className="button button--brand button--small"
                      onClick={() => setManagingMatch(match)}
                    >
                      <UserCheck size={15} /> Quản lý phòng ({match.participants?.filter((p) => p.status === 'pending').length ?? 0} chờ duyệt)
                    </button>
                  ) : myParticipant ? (
                    <div className="match-status-pill">
                      {myParticipant.status === 'pending' && (
                        <span className="badge badge--warning">
                          ⏳ Đang chờ chủ phòng duyệt
                        </span>
                      )}
                      {myParticipant.status === 'accepted' && (
                        <span className="badge badge--success">
                          ✓ Đã vào phòng thi đấu
                        </span>
                      )}
                      {myParticipant.status === 'rejected' && (
                        <span className="badge badge--danger">
                          ✕ Đã bị từ chối
                        </span>
                      )}
                      {myParticipant.status !== 'rejected' && (match.status === 'open' || match.status === 'full') && (
                        <button
                          type="button"
                          className="button button--ghost button--small"
                          disabled={leaveMatch.isPending}
                          onClick={() => {
                            if (window.confirm('Bạn muốn rút khỏi trận đấu này?')) leaveMatch.mutate(match.id)
                          }}
                        >
                          Rút lui
                        </button>
                      )}
                    </div>
                  ) : match.status === 'open' && slotsRemaining > 0 ? (
                    <button
                      type="button"
                      className="button button--brand button--small"
                      onClick={() => handleJoinClick(match)}
                    >
                      <UserPlus size={15} /> Xin tham gia
                    </button>
                  ) : (
                    <button type="button" className="button button--ghost button--small" disabled>
                      {match.status === 'open' || match.status === 'full' ? 'Phòng đã đủ thành viên' : 'Phòng đã đóng'}
                    </button>
                  )}
                </div>
              </article>
            )
          })}
        </div>

        {matchesQuery.isPending && (
          <div className="query-state query-state--loading" aria-live="polite">
            Đang tải danh sách kèo...
          </div>
        )}

        {matchesQuery.isError && (
          <div className="notice notice--error" role="alert">
            Không thể tải danh sách trận đấu. Vui lòng thử lại sau.
          </div>
        )}

        {!matchesQuery.isPending && !matchesQuery.isError && matches.length === 0 && (
          <div className="empty-panel">
            <Trophy size={48} />
            <h2>{mine ? 'Bạn chưa tạo hoặc tham gia kèo nào' : 'Chưa có bài tìm đối nào phù hợp'}</h2>
            <p>
              {mine
                ? 'Tạo phòng mới hoặc bỏ lọc "Kèo của tôi" để xem các kèo đang mở.'
                : 'Hãy là người đầu tiên tạo phòng giao lưu để kết nối bạn bè cùng sở thích!'}
            </p>
            <button
              type="button"
              className="button button--brand"
              onClick={handleOpenCreateModal}
              style={{ marginTop: 12 }}
            >
              <Plus size={16} /> Tạo phòng mới ngay
            </button>
          </div>
        )}

        <Pagination
          page={page}
          lastPage={matchesQuery.data?.meta?.last_page ?? 1}
          total={matchesQuery.data?.meta?.total}
          onPageChange={setPage}
        />
      </div>

      <CreateMatchModal
        key={createKey}
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        categories={categoriesQuery.data ?? []}
        defaultPhone={user?.phone ?? ''}
        busy={createMatch.isPending}
        onCreate={(payload) => createMatch.mutate(payload)}
      />

      {/* Modal: Join Match */}
      <Modal
        isOpen={!!joiningMatch}
        onClose={() => setJoiningMatch(null)}
        title={`Tham gia kèo: ${joiningMatch?.title ?? ''}`}
        asForm
        onSubmit={(e) => {
          e.preventDefault()
          if (joiningMatch) {
            joinMatch.mutate({ matchId: joiningMatch.id, message: joinMessage })
          }
        }}
      >
        <p>
          Bạn đang gửi yêu cầu tham gia trận đấu của <strong>{joiningMatch?.host?.name}</strong>{' '}
          ngày{' '}
          {joiningMatch?.play_date
            ? formatDay(joiningMatch.play_date)
            : ''}{' '}
          ({joiningMatch?.start_time} – {joiningMatch?.end_time}).
        </p>

        <label style={{ marginTop: 14 }}>
          <span>Lời nhắn cho chủ phòng (vị trí chơi, kinh nghiệm...)</span>
          <textarea
            value={joinMessage}
            onChange={(e) => setJoinMessage(e.target.value)}
            rows={3}
            placeholder="VD: Mình đá được vị trí tiền vệ hoặc thủ môn, hẹn gặp bạn trên sân nhé!"
          />
        </label>

        <div className="modal__actions" style={{ marginTop: 20 }}>
          <button
            type="button"
            className="button button--ghost"
            onClick={() => setJoiningMatch(null)}
          >
            Đóng
          </button>
          <button type="submit" disabled={joinMatch.isPending} className="button button--brand">
            {joinMatch.isPending ? 'Đang gửi...' : 'Xác nhận tham gia'}
          </button>
        </div>
      </Modal>

      {/* Modal: Host Manage Match */}
      <Modal
        isOpen={!!managingMatch}
        onClose={() => setManagingMatch(null)}
        title={`Quản lý phòng: ${managingMatch?.title ?? ''}`}
      >
        <div className="manage-match-content">
          <div className="manage-match-stats">
            <span>
              Tiến độ: <strong>{managingMatch?.joined_count} / {(managingMatch?.players_needed ?? 0) + 1} người</strong>
            </span>
            <span>Trạng thái: <strong>{matchStatusLabels[managingMatch?.status ?? 'open']}</strong></span>
          </div>

          <h4 style={{ margin: '16px 0 8px' }}>Danh sách thành viên đăng ký</h4>
          <div className="participants-list">
            {managingMatch?.participants?.map((participant: MatchParticipant) => (
              <div key={participant.id} className="participant-item">
                <div className="participant-info">
                  <div className="participant-name">
                    <strong>{participant.user?.name ?? 'Thành viên'}</strong>
                    <span className="badge badge--soft">
                      <ShieldCheck size={12} /> {participant.user?.phone ? `${participant.user.phone} · ` : ''}Điểm: {participant.user?.trust_score ?? 100}
                    </span>
                  </div>
                  {participant.message && (
                    <p className="participant-msg">💬 &quot;{participant.message}&quot;</p>
                  )}
                  <small style={{ color: 'var(--text-muted)' }}>
                    Trạng thái:{' '}
                    {participant.status === 'accepted'
                      ? '✓ Đã chấp nhận'
                      : participant.status === 'rejected'
                        ? '✕ Từ chối'
                        : '⏳ Chờ duyệt'}
                  </small>
                </div>

                <div className="participant-actions">
                  {participant.status === 'pending' && (managingMatch?.status === 'open' || managingMatch?.status === 'full') && (
                    <>
                      <button
                        type="button"
                        className="button button--brand button--small"
                        disabled={updateParticipant.isPending}
                        onClick={() =>
                          managingMatch &&
                          updateParticipant.mutate({
                            matchId: managingMatch.id,
                            participantId: participant.id,
                            status: 'accepted',
                          })
                        }
                      >
                        <CheckCircle2 size={14} /> Chấp nhận
                      </button>
                      <button
                        type="button"
                        className="button button--ghost button--danger button--small"
                        disabled={updateParticipant.isPending}
                        onClick={() =>
                          managingMatch &&
                          updateParticipant.mutate({
                            matchId: managingMatch.id,
                            participantId: participant.id,
                            status: 'rejected',
                          })
                        }
                      >
                        <XCircle size={14} /> Từ chối
                      </button>
                    </>
                  )}
                  {participant.status === 'accepted' && (
                    <span className="badge badge--success">Đã trong đội</span>
                  )}
                  {participant.status === 'rejected' && (
                    <span className="badge badge--danger">Đã từ chối</span>
                  )}
                </div>
              </div>
            ))}

            {!managingMatch?.participants?.length && (
              <p className="empty-inline" style={{ padding: '16px 0', textAlign: 'center' }}>
                Chưa có ai xin tham gia phòng này.
              </p>
            )}
          </div>

          <div
            className="modal__actions"
            style={{ marginTop: 24, justifyContent: 'space-between', borderTop: '1px solid var(--border)', paddingTop: 16 }}
          >
            <button
              type="button"
              className="button button--ghost button--danger"
              style={{ visibility: managingMatch?.status === 'open' || managingMatch?.status === 'full' ? 'visible' : 'hidden' }}
              disabled={cancelMatch.isPending}
              onClick={() => {
                if (managingMatch) {
                  setCancellingMatch(managingMatch)
                }
              }}
            >
              Hủy bài đăng
            </button>
            <button
              type="button"
              className="button button--brand"
              onClick={() => setManagingMatch(null)}
            >
              Xong
            </button>
          </div>
        </div>
      </Modal>

      {/* Modal huỷ bài tìm đối có lý do */}
      <Modal
        isOpen={!!cancellingMatch}
        onClose={() => {
          setCancellingMatch(null)
          setMatchCancelReason('')
        }}
        title="Huỷ bài tìm đối"
        asForm
        onSubmit={(e) => {
          e.preventDefault()
          if (cancellingMatch) {
            cancelMatch.mutate({
              matchId: cancellingMatch.id,
              reason: matchCancelReason.trim() || undefined,
            })
          }
        }}
      >
        <p>
          Bạn có chắc muốn huỷ bài tìm đối <strong>{cancellingMatch?.title}</strong>?
        </p>
        <p className="form-help">
          Những người đã được chấp nhận sẽ nhận email thông báo bài đăng đã bị huỷ kèm lý do.
        </p>
        <label style={{ marginTop: 12 }}>
          <span>Lý do huỷ bài (tuỳ chọn)</span>
          <textarea
            value={matchCancelReason}
            onChange={(e) => setMatchCancelReason(e.target.value)}
            placeholder="Ví dụ: Đội mình bận đột xuất, không đủ người đá..."
            rows={3}
          />
        </label>
        <div className="modal__actions" style={{ marginTop: 20 }}>
          <button
            type="button"
            className="button button--ghost"
            onClick={() => {
              setCancellingMatch(null)
              setMatchCancelReason('')
            }}
          >
            Đóng
          </button>
          <button type="submit" disabled={cancelMatch.isPending} className="button button--danger">
            {cancelMatch.isPending ? 'Đang huỷ...' : 'Xác nhận huỷ bài'}
          </button>
        </div>
      </Modal>
    </div>
  )
}

function CreateMatchModal({
  isOpen,
  onClose,
  categories,
  defaultPhone,
  busy,
  onCreate,
}: {
  isOpen: boolean
  onClose: () => void
  categories: Category[]
  defaultPhone: string
  busy: boolean
  onCreate: (payload: Record<string, unknown>) => void
}) {
  const todayStr = getLocalTodayString()
  const [pickedCategoryId, setPickedCategoryId] = useState<number | null>(null)
  const [field, setField] = useState<SportsField | null>(null)
  const [keyword, setKeyword] = useState('')
  const [debouncedKeyword, setDebouncedKeyword] = useState('')
  const [date, setDate] = useState(todayStr)
  // Có sân: giờ chơi lấy từ khung giờ của sân. Chưa chốt sân: nhập tự do.
  const [slot, setSlot] = useState<TimeSlot | null>(null)
  const [freeStart, setFreeStart] = useState('18:00')
  const [freeEnd, setFreeEnd] = useState('19:30')

  const category = categories.find((c) => c.id === pickedCategoryId) ?? categories[0]

  useEffect(() => {
    const handler = setTimeout(() => setDebouncedKeyword(keyword.trim()), 300)
    return () => clearTimeout(handler)
  }, [keyword])

  // Tìm sân phía server nên vẫn nhanh khi hệ thống có hàng trăm sân.
  const fieldSearch = useQuery({
    queryKey: ['match-field-search', category?.slug, debouncedKeyword],
    enabled: isOpen && !field && !!category,
    queryFn: async () =>
      (
        await api.get<Paginated<SportsField>>('/fields', {
          params: { per_page: 6, category: category?.slug, keyword: debouncedKeyword || undefined },
        })
      ).data,
  })

  const slots = useQuery({
    queryKey: ['slots', field?.id, date],
    enabled: isOpen && !!field && !!date,
    queryFn: async () =>
      (
        await api.get<ApiEnvelope<{ date: string; slots: TimeSlot[] }>>(
          `/fields/${field?.id}/available-slots?date=${date}`,
        )
      ).data.data.slots,
  })

  const startTime = field ? slot?.start_time : freeStart
  const endTime = field ? slot?.end_time : freeEnd
  const timeError = !field && freeEnd <= freeStart ? 'Giờ kết thúc phải sau giờ bắt đầu.' : ''

  const submit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    if (!category || !startTime || !endTime || timeError) return
    const form = new FormData(e.currentTarget)
    onCreate({
      sport_category_id: category.id,
      sports_field_id: field?.id ?? null,
      title: String(form.get('title')),
      play_date: date,
      start_time: startTime,
      end_time: endTime,
      skill_level: String(form.get('skill_level')),
      players_needed: Number(form.get('players_needed')),
      contact_phone: String(form.get('contact_phone')),
      description: String(form.get('description') ?? ''),
    })
  }

  const results = fieldSearch.data?.data ?? []
  const totalFields = fieldSearch.data?.meta.total ?? 0

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Tạo phòng ghép đội / Tìm đối thể thao" wide asForm onSubmit={submit}>
      <div className="form-grid">
        <label>
          <span>Môn thể thao *</span>
          <select
            required
            value={category?.id ?? ''}
            onChange={(e) => {
              setPickedCategoryId(Number(e.target.value))
              // Sân thuộc môn khác thì bỏ chọn để môn và sân luôn khớp nhau.
              setField(null)
              setSlot(null)
            }}
          >
            {categories.map((cat) => (
              <option key={cat.id} value={cat.id}>
                {cat.name}
              </option>
            ))}
          </select>
        </label>

        <label>
          <span>Trình độ mong muốn *</span>
          <select name="skill_level" required defaultValue="intermediate">
            <option value="beginner">Mới chơi / Vui vẻ giao lưu</option>
            <option value="intermediate">Trung bình / Vừa sức</option>
            <option value="advanced">Khá / Bán chuyên</option>
            <option value="any">Tự do / Mọi trình độ</option>
          </select>
        </label>
      </div>

      <label>
        <span>Tiêu đề bài đăng *</span>
        <input name="title" required maxLength={150} placeholder="VD: Kèo 5v5 giao lưu vui vẻ tối thứ Năm" />
      </label>

      <div className="field-picker">
        <span className="field-label">Sân thi đấu (tùy chọn)</span>
        {field ? (
          <div className="field-picker__selected">
            <div>
              <strong>{field.name}</strong>
              <small>
                <MapPin size={12} /> {field.address} · {formatMoney(field.price_per_hour)}/giờ
              </small>
            </div>
            <button
              type="button"
              className="button button--ghost button--small"
              onClick={() => {
                setField(null)
                setSlot(null)
              }}
            >
              Đổi sân
            </button>
          </div>
        ) : (
          <>
            <div className="search-control">
              <Search size={16} />
              <input
                type="search"
                value={keyword}
                onChange={(e) => setKeyword(e.target.value)}
                placeholder={`Tìm sân ${category?.name.toLowerCase() ?? ''} theo tên, quận hoặc địa chỉ...`}
                aria-label="Tìm sân"
              />
            </div>
            <div className="field-picker__results">
              {results.map((item) => (
                <button
                  type="button"
                  key={item.id}
                  className="field-picker__item"
                  onClick={() => {
                    setField(item)
                    setSlot(null)
                  }}
                >
                  <strong>{item.name}</strong>
                  <small>
                    {item.address} · {formatMoney(item.price_per_hour)}/giờ
                  </small>
                </button>
              ))}
              {fieldSearch.isPending && <p className="form-help">Đang tìm sân...</p>}
              {!fieldSearch.isPending && results.length === 0 && (
                <p className="form-help">
                  Không có sân {category?.name.toLowerCase()} nào khớp{debouncedKeyword ? ` "${debouncedKeyword}"` : ''}. Bạn vẫn
                  có thể tạo phòng và ghi địa điểm trong phần mô tả.
                </p>
              )}
              {totalFields > results.length && (
                <p className="form-help">
                  Đang hiện {results.length}/{totalFields} sân — gõ thêm tên hoặc địa chỉ để thu hẹp.
                </p>
              )}
            </div>
          </>
        )}
      </div>

      <div className="form-grid">
        <label>
          <span>Ngày thi đấu *</span>
          <input
            type="date"
            required
            min={todayStr}
            value={date}
            onChange={(e) => {
              setDate(e.target.value)
              setSlot(null)
            }}
          />
        </label>

        <label>
          <span>Số người cần tìm thêm *</span>
          <input name="players_needed" type="number" min={1} max={30} defaultValue={2} required />
        </label>
      </div>

      {field ? (
        <div className="field-picker">
          <span className="field-label">Khung giờ của sân ngày {formatDay(date)} *</span>
          {slots.isPending && <p className="form-help">Đang tải khung giờ...</p>}
          {slots.data?.length === 0 && (
            <p className="form-help">Sân này chưa mở khung giờ nào. Hãy chọn sân khác.</p>
          )}
          <div className="slot-grid slot-grid--wide">
            {slots.data?.map((item) => (
              <button
                type="button"
                key={item.id}
                disabled={item.is_past}
                className={`slot-chip ${slot?.id === item.id ? 'selected' : ''} ${item.is_past ? 'slot-chip--past' : ''}`}
                onClick={() => setSlot(item)}
              >
                <span>
                  {item.start_time} – {item.end_time}
                </span>
                <small>
                  {item.is_past ? 'Đã qua giờ' : item.is_booked ? 'Đã có người đặt' : item.is_held ? 'Đang giữ chỗ' : 'Còn trống'}
                </small>
              </button>
            ))}
          </div>
          {slot?.is_booked && (
            <p className="form-help">
              Khung giờ này đã có người đặt. Chỉ tạo phòng nếu chính bạn là người đã đặt sân.
            </p>
          )}
          {slot && !slot.is_booked && (
            <p className="form-help">
              Tạo phòng chưa giữ sân — nhớ đặt khung {slot.start_time} – {slot.end_time} ở trang sân sau khi đăng.
            </p>
          )}
        </div>
      ) : (
        <>
          <div className="form-grid">
            <label>
              <span>Giờ bắt đầu *</span>
              <input type="time" step={1800} required value={freeStart} onChange={(e) => setFreeStart(e.target.value)} />
            </label>

            <label>
              <span>Giờ kết thúc *</span>
              <input type="time" step={1800} required value={freeEnd} onChange={(e) => setFreeEnd(e.target.value)} />
            </label>
          </div>
          {timeError && <div className="notice notice--error">{timeError}</div>}
        </>
      )}

      <label>
        <span>Số điện thoại liên hệ *</span>
        <input
          name="contact_phone"
          type="tel"
          inputMode="numeric"
          pattern="[0-9]{10,11}"
          title="Số điện thoại gồm 10–11 chữ số"
          required
          defaultValue={defaultPhone}
          placeholder="09xxxxxxxx"
        />
      </label>

      <label>
        <span>Mô tả thêm / Yêu cầu chi tiết</span>
        <textarea
          name="description"
          rows={3}
          maxLength={1000}
          placeholder="VD: Thiếu 2 bạn đá cánh, có áo bib, chia tiền sân đều 50k/người..."
        />
      </label>

      <div className="modal__actions">
        <button type="button" className="button button--ghost" onClick={onClose}>
          Hủy
        </button>
        <button type="submit" disabled={busy || !startTime || !!timeError} className="button button--brand">
          {busy ? 'Đang tạo...' : field && !slot ? 'Chọn khung giờ' : 'Đăng phòng'}
        </button>
      </div>
    </Modal>
  )
}
