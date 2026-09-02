const labels: Record<string, string> = {
  pending: 'Chờ xử lý', confirmed: 'Đã xác nhận', completed: 'Hoàn thành',
  cancelled: 'Đã hủy', rejected: 'Từ chối', approved: 'Đã duyệt', inactive: 'Tạm đóng',
}

export function StatusBadge({ status }: { status: string }) {
  return <span className={`status status--${status}`}><i />{labels[status] ?? status}</span>
}
