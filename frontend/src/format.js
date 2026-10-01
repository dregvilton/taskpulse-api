export function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

export function toApiDate(value, endOfDay = false) {
  if (!value) return null
  const date = new Date(`${value}T${endOfDay ? '23:59:59' : '00:00:00'}`)
  return `${date.toISOString().slice(0, 19)}+00:00`
}

export function formatDuration(seconds) {
  if (seconds === null || seconds === undefined) return 'Нет данных'
  if (seconds < 3600) return `${Math.round(seconds / 60)} мин`
  if (seconds < 86400) return `${(seconds / 3600).toFixed(1)} ч`
  return `${(seconds / 86400).toFixed(1)} дн`
}
