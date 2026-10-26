import type { GridCell, GridField } from '../types/api'

export const cellKey = (fieldId: number, start: string) => `${fieldId}|${start}`

/** Khoảng giờ liền nhau trên cùng một sân (gộp từ các ô đã chọn). */
export interface GridBlock {
  fieldId: number
  fieldName: string
  start: string
  end: string
  price: number
}

/** Gộp các ô đã chọn liền nhau trên cùng sân thành khoảng giờ, tính tiền theo giá từng ô. */
export function mergeSelection(fields: GridField[], selected: Set<string>): GridBlock[] {
  const blocks: GridBlock[] = []
  for (const field of fields) {
    let current: GridBlock | null = null
    for (const cell of field.cells) {
      if (!selected.has(cellKey(field.id, cell.start))) {
        current = null
        continue
      }
      const price = (cell.price_per_hour ?? field.price_per_hour) / 2
      if (current && current.end === cell.start) {
        current.end = cell.end
        current.price += price
      } else {
        current = { fieldId: field.id, fieldName: field.name, start: cell.start, end: cell.end, price }
        blocks.push(current)
      }
    }
  }
  return blocks
}

/** Khoảng giờ đã đặt liền nhau chứa ô vừa bấm (để vào hàng chờ / xem gợi ý cho đúng khoảng đó). */
export function bookedRunAround(field: GridField, cell: GridCell): { start: string; end: string } {
  const index = field.cells.findIndex((c) => c.start === cell.start)
  let from = index
  let to = index
  while (from > 0 && field.cells[from - 1].state === 'booked') from--
  while (to < field.cells.length - 1 && field.cells[to + 1].state === 'booked') to++
  return { start: field.cells[from].start, end: field.cells[to].end }
}
