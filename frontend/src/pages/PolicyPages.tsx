import { useState, type FormEvent } from 'react'
import {
  AlertTriangle,
  CheckCircle2,
  Mail,
  MapPin,
  Phone,
  Send,
  ShieldAlert,
  ShieldCheck,
  Sparkles,
  UserCheck,
} from 'lucide-react'
import { usePublicSettings } from '../hooks/usePublicSettings'
import { useToast } from '../components/Toast'
import { api } from '../api/client'
import { errorMessage } from '../api/errors'

function PolicyShell({
  kicker,
  title,
  updatedAt = '10/2026',
  children,
}: {
  kicker: string
  title: string
  updatedAt?: string
  children: React.ReactNode
}) {
  return (
    <div className="section" style={{ paddingTop: 36, paddingBottom: 64 }}>
      <div className="shell">
        <header style={{ marginBottom: 32, paddingBottom: 20, borderBottom: '1px solid var(--line)' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap', marginBottom: 12 }}>
            <span className="kicker">{kicker}</span>
            <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
              <span className="badge badge--soft" style={{ fontSize: 11 }}>Cập nhật: {updatedAt}</span>
              <span className="badge badge--warning" style={{ fontSize: 11 }}>Văn bản mô phỏng cho đồ án</span>
            </div>
          </div>
          <h1 style={{ fontSize: 'clamp(24px, 3.2vw, 36px)', margin: '0 0 10px', color: 'var(--forest)' }}>
            {title}
          </h1>
        </header>
        <div style={{ maxWidth: 860, margin: '0 auto', lineHeight: 1.75, color: 'var(--ink)' }}>
          {children}
        </div>
      </div>
    </div>
  )
}

/** 1. Điều khoản sử dụng (/dieu-khoan) */
export function TermsPage() {
  const { data: settings } = usePublicSettings()

  return (
    <PolicyShell kicker="Pháp lý & Quy định" title="Điều khoản sử dụng dịch vụ">
      <div className="content-card" style={{ padding: 24, marginBottom: 24 }}>
        <p style={{ margin: 0, fontStyle: 'italic', color: 'var(--soft)' }}>
          Chào mừng bạn đến với SportHub. Bằng việc truy cập hoặc sử dụng nền tảng, bạn đồng ý tuân thủ các điều khoản và quy định được nêu dưới đây. Vui lòng đọc kỹ trước khi đăng ký hoặc thực hiện giao dịch.
        </p>
      </div>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>1. Giới thiệu & Vai trò của SportHub</h2>
        <p>
          SportHub là nền tảng công nghệ kết nối trực tuyến giữa khách hàng có nhu cầu thuê sân thể thao và các chủ sở hữu, người quản lý cơ sở sân bãi hợp pháp tại Việt Nam. SportHub cung cấp các công cụ tìm kiếm, giữ chỗ, hàng đợi thông minh và đối soát doanh thu.
        </p>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>2. Quyền và trách nhiệm của khách hàng</h2>
        <ul style={{ paddingLeft: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <li>Cung cấp thông tin tài khoản, số điện thoại và email chính xác khi đăng ký để nhận thông báo đơn đặt sân.</li>
          <li>Có mặt tại sân đúng giờ đã đặt, giữ gìn vệ sinh chung và bảo quản trang thiết bị tại sân thể thao.</li>
          <li>Thực hiện chuyển khoản đặt cọc đúng số tiền và nội dung quy định trong vòng <strong>{settings?.payment_window_hours ?? 2} giờ</strong> sau khi đơn được chủ sân duyệt.</li>
          <li>Đơn không được thanh toán trong thời hạn hoặc chủ sân không phản hồi trong 24 giờ sẽ tự động bị huỷ.</li>
        </ul>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>3. Trách nhiệm của chủ sân</h2>
        <ul style={{ paddingLeft: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <li>Đảm bảo cơ sở vật chất, khung giờ thi đấu trống đúng theo lịch công bố trên hệ thống.</li>
          <li>Phản hồi yêu cầu đặt sân và đối chiếu sao kê thanh toán chuyển khoản của khách hàng kịp thời.</li>
          <li>Tiếp đón khách hàng và thực hiện thủ tục check-in theo quy định để ghi nhận điểm uy tín cho khách.</li>
        </ul>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>4. Miễn trừ trách nhiệm đối với giao dịch ngoài nền tảng</h2>
        <div className="notice notice--error" style={{ marginBottom: 16 }}>
          <ShieldAlert size={18} />
          <span>
            SportHub <strong>tuyệt đối không chịu trách nhiệm</strong> đối với bất kỳ khiếu nại, tranh chấp hay mất mát tài chính nào phát sinh từ các thỏa thuận chuyển tiền riêng ngoài hệ thống (qua Zalo, số tài khoản không hiển thị trên SportHub).
          </span>
        </div>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>5. Hệ thống điểm tin cậy (Trust Score)</h2>
        <p>
          Mỗi tài khoản bắt đầu với 100 điểm tin cậy. Điểm sẽ được cộng khi bạn đến sân đúng giờ (+10 điểm) và bị trừ khi hủy muộn (-10 đến -15 điểm) hoặc vắng mặt không lý do (-25 điểm). Tài khoản có điểm tin cậy quá thấp sẽ bị giới hạn số lượng đơn đặt sân cùng lúc.
        </p>
      </section>
    </PolicyShell>
  )
}

/** 2. Chính sách bảo mật (/chinh-sach-bao-mat) */
export function PrivacyPage() {
  const { data: settings } = usePublicSettings()

  return (
    <PolicyShell kicker="Bảo mật dữ liệu" title="Chính sách bảo mật thông tin">
      <div className="content-card" style={{ padding: 24, marginBottom: 24 }}>
        <p style={{ margin: 0, color: 'var(--soft)' }}>
          SportHub cam kết tuân thủ các quy định pháp luật về bảo vệ dữ liệu cá nhân. Chính sách này mô tả cách chúng tôi thu thập, sử dụng, lưu trữ và bảo vệ thông tin của bạn.
        </p>
      </div>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>1. Dữ liệu cá nhân thu thập</h2>
        <p>Chúng tôi chỉ thu thập các dữ liệu cần thiết phục vụ cho việc vận hành dịch vụ:</p>
        <ul style={{ paddingLeft: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <li><strong>Khách hàng:</strong> Họ tên, địa chỉ email, số điện thoại, địa chỉ (nếu có), lịch sử đặt sân và điểm uy tín.</li>
          <li><strong>Chủ sân:</strong> Họ tên người đại diện, số CCCD/định danh cá nhân, tên cơ sở kinh doanh, số tài khoản ngân hàng và các giấy tờ xác thực pháp lý.</li>
          <li><strong>Dữ liệu kỹ thuật:</strong> Địa chỉ IP, nhật ký truy cập và cookie kỹ thuật duy trì phiên làm việc.</li>
        </ul>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>2. Mục đích xử lý dữ liệu</h2>
        <ul style={{ paddingLeft: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <li>Xác thực tài khoản và gửi email thông báo trạng thái đơn đặt sân.</li>
          <li>Cung cấp số tài khoản và nội dung chuyển tiền để phục vụ việc chuyển khoản giữa khách và chủ sân.</li>
          <li>Hỗ trợ giải quyết tranh chấp, đối soát hoa hồng và nâng cao chất lượng dịch vụ.</li>
          <li>Không mua bán, chia sẻ hoặc chuyển giao dữ liệu cá nhân cho bên thứ ba vì mục đích thương mại.</li>
        </ul>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>3. Biện pháp bảo vệ và lưu trữ</h2>
        <p>
          Mật khẩu của người dùng được mã hóa bằng thuật toán băm an toàn (Bcrypt/Argon2). Các giấy tờ nhạy cảm như CCCD, giấy phép kinh doanh của chủ sân được lưu trữ trên vùng nhớ riêng biệt (private storage) có kiểm soát quyền truy cập nghiêm ngặt.
        </p>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>4. Quyền của chủ thể dữ liệu</h2>
        <p>
          Bạn có quyền được biết, đồng ý, truy cập, chỉnh sửa, rút lại sự đồng ý hoặc yêu cầu xóa dữ liệu cá nhân của mình bất kỳ lúc nào bằng cách liên hệ ban quản trị tại{' '}
          <a href={`mailto:${settings?.support_email ?? 'hotro@sporthub.vn'}`} style={{ color: 'var(--pitch)', fontWeight: 600 }}>
            {settings?.support_email ?? 'hotro@sporthub.vn'}
          </a>.
        </p>
      </section>
    </PolicyShell>
  )
}

/** 3. Chính sách hủy & hoàn cọc (/chinh-sach-huy-hoan) */
export function RefundPolicyPage() {
  const { data: settings } = usePublicSettings()
  const depositPercent = settings?.deposit_percent ?? 30
  const freeCancelHours = settings?.free_cancel_hours ?? 4

  return (
    <PolicyShell kicker="Minh bạch tài chính" title="Chính sách hủy đơn & Hoàn tiền cọc">
      <div className="content-card" style={{ padding: 24, marginBottom: 24 }}>
        <p style={{ margin: 0, color: 'var(--soft)' }}>
          Nhằm đảm bảo quyền lợi cân bằng giữa người chơi và cơ sở kinh doanh sân bãi, SportHub áp dụng quy chế đặt cọc và chính sách hủy đơn minh bạch, rõ ràng theo mốc thời gian.
        </p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 16, marginBottom: 32 }}>
        <div className="content-card" style={{ padding: 20, borderLeft: '4px solid var(--pitch)' }}>
          <strong style={{ color: 'var(--pitch)', fontSize: 16, display: 'block', marginBottom: 6 }}>Huỷ miễn phí (Trước {freeCancelHours} giờ)</strong>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--soft)' }}>
            Hủy trước giờ chơi tối thiểu <strong>{freeCancelHours} giờ</strong>: Hoàn lại toàn bộ {depositPercent}% tiền đặt cọc, không bị trừ điểm uy tín.
          </p>
        </div>

        <div className="content-card" style={{ padding: 20, borderLeft: '4px solid var(--forest)' }}>
          <strong style={{ color: 'var(--forest)', fontSize: 16, display: 'block', marginBottom: 6 }}>Ân hạn huỷ 2 giờ sau duyệt</strong>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--soft)' }}>
            Sau khi chủ sân duyệt đơn, bạn có <strong>ân hạn 2 giờ</strong> để huỷ miễn phí nhận lại 100% tiền cọc (áp dụng nếu thời điểm huỷ trước giờ chơi tối thiểu 1 giờ).
          </p>
        </div>

        <div className="content-card" style={{ padding: 20, borderLeft: '4px solid var(--warn)' }}>
          <strong style={{ color: 'var(--warn)', fontSize: 16, display: 'block', marginBottom: 6 }}>Huỷ muộn (Mất cọc)</strong>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--soft)' }}>
            Hủy sau thời hạn huỷ miễn phí / ân hạn: Tiền cọc được bồi hoàn cho chủ sân bù đắp slot trống; khách bị trừ cố định <strong>15 điểm uy tín</strong>.
          </p>
        </div>

        <div className="content-card" style={{ padding: 20, borderLeft: '4px solid var(--danger)' }}>
          <strong style={{ color: 'var(--danger)', fontSize: 16, display: 'block', marginBottom: 6 }}>Vắng mặt (No-show)</strong>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--soft)' }}>
            Không đến sân và không báo trước: Mất toàn bộ tiền cọc, ghi nhận vi phạm No-show và bị trừ 25 điểm uy tín.
          </p>
        </div>
      </div>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>Quy trình ghi nhận và xử lý hoàn cọc</h2>
        <ol style={{ paddingLeft: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <li>Khi khách hàng hủy đơn hợp lệ (trong khung giờ miễn phí hoặc thời gian ân hạn), hệ thống tự động ghi nhận bản ghi hoàn tiền (booking refund) và chuyển trạng thái đơn sang <em>Đã huỷ</em>.</li>
          <li>Khoản tiền cọc được đối chiếu để chuyển hoàn lại qua tài khoản ngân hàng của khách theo thông tin giao dịch đã ghi nhận.</li>
          <li>Trường hợp cần hỗ trợ đối chiếu giao dịch hoặc có thắc mắc về tiền hoàn, khách hàng liên hệ ban quản trị SportHub để được kiểm tra và xử lý bảo vệ quyền lợi.</li>
        </ol>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>Trường hợp bất khả kháng</h2>
        <p>
          Nếu buổi chơi bị hủy do lý do từ phía cơ sở sân bãi (sự cố điện nước, bảo trì đột xuất, thời tiết giông lốc nguy hiểm), chủ sân có nghĩa vụ hoàn trả 100% tiền cọc hoặc sắp xếp dời lịch thi đấu theo thỏa thuận với khách.
        </p>
      </section>
    </PolicyShell>
  )
}

/** 4. Quy chế hoạt động cho chủ sân (/quy-che-chu-san) */
export function OwnerRegulationsPage() {
  const { data: settings } = usePublicSettings()
  const commissionPercent = settings?.commission_percent ?? 8
  const dueDays = settings?.statement_due_days ?? 7

  return (
    <PolicyShell kicker="Dành cho đối tác" title="Quy chế hoạt động dành cho chủ sân">
      <div className="content-card" style={{ padding: 24, marginBottom: 24 }}>
        <p style={{ margin: 0, color: 'var(--soft)' }}>
          Quy chế này quy định quyền, trách nhiệm, cơ chế thu phí hoa hồng và các chế tài kỷ luật áp dụng đối với tất cả các đối tác quản lý cơ sở thể thao trên hệ thống SportHub.
        </p>
      </div>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>1. Điều kiện tham gia & Duyệt hồ sơ</h2>
        <p>Để trở thành đối tác chủ sân trên SportHub, người đăng ký cần:</p>
        <ul style={{ paddingLeft: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <li>Cung cấp số CCCD chính chủ và giấy chứng nhận đăng ký kinh doanh hoặc quyền sử dụng địa điểm sân thể thao.</li>
          <li>Kê khai số tài khoản ngân hàng chính xác để nhận thanh toán cọc trực tiếp từ khách hàng.</li>
          <li>Cam kết hình ảnh, bảng giá và tiện ích sân cập nhật đúng thực tế cơ sở.</li>
        </ul>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>2. Mô hình hoa hồng & Đối soát hàng tháng (Mô hình Agoda)</h2>
        <div className="content-card" style={{ padding: 20, background: 'var(--surface)', marginBottom: 16 }}>
          <ul style={{ margin: 0, paddingLeft: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
            <li><strong>Mức hoa hồng nền tảng:</strong> Cố định <strong>{commissionPercent}%</strong> tính trên doanh thu đơn hoàn thành hoặc phần cọc giữ lại từ đơn huỷ muộn/no-show.</li>
            <li><strong>Chu kỳ đối soát:</strong> Vào ngày 1 hàng tháng, SportHub tự động tổng hợp bảng kê đối soát hoa hồng cho doanh thu tháng trước.</li>
            <li><strong>Thời hạn thanh toán:</strong> Chủ sân có <strong>{dueDays} ngày</strong> để chuyển khoản phí hoa hồng về tài khoản định danh của nền tảng.</li>
          </ul>
        </div>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>3. Chế tài xử lý khi nợ hoa hồng quá hạn</h2>
        <div className="notice notice--warn" style={{ marginBottom: 16 }}>
          <AlertTriangle size={18} />
          <span>
            Nếu quá hạn <strong>{dueDays} ngày</strong> mà chủ sân chưa thanh toán hoa hồng, hệ thống sẽ <strong>tự động tạm khóa tính năng duyệt đơn mới</strong> của cụm sân đó cho tới khi hoàn tất nghĩa vụ tài chính.
          </span>
        </div>
      </section>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>4. Nghiêm cấm hành vi giao dịch ngoài nền tảng</h2>
        <div className="notice notice--error">
          <ShieldAlert size={18} />
          <span>
            Nghiêm cấm chủ sân yêu cầu khách hàng hủy đơn trên SportHub để giao dịch riêng qua Zalo hoặc số cá nhân nhằm trốn tránh phí hoa hồng. Trường hợp bị phát hiện hoặc có phản ánh kèm bằng chứng, tài khoản cơ sở sẽ bị <strong>đình chỉ vĩnh viễn</strong>.
          </span>
        </div>
      </section>
    </PolicyShell>
  )
}

/** 5. Câu hỏi thường gặp (/cau-hoi-thuong-gap) */
export function FaqPage() {
  const { data: settings } = usePublicSettings()
  const [openIdx, setOpenIdx] = useState<number | null>(0)

  const faqs = [
    {
      q: 'SportHub có thu thêm phụ phí đặt sân của khách hàng không?',
      a: 'Không. Khách hàng sử dụng SportHub hoàn toàn miễn phí, giá thuê sân đúng bằng bảng giá niêm yết công khai của chủ sân.',
    },
    {
      q: 'Tôi cần đặt cọc bao nhiêu phần trăm khi đặt sân?',
      a: `Mức đặt cọc mặc định là ${settings?.deposit_percent ?? 30}% tổng tiền đơn. Sau khi chủ sân duyệt đơn, bạn chuyển khoản số tiền cọc này để giữ chỗ; phần tiền còn lại bạn thanh toán trực tiếp tại sân khi đến chơi.`,
    },
    {
      q: 'Nếu tôi bận việc đột xuất thì hủy đơn như thế nào?',
      a: `Bạn có thể bấm Hủy đơn trong mục "Lịch đặt của tôi". Nếu hủy trước giờ chơi tối thiểu ${settings?.free_cancel_hours ?? 4} giờ, bạn được hoàn lại 100% tiền cọc và không bị trừ điểm uy tín.`,
    },
    {
      q: 'Mã check-in tại sân hoạt động như thế nào?',
      a: 'Khi đơn đặt sân đã được thanh toán xác nhận, hệ thống sẽ cấp một mã check-in định dạng CK-XXXXXXXX. Khi đến sân, bạn đọc mã này cho chủ sân để được tích lũy +10 điểm tin cậy.',
    },
    {
      q: 'Hàng đợi thông minh (Waitlist) hoạt động ra sao?',
      a: 'Nếu khung giờ bạn muốn đặt đã kín chỗ, bạn có thể bấm "Vào hàng đợi". Khi có người khác hủy lịch, hệ thống sẽ tự động gửi email và giữ suất độc quyền cho bạn trong 15 phút.',
    },
    {
      q: 'Làm thế nào để đăng ký trở thành chủ sân trên SportHub?',
      a: 'Bạn vào trang "Trở thành chủ sân", điền thông tin cơ sở, số tài khoản nhận tiền và chụp ảnh giấy phép/CCCD. Ban quản trị sẽ thẩm định và kích hoạt tài khoản của bạn trong 24 giờ.',
    },
  ]

  return (
    <PolicyShell kicker="Trợ giúp & Hướng dẫn" title="Câu hỏi thường gặp (FAQ)">
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        {faqs.map((faq, idx) => {
          const isOpen = openIdx === idx
          return (
            <div
              key={faq.q}
              className="content-card"
              style={{
                padding: 18,
                cursor: 'pointer',
                border: isOpen ? '1px solid var(--forest)' : undefined,
                transition: 'all 0.2s ease',
              }}
              onClick={() => setOpenIdx(isOpen ? null : idx)}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
                <strong style={{ fontSize: 16, color: 'var(--forest)' }}>{faq.q}</strong>
                <span style={{ fontSize: 18, fontWeight: 700, color: 'var(--muted)' }}>
                  {isOpen ? '−' : '+'}
                </span>
              </div>
              {isOpen && (
                <p style={{ margin: '12px 0 0', color: 'var(--soft)', fontSize: 14, lineHeight: 1.6 }}>
                  {faq.a}
                </p>
              )}
            </div>
          )
        })}
      </div>
    </PolicyShell>
  )
}

/** 6. Giới thiệu SportHub (/gioi-thieu) */
export function AboutPage() {
  return (
    <PolicyShell kicker="Về chúng tôi" title="Giới thiệu nền tảng SportHub">
      <div className="content-card" style={{ padding: 24, marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 22, marginTop: 0, marginBottom: 12 }}>
          Kết nối cộng đồng thể thao Việt Nam
        </h2>
        <p style={{ margin: 0, lineHeight: 1.7, color: 'var(--soft)' }}>
          SportHub ra đời với mục tiêu giải quyết bài toán đặt sân thủ công, thiếu minh bạch về giá và khó tìm kiếm bạn chơi trong cộng đồng thể thao phong trào tại Việt Nam. Chúng tôi xây dựng một nền tảng hiện đại, trực quan, hỗ trợ đa môn thể thao từ bóng đá, cầu lông, pickleball đến quần vợt và bóng rổ.
        </p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 20, marginBottom: 36 }}>
        <div className="content-card" style={{ padding: 20, textAlign: 'center' }}>
          <Sparkles size={32} style={{ color: 'var(--lime-deep)', margin: '0 auto 12px' }} />
          <h3 style={{ margin: '0 0 6px', fontSize: 18 }}>Nhanh chóng & Trực quan</h3>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--soft)' }}>
            Tra cứu lịch sân trống theo thời gian thực, xem bản đồ vị trí và đặt chỗ chỉ với vài thao tác.
          </p>
        </div>

        <div className="content-card" style={{ padding: 20, textAlign: 'center' }}>
          <ShieldCheck size={32} style={{ color: 'var(--pitch)', margin: '0 auto 12px' }} />
          <h3 style={{ margin: '0 0 6px', fontSize: 18 }}>Minh bạch & Tin cậy</h3>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--soft)' }}>
            Bảng giá niêm yết chuẩn xác, cơ chế cọc bảo vệ đôi bên và hệ thống điểm uy tín hạn chế hủy lịch vô cớ.
          </p>
        </div>

        <div className="content-card" style={{ padding: 20, textAlign: 'center' }}>
          <UserCheck size={32} style={{ color: 'var(--forest)', margin: '0 auto 12px' }} />
          <h3 style={{ margin: '0 0 6px', fontSize: 18 }}>Gắn kết cộng đồng</h3>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--soft)' }}>
            Kênh tìm đối giao lưu, ghép đội theo trình độ và hàng đợi thông minh khi khung giờ hết chỗ.
          </p>
        </div>
      </div>

      <section style={{ marginBottom: 32 }}>
        <h2 style={{ color: 'var(--forest)', fontSize: 20, marginBottom: 12 }}>Thông tin đồ án công nghệ</h2>
        <p>
          Dự án SportHub được phát triển bởi nhóm sinh viên đam mê công nghệ và thể thao, ứng dụng kiến trúc chuẩn RESTful API, Single Page Application với cơ chế bảo mật xác thực session/token hiện đại.
        </p>
      </section>
    </PolicyShell>
  )
}

/** 7. Liên hệ hỗ trợ (/lien-he) */
export function ContactPage() {
  const { data: settings } = usePublicSettings()
  const { success, error } = useToast()
  const [sent, setSent] = useState(false)
  const [loading, setLoading] = useState(false)
  const [name, setName] = useState('')
  const [contact, setContact] = useState('')
  const [content, setContent] = useState('')

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setLoading(true)
    try {
      await api.post('/contact', {
        subject_type: 'contact',
        category: 'other',
        content: `[Người gửi: ${name} | Liên hệ: ${contact}] ${content}`,
      })
      setLoading(false)
      setSent(true)
      success('Cảm ơn bạn đã gửi liên hệ! Đội ngũ hỗ trợ sẽ phản hồi trong vòng 24 giờ.', 'Đã tiếp nhận yêu cầu')
    } catch (err) {
      setLoading(false)
      error(errorMessage(err), 'Không thể gửi tin nhắn liên hệ')
    }
  }

  return (
    <PolicyShell kicker="Hỗ trợ & Hợp tác" title="Liên hệ với ban quản trị SportHub">
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: 28 }}>
        <div>
          <h2 style={{ color: 'var(--forest)', fontSize: 20, marginTop: 0, marginBottom: 16 }}>
            Thông tin liên hệ
          </h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
              <Mail size={18} style={{ color: 'var(--pitch)', marginTop: 3 }} />
              <div>
                <strong style={{ display: 'block', fontSize: 14 }}>Email hỗ trợ</strong>
                <a href={`mailto:${settings?.support_email ?? 'hotro@sporthub.vn'}`} style={{ color: 'var(--forest)', textDecoration: 'underline' }}>
                  {settings?.support_email ?? 'hotro@sporthub.vn'}
                </a>
              </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
              <Phone size={18} style={{ color: 'var(--pitch)', marginTop: 3 }} />
              <div>
                <strong style={{ display: 'block', fontSize: 14 }}>Tổng đài hỗ trợ</strong>
                <a href={`tel:${settings?.support_hotline?.replace(/\s+/g, '') ?? '19006868'}`} style={{ color: 'var(--forest)' }}>
                  {settings?.support_hotline ?? '1900 6868'} (8:00 – 21:00 hàng ngày)
                </a>
              </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
              <MapPin size={18} style={{ color: 'var(--pitch)', marginTop: 3 }} />
              <div>
                <strong style={{ display: 'block', fontSize: 14 }}>Địa chỉ văn phòng</strong>
                <span style={{ color: 'var(--soft)', fontSize: 13, lineHeight: 1.5 }}>
                  Khu Công nghệ cao, Hà Nội & Quận 1, TP. Hồ Chí Minh, Việt Nam
                </span>
              </div>
            </div>
          </div>
        </div>

        <div className="content-card" style={{ padding: 24 }}>
          <h2 style={{ color: 'var(--forest)', fontSize: 18, marginTop: 0, marginBottom: 16 }}>
            Gửi tin nhắn cho chúng tôi
          </h2>
          {sent ? (
            <div className="notice notice--success" style={{ margin: 0 }}>
              <CheckCircle2 size={18} />
              <span>Tin nhắn của bạn đã được gửi thành công. Ban quản trị SportHub đã ghi nhận và sẽ phản hồi sớm nhất!</span>
            </div>
          ) : (
            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
              <label>
                <span style={{ fontSize: 13, fontWeight: 600 }}>Họ và tên</span>
                <input
                  required
                  placeholder="Nguyễn Văn A"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                />
              </label>
              <label>
                <span style={{ fontSize: 13, fontWeight: 600 }}>Email hoặc số điện thoại</span>
                <input
                  required
                  placeholder="email@domain.com hoặc 0901234567"
                  value={contact}
                  onChange={(e) => setContact(e.target.value)}
                />
              </label>
              <label>
                <span style={{ fontSize: 13, fontWeight: 600 }}>Nội dung cần hỗ trợ (tối thiểu 5 ký tự)</span>
                <textarea
                  required
                  rows={4}
                  minLength={5}
                  placeholder="Mô tả thắc mắc hoặc sự cố bạn gặp phải..."
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                />
              </label>
              <button disabled={loading} type="submit" className="button button--brand button--block">
                <Send size={15} /> {loading ? 'Đang gửi...' : 'Gửi yêu cầu hỗ trợ'}
              </button>
            </form>
          )}
        </div>
      </div>
    </PolicyShell>
  )
}
