const labels: Record<string, string> = {
  pending: 'Chờ xử lý', confirmed: 'Đã xác nhận', completed: 'Hoàn thành',
  cancelled: 'Đã hủy', rejected: 'Từ chối', approved: 'Đã duyệt', inactive: 'Tạm đóng', suspended: 'Tạm khoá',
  awaiting: 'Chờ thanh toán', reported: 'Chờ xác nhận tiền',
}

/** `payment`: trạng thái thanh toán của đơn đặt sân; khi đơn còn chờ thì hiện bước thanh toán thay cho "Chờ xử lý". */
export function StatusBadge({ status, payment }: { status: string; payment?: string | null }) {
  const shown = status === 'pending' && (payment === 'awaiting' || payment === 'reported') ? payment : status
  return <span className={`status status--${shown}`}><i />{labels[shown] ?? shown}</span>
}
