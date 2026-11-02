import { describe, expect, it, vi } from 'vitest'
import { ageFrom, formatDate, formatDateTime, statusStyle } from '@/utils/pipeline.js'

describe('pipeline presentation utilities', () => {
    it('formats dates and empty values consistently', () => {
        expect(formatDate(null)).toBe('—')
        expect(formatDate('2026-01-15T12:00:00Z')).toBe('Jan 15, 2026')
        expect(formatDateTime(null)).toBe('—')
        expect(formatDateTime('2026-01-15T12:30:00Z')).toContain('Jan 15, 2026')
    })

    it('uses configured and fallback status styles', () => {
        expect(statusStyle('Commercials').badge).toContain('yellow')
        expect(statusStyle('Unknown').dot).toBe('bg-outline')
    })

    it('reports relative age', () => {
        vi.useFakeTimers()
        vi.setSystemTime(new Date('2026-01-15T12:00:00Z'))

        expect(ageFrom('2026-01-15T11:59:30Z')).toBe('just now')
        expect(ageFrom('2026-01-13T12:00:00Z')).toBe('2d ago')

        vi.useRealTimers()
    })
})
