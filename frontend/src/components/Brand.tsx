import { Activity } from 'lucide-react'
import { Link } from 'react-router'

export function Brand() {
  return (
    <Link to="/" className="brand" aria-label="SportHub - Trang chủ">
      <span className="brand__mark"><Activity size={22} /></span>
      <span><strong>SportHub</strong><small>Đặt sân · Vào trận</small></span>
    </Link>
  )
}
