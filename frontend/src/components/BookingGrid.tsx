import { useRef, useState, type KeyboardEvent } from 'react'
import type { GridCell, GridField } from '../types/api'
import { cellKey } from '../utils/grid'

const toMinutes = (time: string) => {
  const [h, m] = time.split(':').map(Number)
  return h * 60 + m
}
const toTime = (minutes: number) => `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`

const STATE_TITLE: Record<GridCell['state'], string> = {
  free: 'Còn trống — bấm để chọn',
  booked: 'Đã có người đặt — bấm để vào hàng chờ',
  held: 'Đang giữ chỗ cho người trong hàng chờ',
  locked: 'Sân khoá hoặc ngoài giờ mở cửa',
  past: 'Đã qua giờ',
}

const isInteractive = (cell?: GridCell) => cell?.state === 'free' || cell?.state === 'booked'

/**
 * Lưới đặt sân trực quan: mỗi hàng là một sân trong cụm, mỗi cột là 30 phút.
 * Bấm ô trống để chọn/bỏ chọn (chọn được nhiều ô, nhiều sân), bấm ô đã đặt để vào hàng chờ.
 * Bàn phím: cả lưới chỉ có một điểm Tab (roving tabindex); mũi tên di chuyển giữa các ô (bỏ qua ô không bấm được),
 * Home/End nhảy tới đầu/cuối hàng, Enter hoặc Space để chọn.
 */
