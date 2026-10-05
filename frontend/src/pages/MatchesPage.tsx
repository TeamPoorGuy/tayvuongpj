import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  CalendarDays,
  CheckCircle2,
  Clock3,
  Filter,
  MapPin,
  Phone,
  Plus,
  ShieldCheck,
  Trophy,
  UserCheck,
  UserPlus,
  Users,
  XCircle,
} from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'
import { Modal } from '../components/Modal'
import { Pagination } from '../components/Pagination'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/auth-context'
import type { Category, MatchParticipant, MatchRequest, Paginated, SportsField } from '../types/api'

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
  const [page, setPage] = useState<number>(1)

  // Modals
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [joiningMatch, setJoiningMatch] = useState<MatchRequest | null>(null)
  const [joinMessage, setJoinMessage] = useState('')
  const [managingMatch, setManagingMatch] = useState<MatchRequest | null>(null)

  // Categories & Fields queries
  const categoriesQuery = useQuery({
    queryKey: ['categories'],
    queryFn: async () => (await api.get<{ data: Category[] }>('/categories')).data.data,
  })

  const fieldsQuery = useQuery({
    queryKey: ['fields-dropdown'],
    queryFn: async () => (await api.get<Paginated<SportsField>>('/fields?per_page=100')).data.data,
  })

  // Matches query
  const matchesQuery = useQuery({
    queryKey: ['matches', categoryId, skillLevel, matchStatus, date, page],
    queryFn: async () => {
      const params = new URLSearchParams()
      params.set('page', String(page))
      if (categoryId) params.set('category_id', categoryId)
      if (skillLevel) params.set('skill_level', skillLevel)
      if (matchStatus) params.set('status', matchStatus)
      if (date) params.set('date', date)
      return (await api.get<Paginated<MatchRequest>>(`/matches?${params.toString()}`)).data
    },
  })

  // Mutations
  const createMatch = useMutation({
    mutationFn: async (payload: Record<string, unknown>) =>
      (await api.post<{ message: string; data: MatchRequest }>('/customer/matches', payload)).data,
    onSuccess: (res) => {
      toast.success(res.message || 'Tạo phòng tìm đối thành công!')
      setShowCreateModal(false)
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
    mutationFn: async (matchId: number) =>
      (await api.delete(`/customer/matches/${matchId}`)).data,
    onSuccess: () => {
      toast.success('Đã huỷ bài tìm đối.')
      setManagingMatch(null)
      void client.invalidateQueries({ queryKey: ['matches'] })
    },
    onError: (err) => {
      toast.error(errorMessage(err), 'Không thể huỷ bài')
    },
  })

  const handleCreateSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const form = new FormData(e.currentTarget)
    const payload = {
      sport_category_id: Number(form.get('sport_category_id')),
      sports_field_id: form.get('sports_field_id') ? Number(form.get('sports_field_id')) : null,
      title: String(form.get('title')),
      play_date: String(form.get('play_date')),
      start_time: String(form.get('start_time')),
      end_time: String(form.get('end_time')),
      skill_level: String(form.get('skill_level')),
      players_needed: Number(form.get('players_needed')),
      contact_phone: String(form.get('contact_phone')),
      description: String(form.get('description') ?? ''),
    }
    createMatch.mutate(payload)
  }

  const handleJoinClick = (match: MatchRequest) => {
    if (!user) {
      toast.error('Vui lòng đăng nhập để tham gia tìm đối/ghép đội.')
      navigate('/login')
      return
    }
    setJoiningMatch(match)
  }

  const handleOpenCreateModal = () => {
    if (!user) {
      toast.error('Vui lòng đăng nhập để tạo phòng tìm đối.')
      navigate('/login')
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
            </select>
          </div>

          <div className="filter-group">
            <input
              type="date"
              value={date}
              min={new Date().toISOString().slice(0, 10)}
              onChange={(e) => {
                setDate(e.target.value)
                setPage(1)
              }}
            />
          </div>

          {(categoryId || skillLevel || matchStatus || date) && (
            <button
              type="button"
              className="button button--ghost button--small"
              onClick={() => {
                setCategoryId('')
                setSkillLevel('')
                setMatchStatus('')
                setDate('')
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
                      <span className="badge badge--success">🟢 Đang mở</span>
                    ) : match.status === 'full' ? (
                      <span className="badge badge--warning">🔒 Đã đủ</span>
                    ) : match.status === 'completed' ? (
                      <span className="badge badge--soft">Đã đá</span>
                    ) : (
                      <span className="badge badge--soft">Đã hủy</span>
                    )}
                  </div>
                </div>

                <h3 className="match-card__title">{match.title}</h3>

                <div className="match-card__meta">
                  <span>
                    <CalendarDays size={15} />
                    {new Date(match.play_date).toLocaleDateString('vi-VN')}
                  </span>
                  <span>
                    <Clock3 size={15} />
                    {match.start_time} – {match.end_time}
                  </span>
                  {match.sports_field && (
                    <span>
                      <MapPin size={15} />
                      {match.sports_field.name}
                    </span>
                  )}
                </div>

                {match.description && (
                  <p className="match-card__desc">{match.description}</p>
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
                      <ShieldCheck size={12} /> {match.host?.trust_score ?? 100}đ uy tín
                    </span>
                  </div>
                  <div className="host-contact">
                    <Phone size={13} /> {match.contact_phone}
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
                    </div>
                  ) : match.status === 'open' && slotsRemaining > 0 ? (
                    <button
                      type="button"
                      className="button button--brand button--small"
                      onClick={() => handleJoinClick(match)}
                    >
                      <UserPlus size={15} /> Xin tham gia kèo
                    </button>
                  ) : (
                    <button type="button" className="button button--ghost button--small" disabled>
                      Phòng đã đủ thành viên
                    </button>
                  )}
                </div>
              </article>
            )
          })}
        </div>

        {!matchesQuery.isPending && matches.length === 0 && (
          <div className="empty-panel">
            <Trophy size={48} />
            <h2>Chưa có bài tìm đối nào phù hợp</h2>
            <p>Hãy là người đầu tiên tạo phòng giao lưu để kết nối bạn bè cùng sở thích!</p>
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

      {/* Modal: Create Match */}
      <Modal
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
        title="Tạo phòng ghép đội / Tìm đối thể thao"
        asForm
        onSubmit={handleCreateSubmit}
      >
        <div className="form-grid">
          <label>
            <span>Môn thể thao *</span>
            <select name="sport_category_id" required defaultValue={categoriesQuery.data?.[0]?.id}>
              {categoriesQuery.data?.map((cat) => (
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

        <label style={{ marginTop: 10 }}>
          <span>Tiêu đề bài đăng *</span>
          <input
            name="title"
            required
            placeholder="VD: Kèo 5v5 giao lưu vui vẻ tối thứ Năm tại sân Chu Văn An"
          />
        </label>

        <label style={{ marginTop: 10 }}>
          <span>Địa điểm / Sân (Tùy chọn)</span>
          <select name="sports_field_id" defaultValue="">
            <option value="">Chọn sân đã có trên hệ thống hoặc ghi chú sau</option>
            {fieldsQuery.data?.map((f) => (
              <option key={f.id} value={f.id}>
                {f.name} ({f.address})
              </option>
            ))}
          </select>
        </label>

        <div className="form-grid" style={{ marginTop: 10 }}>
          <label>
            <span>Ngày thi đấu *</span>
            <input
              name="play_date"
              type="date"
              required
              min={new Date().toISOString().slice(0, 10)}
            />
          </label>

          <label>
            <span>Số người cần tìm thêm *</span>
            <input
              name="players_needed"
              type="number"
              min={1}
              max={30}
              defaultValue={2}
              required
            />
          </label>
        </div>

        <div className="form-grid" style={{ marginTop: 10 }}>
          <label>
            <span>Giờ bắt đầu *</span>
            <input name="start_time" type="time" required defaultValue="18:00" />
          </label>

          <label>
            <span>Giờ kết thúc *</span>
            <input name="end_time" type="time" required defaultValue="19:30" />
          </label>
        </div>

        <label style={{ marginTop: 10 }}>
          <span>Số điện thoại liên hệ *</span>
          <input
            name="contact_phone"
            required
            defaultValue={user?.phone ?? ''}
            placeholder="09xxxxxxxx"
          />
        </label>

        <label style={{ marginTop: 10 }}>
          <span>Mô tả thêm / Yêu cầu chi tiết</span>
          <textarea
            name="description"
            rows={3}
            placeholder="VD: Thiếu 2 bạn đá cánh, có áo bib, chia tiền sân đều 50k/người..."
          />
        </label>

        <div className="modal__actions" style={{ marginTop: 20 }}>
          <button
            type="button"
            className="button button--ghost"
            onClick={() => setShowCreateModal(false)}
          >
            Hủy
          </button>
          <button type="submit" disabled={createMatch.isPending} className="button button--brand">
            {createMatch.isPending ? 'Đang tạo...' : 'Đăng phòng tìm đối'}
          </button>
        </div>
      </Modal>

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
            ? new Date(joiningMatch.play_date).toLocaleDateString('vi-VN')
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
            <span>Trạng thái: <strong>{managingMatch?.status === 'open' ? 'Đang mở' : 'Đã đủ'}</strong></span>
          </div>

          <h4 style={{ margin: '16px 0 8px' }}>Danh sách thành viên đăng ký</h4>
          <div className="participants-list">
            {managingMatch?.participants?.map((participant: MatchParticipant) => (
              <div key={participant.id} className="participant-item">
                <div className="participant-info">
                  <div className="participant-name">
                    <strong>{participant.user?.name ?? 'Thành viên'}</strong>
                    <span className="badge badge--soft">
                      <ShieldCheck size={12} /> {participant.user?.phone ? `${participant.user.phone} · ` : ''}Điểm: 100
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
                  {participant.status === 'pending' && (
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
              disabled={cancelMatch.isPending}
              onClick={() => {
                if (managingMatch && confirm('Bạn có chắc muốn huỷ bài tìm đối này?')) {
                  cancelMatch.mutate(managingMatch.id)
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
    </div>
  )
}
