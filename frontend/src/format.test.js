import { expect, it } from 'vitest'
import { formatDate, formatDuration, toApiDate } from './format.js'

it('formats RFC 3339 dates and leaves unexpected values visible for diagnosis', () => {
  expect(formatDate(null)).toBe('—')
  expect(formatDate('2026-10-01T12:00:00+00:00')).not.toBe('2026-10-01T12:00:00+00:00')
  expect(formatDate('invalid-date')).toBe('invalid-date')
})

it('formats duration without treating zero as missing data', () => {
  expect(formatDuration(null)).toBe('Нет данных')
  expect(formatDuration(0)).toBe('0 мин')
  expect(formatDuration(7200)).toBe('2.0 ч')
})

it('converts date filters to the timezone format accepted by the API', () => {
  expect(toApiDate('')).toBeNull()
  expect(toApiDate('2026-10-01')).toMatch(/^2026-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/)
  expect(toApiDate('2026-10-01', true)).toMatch(/T\d\d:\d\d:\d\d\+00:00$/)
})