export function BookingGrid({
  fields,
  selected,
  onToggle,
  onBookedClick,
}: {
  fields: GridField[]
  selected: Set<string>
  onToggle: (field: GridField, cell: GridCell) => void
  onBookedClick: (field: GridField, cell: GridCell) => void
}) {
  const [activeKey, setActiveKey] = useState<string | null>(null)
  const gridRef = useRef<HTMLDivElement>(null)

  const withCells = fields.filter((f) => f.cells.length > 0)
  if (withCells.length === 0) {
    return <p className="no-slot-notice">Sân chưa mở giờ nhận đặt cho ngày này.</p>
  }

  // Trục giờ chung của cả cụm: từ giờ mở sớm nhất tới giờ đóng muộn nhất.
  const open = Math.min(...withCells.map((f) => toMinutes(f.cells[0].start)))
  const close = Math.max(...withCells.map((f) => toMinutes(f.cells[f.cells.length - 1].end)))
  const columns = Array.from({ length: (close - open) / 30 }, (_, i) => toTime(open + i * 30))
  const lookup = withCells.map((f) => new Map(f.cells.map((c) => [c.start, c])))

  // Ô duy nhất nhận Tab: ô đang active nếu còn bấm được, không thì ô bấm được đầu tiên.
  const firstInteractive = (() => {
    for (let r = 0; r < withCells.length; r++) {
      for (const time of columns) if (isInteractive(lookup[r].get(time))) return cellKey(withCells[r].id, time)
    }
    return null
  })()
  const activeStillValid = activeKey !== null && withCells.some((f, r) => columns.some((t) => cellKey(f.id, t) === activeKey && isInteractive(lookup[r].get(t))))
  const tabStop = activeStillValid ? activeKey : firstInteractive

  const focusCell = (r: number, c: number) => {
    const key = cellKey(withCells[r].id, columns[c])
    setActiveKey(key)
    gridRef.current?.querySelector<HTMLElement>(`[data-key="${key}"]`)?.focus()
  }

  // Tìm ô bấm được kế tiếp theo hướng (dr, dc), bỏ qua ô không bấm được.
  const move = (r: number, c: number, dr: number, dc: number) => {
    let nr = r + dr
    let nc = c + dc
    while (nr >= 0 && nr < withCells.length && nc >= 0 && nc < columns.length) {
      if (isInteractive(lookup[nr].get(columns[nc]))) return focusCell(nr, nc)
      nr += dr
      nc += dc
    }
  }

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    const target = (event.target as HTMLElement).closest<HTMLElement>('[data-row]')
    if (!target) return
    const r = Number(target.dataset.row)
    const c = Number(target.dataset.col)
    const steps: Record<string, [number, number]> = { ArrowLeft: [0, -1], ArrowRight: [0, 1], ArrowUp: [-1, 0], ArrowDown: [1, 0] }
    if (event.key in steps) {
      event.preventDefault()
      move(r, c, ...steps[event.key])
    } else if (event.key === 'Home' || event.key === 'End') {
      event.preventDefault()
      // Về ô bấm được đầu/cuối của hàng hiện tại: quét từ mép tương ứng vào trong.
      if (event.key === 'Home') move(r, -1, 0, 1)
      else move(r, columns.length, 0, -1)
    }
  }

  return (
    <div className="bgrid-scroll">
      <p id="bgrid-help" className="visually-hidden">
        Lưới chọn giờ. Dùng phím mũi tên để di chuyển giữa các ô, Home và End để tới đầu hoặc cuối hàng, Enter hoặc Space để chọn ô trống hoặc vào hàng chờ ô đã đặt.
      </p>
      <div
        ref={gridRef}
        className="bgrid"
        role="grid"
        aria-label="Lưới chọn giờ theo sân"
        aria-describedby="bgrid-help"
        aria-multiselectable="true"
        onKeyDown={onKeyDown}
        style={{ gridTemplateColumns: `minmax(92px, max-content) repeat(${columns.length}, minmax(32px, 1fr))` }}
      >
        <div className="bgrid__row" role="row" style={{ display: 'contents' }}>
          <div className="bgrid__corner" role="columnheader" aria-label="Sân" />
          {columns.map((time) => (
            <div key={time} className="bgrid__time" role="columnheader" aria-label={time}>{time.endsWith(':00') ? time : ''}</div>
          ))}
        </div>

        {withCells.map((field, r) => (
          <div key={field.id} className="bgrid__row" role="row" style={{ display: 'contents' }}>
            <div className="bgrid__name" role="rowheader" title={field.name}>{field.name}</div>
            {columns.map((time, c) => {
              const cell = lookup[r].get(time)
              if (!cell) return <div key={time} className="bgrid__cell bgrid__cell--locked" role="gridcell" aria-disabled="true" title={STATE_TITLE.locked} />
              const key = cellKey(field.id, cell.start)
              const isSelected = selected.has(key)
              const clickable = isInteractive(cell)
              return (
                <button
                  key={time}
                  type="button"
                  role="gridcell"
                  data-key={key}
                  data-row={r}
                  data-col={c}
                  tabIndex={clickable && key === tabStop ? 0 : -1}
                  className={`bgrid__cell bgrid__cell--${isSelected ? 'selected' : cell.state}`}
                  disabled={!clickable}
                  aria-selected={cell.state === 'free' ? isSelected : undefined}
                  aria-label={`${field.name} ${cell.start}–${cell.end}: ${isSelected ? 'đang chọn' : STATE_TITLE[cell.state]}`}
                  title={`${cell.start}–${cell.end} · ${isSelected ? 'Đang chọn' : STATE_TITLE[cell.state]}`}
                  onFocus={() => setActiveKey(key)}
                  onClick={() => (cell.state === 'booked' ? onBookedClick(field, cell) : onToggle(field, cell))}
                />
              )
            })}
          </div>
        ))}
      </div>
    </div>
  )
}

export function BookingGridLegend() {
  return (
    <div className="bgrid-legend" aria-hidden="true">
      <span><i className="bgrid__cell--free" /> Trống</span>
      <span><i className="bgrid__cell--selected" /> Đang chọn</span>
      <span><i className="bgrid__cell--booked" /> Đã đặt</span>
      <span><i className="bgrid__cell--locked" /> Khoá / ngoài giờ</span>
      <span><i className="bgrid__cell--past" /> Đã qua</span>
    </div>
  )
}
