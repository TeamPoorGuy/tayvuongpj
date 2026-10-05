import { Compass, Copy, ExternalLink, MapPin, Navigation } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useToast } from './Toast'
import type { SportsField } from '../types/api'

declare global {
  interface Window {
    L: any
  }
}

interface FieldMapProps {
  field: SportsField
}

function calculateDistanceKm(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371 // Radius of earth in km
  const dLat = ((lat2 - lat1) * Math.PI) / 180
  const dLon = ((lon2 - lon1) * Math.PI) / 180
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2)
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
  return Math.round(R * c * 10) / 10
}

export function FieldMap({ field }: FieldMapProps) {
  const mapContainerRef = useRef<HTMLDivElement>(null)
  const mapInstanceRef = useRef<any>(null)
  const [leafletLoaded, setLeafletLoaded] = useState(typeof window !== 'undefined' && !!window.L)
  const [userLocation, setUserLocation] = useState<{ lat: number; lng: number } | null>(null)
  const [distanceKm, setDistanceKm] = useState<number | null>(null)
  const toast = useToast()

  const rawLat = field.latitude != null ? Number(field.latitude) : null
  const rawLng = field.longitude != null ? Number(field.longitude) : null
  const hasValidCoords = rawLat !== null && rawLng !== null && !isNaN(rawLat) && !isNaN(rawLng)

  // 1. Load Leaflet script and CSS if needed
  useEffect(() => {
    if (window.L) {
      setLeafletLoaded(true)
      return
    }

    const cssHref = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    if (!document.querySelector(`link[href="${cssHref}"]`)) {
      const link = document.createElement('link')
      link.rel = 'stylesheet'
      link.href = cssHref
      document.head.appendChild(link)
    }

    const scriptSrc = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
    const existingScript = document.querySelector(`script[src="${scriptSrc}"]`)
    if (existingScript) {
      existingScript.addEventListener('load', () => setLeafletLoaded(true))
    } else {
      const script = document.createElement('script')
      script.src = scriptSrc
      script.async = true
      script.onload = () => setLeafletLoaded(true)
      document.body.appendChild(script)
    }
  }, [])

  // 2. Request user geolocation to calculate distance
  useEffect(() => {
    if (!navigator.geolocation) return

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const uLat = pos.coords.latitude
        const uLng = pos.coords.longitude
        setUserLocation({ lat: uLat, lng: uLng })

        if (hasValidCoords && rawLat !== null && rawLng !== null) {
          const dist = calculateDistanceKm(uLat, uLng, rawLat, rawLng)
          setDistanceKm(dist)
        }
      },
      () => {
        // User denied geolocation, ignore
      },
      { timeout: 8000 },
    )
  }, [hasValidCoords, rawLat, rawLng])

  // 3. Render Leaflet Map
  useEffect(() => {
    if (!leafletLoaded || !hasValidCoords || !mapContainerRef.current || rawLat === null || rawLng === null) {
      return
    }

    const L = window.L
    if (!L) return

    // Clean previous instance
    if (mapInstanceRef.current) {
      mapInstanceRef.current.remove()
      mapInstanceRef.current = null
    }

    try {
      const map = L.map(mapContainerRef.current, {
        scrollWheelZoom: false,
      }).setView([rawLat, rawLng], 15)

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
      }).addTo(map)

      // Custom marker icon
      const customIcon = L.divIcon({
        className: 'leaflet-custom-marker',
        html: `<div style="background: #113324; color: #bbf7d0; border: 2px solid #fff; border-radius: 50%; width: 36px; height: 36px; display: grid; place-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg></div>`,
        iconSize: [36, 36],
        iconAnchor: [18, 36],
        popupAnchor: [0, -36],
      })

      const marker = L.marker([rawLat, rawLng], { icon: customIcon }).addTo(map)

      const popupContent = `
        <div style="font-family: inherit; padding: 4px 6px;">
          <strong style="font-size: 14px; color: #113324; display: block; margin-bottom: 2px;">${field.name}</strong>
          <span style="font-size: 12px; color: #475569; display: block; line-height: 1.4;">${field.address}</span>
        </div>
      `
      marker.bindPopup(popupContent).openPopup()

      // User location marker if available
      if (userLocation) {
        const userIcon = L.divIcon({
          className: 'leaflet-user-marker',
          html: `<div style="background: #2563eb; width: 14px; height: 14px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 4px rgba(37,99,235,0.3);"></div>`,
          iconSize: [14, 14],
          iconAnchor: [7, 7],
        })
        L.marker([userLocation.lat, userLocation.lng], { icon: userIcon })
          .addTo(map)
          .bindPopup('Vị trí hiện tại của bạn')
      }

      mapInstanceRef.current = map
    } catch {
      // Map init error fallback
    }

    return () => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.remove()
        mapInstanceRef.current = null
      }
    }
  }, [leafletLoaded, hasValidCoords, rawLat, rawLng, field.name, field.address, userLocation])

  // Navigation Links
  const googleMapsUrl = hasValidCoords
    ? userLocation
      ? `https://www.google.com/maps/dir/?api=1&origin=${userLocation.lat},${userLocation.lng}&destination=${rawLat},${rawLng}`
      : `https://www.google.com/maps/dir/?api=1&destination=${rawLat},${rawLng}`
    : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(field.address)}`

  const appleMapsUrl = hasValidCoords
    ? userLocation
      ? `https://maps.apple.com/?saddr=${userLocation.lat},${userLocation.lng}&daddr=${rawLat},${rawLng}`
      : `https://maps.apple.com/?daddr=${rawLat},${rawLng}`
    : `https://maps.apple.com/?daddr=${encodeURIComponent(field.address)}`

  return (
    <section className="content-card field-map-card reveal">
      <div className="field-map-head">
        <div>
          <h2>Bản đồ & Vị trí sân</h2>
          <p className="field-map-address">
            <MapPin size={16} />
            <span>{field.address}</span>
            <button
              type="button"
              className="copy-address-btn"
              onClick={() => {
                navigator.clipboard.writeText(field.address)
                toast.success('Đã sao chép địa chỉ sân vào bộ nhớ tạm.')
              }}
              title="Sao chép địa chỉ"
            >
              <Copy size={13} />
            </button>
          </p>
        </div>

        {distanceKm !== null && (
          <div className="field-distance-chip">
            <Compass size={14} />
            <span>Cách bạn <strong>{distanceKm} km</strong></span>
          </div>
        )}
      </div>

      {/* Map Display / Fallback */}
      <div className="field-map-frame-wrap">
        {hasValidCoords ? (
          <div ref={mapContainerRef} className="field-map-container" style={{ height: 320, width: '100%', borderRadius: 16 }} />
        ) : (
          <div className="field-map-fallback">
            <div className="fallback-inner">
              <MapPin size={36} />
              <h4>Vị trí: {field.address}</h4>
              <p>Tọa độ GPS chi tiết đang được cập nhật. Bạn có thể mở trực tiếp ứng dụng bản đồ để tìm đường đến sân.</p>
              <div className="directions-btn-group">
                <a
                  href={googleMapsUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="button button--brand button--small"
                >
                  <Navigation size={14} /> Chỉ đường Google Maps <ExternalLink size={12} />
                </a>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Directions action toolbar */}
      <div className="field-directions-toolbar">
        <span className="directions-label">Mở chỉ đường bằng ứng dụng:</span>
        <div className="directions-actions">
          <a
            href={googleMapsUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="button button--ghost button--small directions-btn"
          >
            <Navigation size={14} /> Google Maps <ExternalLink size={12} />
          </a>
          <a
            href={appleMapsUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="button button--ghost button--small directions-btn"
          >
            <Navigation size={14} /> Apple Maps <ExternalLink size={12} />
          </a>
        </div>
      </div>
    </section>
  )
}
