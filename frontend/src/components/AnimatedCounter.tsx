import { useEffect, useRef } from 'react'
import { animate, motion, useInView, useMotionValue, useTransform } from 'framer-motion'

interface AnimatedCounterProps {
  value: number | string
  duration?: number
}

const currencyFormatter = new Intl.NumberFormat('vi-VN')

export function AnimatedCounter({ value, duration = 1.1 }: AnimatedCounterProps) {
  const ref = useRef<HTMLSpanElement>(null)
  const isInView = useInView(ref, { once: true, margin: '0px 0px -20px 0px' })

  let targetNumber = 0
  let hasCurrencySuffix = false

  if (typeof value === 'number') {
    targetNumber = value
  } else {
    const cleanStr = String(value).trim()
    if (cleanStr.endsWith('đ')) {
      hasCurrencySuffix = true
    }
    const digitsOnly = cleanStr.replace(/[^\d]/g, '')
    targetNumber = digitsOnly ? parseInt(digitsOnly, 10) : 0
  }

  const count = useMotionValue(0)
  const rounded = useTransform(count, (latest) => {
    const val = Math.round(latest)
    return `${currencyFormatter.format(val)}${hasCurrencySuffix ? 'đ' : ''}`
  })

  useEffect(() => {
    if (isInView && targetNumber > 0) {
      const controls = animate(count, targetNumber, {
        duration,
        ease: [0.16, 1, 0.3, 1],
      })
      return controls.stop
    } else if (isInView && targetNumber === 0) {
      count.set(0)
    }
  }, [isInView, targetNumber, duration, count])

  return (
    <span ref={ref}>
      <motion.span>{rounded}</motion.span>
    </span>
  )
}
