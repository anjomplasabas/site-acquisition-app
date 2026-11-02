export const STATUS_CONFIG = {
    'Prospect':                  { badge: 'bg-slate-100 text-slate-600 border border-slate-200',     dot: 'bg-slate-400' },
    'Prospecting':               { badge: 'bg-blue-50 text-blue-700 border border-blue-100',          dot: 'bg-blue-500' },
    'Site Survey Pending':       { badge: 'bg-amber-50 text-amber-700 border border-amber-100',       dot: 'bg-amber-500' },
    'Site Survey':               { badge: 'bg-orange-50 text-orange-700 border border-orange-100',    dot: 'bg-orange-500' },
    'Commercials':               { badge: 'bg-yellow-50 text-yellow-700 border border-yellow-100',    dot: 'bg-yellow-500' },
    'Approval':                  { badge: 'bg-indigo-50 text-indigo-700 border border-indigo-100',    dot: 'bg-indigo-500' },
    'RFD':                       { badge: 'bg-emerald-50 text-emerald-700 border border-emerald-100', dot: 'bg-emerald-500' },
    'RFS':                       { badge: 'bg-green-50 text-green-700 border border-green-200',       dot: 'bg-green-500' },
    'Inactive':                  { badge: 'bg-red-50 text-red-700 border border-red-100',             dot: 'bg-red-500' },
    'Completed':                 { badge: 'bg-purple-50 text-purple-700 border border-purple-100',    dot: 'bg-purple-500' },
}

export function statusStyle(status) {
    return STATUS_CONFIG[status] ?? { badge: 'bg-surface-container text-on-surface-variant', dot: 'bg-outline' }
}

export function formatDate(value) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(value))
}

export function formatDateTime(value) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(value))
}

export function getInitials(name) {
    if (!name) return '??'
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

export function ageFrom(value) {
    if (!value) return null

    const seconds = Math.floor((Date.now() - new Date(value)) / 1000)
    if (seconds < 60) return 'just now'

    const units = [
        ['year', 31536000], ['month', 2592000], ['week', 604800],
        ['day', 86400], ['hour', 3600], ['minute', 60],
    ]

    for (const [label, secondsPerUnit] of units) {
        const value = Math.floor(seconds / secondsPerUnit)
        if (value >= 1) return `${value}${label[0]} ago`
    }

    return 'just now'
}

export function agingBadge(statusChangedAt) {
    if (!statusChangedAt) return null
    const days = Math.floor((Date.now() - new Date(statusChangedAt)) / 86400000)
    if (days < 7)  return { label: `${days}d`, classes: 'bg-green-50 text-green-700 border border-green-200' }
    if (days < 14) return { label: `${days}d`, classes: 'bg-amber-50 text-amber-700 border border-amber-200' }
    if (days < 30) return { label: `${days}d`, classes: 'bg-orange-50 text-orange-700 border border-orange-200' }
    return { label: `${days}d`, classes: 'bg-red-50 text-red-700 border border-red-200' }
}
