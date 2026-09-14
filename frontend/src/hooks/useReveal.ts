import { useEffect } from 'react'

/**
 * useReveal attaches an IntersectionObserver to elements matching the given selector (.reveal by default)
 * and adds the .is-visible class when they enter the viewport.
 * Automatically respects prefers-reduced-motion.
 */
export function useReveal(deps: unknown[] = [], selector = '.reveal', threshold = 0.12) {
  useEffect(() => {
    const isReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const elements = document.querySelectorAll<HTMLElement>(selector)

    if (elements.length === 0) return

    if (isReducedMotion) {
      elements.forEach((el) => el.classList.add('is-visible'))
      return
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible')
            observer.unobserve(entry.target)
          }
        })
      },
      { threshold, rootMargin: '0px 0px -40px 0px' }
    )

    elements.forEach((el) => {
      if (!el.classList.contains('is-visible')) {
        observer.observe(el)
      }
    })

    return () => {
      observer.disconnect()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps)
}
