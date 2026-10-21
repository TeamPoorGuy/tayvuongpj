/**
 * Giữ lại các ô khách đã chọn trên lưới khi họ rời trang đặt sân (vd sang đăng nhập) để quay về không phải chọn lại.
 * Lưu trong sessionStorage (theo tab); mọi thao tác đều bọc try/catch vì trình duyệt có thể chặn lưu trữ.
 */
const KEY_PREFIX = 'sporthub:selection:'
const MAX_AGE_MS = 60 * 60 * 1000

export interface SelectionDraft {
  date: string
  keys: string[]
}

export function loadSelectionDraft(slug: string, todayStr: string): SelectionDraft | null {
  try {
    const raw = sessionStorage.getItem(KEY_PREFIX + slug)
    if (!raw) return null
    const draft = JSON.parse(raw) as SelectionDraft & { savedAt: number }
    const fresh = Date.now() - draft.savedAt < MAX_AGE_MS
    // Ngày đã qua hoặc bản nháp quá cũ thì bỏ.
    return fresh && draft.date >= todayStr && Array.isArray(draft.keys) && draft.keys.length > 0 ? draft : null
  } catch {
    return null
  }
}

export function saveSelectionDraft(slug: string, date: string, keys: Iterable<string>) {
  try {
    const list = [...keys]
    if (list.length === 0) sessionStorage.removeItem(KEY_PREFIX + slug)
    else sessionStorage.setItem(KEY_PREFIX + slug, JSON.stringify({ date, keys: list, savedAt: Date.now() }))
  } catch {
    // Không lưu được thì thôi: chỉ mất tiện ích khôi phục, không ảnh hưởng việc đặt sân.
  }
}
