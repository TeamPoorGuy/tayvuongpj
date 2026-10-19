import { useState } from 'react'

/** Ảnh đại diện; chưa có ảnh hoặc ảnh lỗi thì hiện chữ cái đầu của tên. */
export function Avatar({ name, src, className = 'avatar' }: { name?: string; src?: string | null; className?: string }) {
  const [failed, setFailed] = useState(false)
  const initial = (name ?? '?').slice(0, 1).toUpperCase()
  return (
    <div className={className}>
      {src && !failed ? <img src={src} alt={name ?? ''} onError={() => setFailed(true)} className="avatar__img" /> : initial}
    </div>
  )
}
