import { useEffect, useRef, useState, type CSSProperties, type KeyboardEvent } from 'react'
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
  onSelectRange,
  onBookedClick,
}: {
  fields: GridField[]
  selected: Set<string>
  onToggle: (field: GridField, cell: GridCell) => void
  /** Giữ Shift rồi bấm ô thứ hai: chọn cả dải ô trống liền nhau trên cùng một sân. */
  onSelectRange: (field: GridField, cells: GridCell[]) => void
  onBookedClick: (field: GridField, cell: GridCell) => void
}) {
  const [activeKey, setActiveKey] = useState<string | null>(null)
  const anchor = useRef<{ fieldId: number; start: string } | null>(null)
  const gridRef = useRef<HTMLDivElement>(null)
  const scrollRef = useRef<HTMLDivElement>(null)

  const withCells = fields.filter((f) => f.cells.length > 0)

  // Màn hẹp phải cuộn ngang: đưa khung giờ cao điểm (17:00) vào tầm nhìn thay vì bắt khách tự cuộn từ 06:00.
  const firstColumn = withCells.length ? Math.min(...withCells.map((f) => toMinutes(f.cells[0].start))) : 0
  const lastColumn = withCells.length ? Math.max(...withCells.map((f) => toMinutes(f.cells[f.cells.length - 1].end))) : 0
  const firstFieldId = fields[0]?.id
  useEffect(() => {
    const scroller = scrollRef.current
    if (!scroller || scroller.scrollWidth <= scroller.clientWidth) return
    const target = 17 * 60
    if (target <= firstColumn || target >= lastColumn) return
    const cell = scroller.querySelector<HTMLElement>(`[data-col="${(target - firstColumn) / 30}"]`)
    const nameWidth = scroller.querySelector<HTMLElement>('.bgrid__name')?.offsetWidth ?? 0
    if (cell) scroller.scrollTo({ left: Math.max(0, cell.offsetLeft - nameWidth - 8) })
  }, [withCells.length, firstColumn, lastColumn, firstFieldId])

  if (withCells.length === 0) {
    return <p className="no-slot-notice">Sân chưa mở giờ nhận đặt cho ngày này.</p>
  }

  // Trục giờ chung của cả cụm: từ giờ mở sớm nhất tới giờ đóng muộn nhất.
  const open = Math.min(...withCells.map((f) => toMinutes(f.cells[0].start)))
  const close = Math.max(...withCells.map((f) => toMinutes(f.cells[f.cells.length - 1].end)))
  const columns = Array.from({ length: (close - open) / 30 }, (_, i) => toTime(open + i * 30))
  const lookup = withCells.map((f) => new Map(f.cells.map((c) => [c.start, c])))

  // Tên sân dùng chung phần đầu ("Sân Cầu Lông Tây Vương A1/A2…") nên chỉ hiện phần khác nhau để cột tên ngắn mà vẫn phân biệt được.
  const shortNames = (() => {
    const words = withCells.map((f) => f.name.split(' '))
    if (words.length < 2) return withCells.map((f) => f.name)
    let common = 0
    while (words.every((w) => w[common] !== undefined && w[common] === words[0][common])) common++
    // Giữ lại một từ cuối của phần chung để tên còn nghĩa ("Xanh 1" thay vì "1").
    const keep = Math.max(0, common - 1)
    return words.map((w, i) => (common < w.length ? w.slice(keep).join(' ') : withCells[i].name))
  })()

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
    <div className="bgrid-scroll" ref={scrollRef}>
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
        style={{ '--bgrid-cols': columns.length, gridTemplateColumns: `minmax(92px, max-content) repeat(${columns.length}, minmax(26px, 1fr))` } as CSSProperties}
      >
        <div className="bgrid__row" role="row" style={{ display: 'contents' }}>
          <div className="bgrid__corner" role="columnheader" aria-label="Sân" />
          {columns.map((time) => (
            <div key={time} className="bgrid__time" role="columnheader" aria-label={time}><span>{time.endsWith(':00') ? time : ''}</span></div>
          ))}
        </div>

        {withCells.map((field, r) => (
          <div key={field.id} className="bgrid__row" role="row" style={{ display: 'contents' }}>
            <div className="bgrid__name" role="rowheader" title={field.name}>{shortNames[r]}</div>
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
                  onClick={(event) => {
                    if (cell.state === 'booked') return onBookedClick(field, cell)
                    // Shift + bấm: chọn dải từ ô bấm trước đó tới ô này (cùng sân, bỏ qua ô không trống).
                    const from = anchor.current
                    if (event.shiftKey && from && from.fieldId === field.id && from.start !== cell.start) {
                      const a = field.cells.findIndex((c) => c.start === from.start)
                      const b = field.cells.findIndex((c) => c.start === cell.start)
                      const range = field.cells.slice(Math.min(a, b), Math.max(a, b) + 1).filter((c) => c.state === 'free')
                      anchor.current = { fieldId: field.id, start: cell.start }
                      return onSelectRange(field, range)
                    }
                    anchor.current = { fieldId: field.id, start: cell.start }
                    onToggle(field, cell)
                  }}
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
