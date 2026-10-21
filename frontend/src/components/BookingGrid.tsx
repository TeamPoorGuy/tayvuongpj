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

/**
 * Lưới đặt sân trực quan: mỗi hàng là một sân trong cụm, mỗi cột là 30 phút.
 * Bấm ô trống để chọn/bỏ chọn (chọn được nhiều ô, nhiều sân), bấm ô đã đặt để vào hàng chờ.
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
  const withCells = fields.filter((f) => f.cells.length > 0)
  if (withCells.length === 0) {
    return <p className="no-slot-notice">Sân chưa mở giờ nhận đặt cho ngày này.</p>
  }

  // Trục giờ chung của cả cụm: từ giờ mở sớm nhất tới giờ đóng muộn nhất.
  const open = Math.min(...withCells.map((f) => toMinutes(f.cells[0].start)))
  const close = Math.max(...withCells.map((f) => toMinutes(f.cells[f.cells.length - 1].end)))
  const columns = Array.from({ length: (close - open) / 30 }, (_, i) => toTime(open + i * 30))

  return (
    <div className="bgrid-scroll" role="region" aria-label="Lưới chọn giờ theo sân" tabIndex={0}>
      <div className="bgrid" style={{ gridTemplateColumns: `minmax(92px, max-content) repeat(${columns.length}, minmax(32px, 1fr))` }}>
        <div className="bgrid__corner" />
        {columns.map((time) => (
          <div key={time} className="bgrid__time">{time.endsWith(':00') ? time : ''}</div>
        ))}

        {withCells.map((field) => {
          const byStart = new Map(field.cells.map((c) => [c.start, c]))
          return (
            <div key={field.id} className="bgrid__row" style={{ display: 'contents' }}>
              <div className="bgrid__name" title={field.name}>{field.name}</div>
              {columns.map((time) => {
                const cell = byStart.get(time)
                if (!cell) return <div key={time} className="bgrid__cell bgrid__cell--locked" title={STATE_TITLE.locked} />
                const isSelected = selected.has(cellKey(field.id, cell.start))
                const clickable = cell.state === 'free' || cell.state === 'booked'
                return (
                  <button
                    key={time}
                    type="button"
                    className={`bgrid__cell bgrid__cell--${isSelected ? 'selected' : cell.state}`}
                    disabled={!clickable}
                    aria-pressed={cell.state === 'free' ? isSelected : undefined}
                    aria-label={`${field.name} ${cell.start}–${cell.end}: ${isSelected ? 'đang chọn' : STATE_TITLE[cell.state]}`}
                    title={`${cell.start}–${cell.end} · ${isSelected ? 'Đang chọn' : STATE_TITLE[cell.state]}`}
                    onClick={() => (cell.state === 'booked' ? onBookedClick(field, cell) : onToggle(field, cell))}
                  />
                )
              })}
            </div>
          )
        })}
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
