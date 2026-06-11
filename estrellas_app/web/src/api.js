const NW = typeof globalThis !== 'undefined' ? globalThis.EstrellasNW : undefined

const API_BASE = (
  NW && NW.restBase
    ? NW.restBase
    : (import.meta.env.VITE_API_BASE || 'http://localhost:8080')
).replace(/\/$/, '')

async function request(path, options = {}) {
  const method = String(options.method || 'GET').toUpperCase()
  const requestPath =
    method === 'GET'
      ? `${path}${path.includes('?') ? '&' : '?'}_=${Date.now()}`
      : path
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) }
  if (NW && NW.nonce) {
    headers['X-WP-Nonce'] = NW.nonce
  }
  const res = await fetch(`${API_BASE}${requestPath}`, {
    cache: 'no-store',
    credentials: NW ? 'same-origin' : 'omit',
    headers,
    ...options,
  })
  if (res.status === 204) return null
  const text = await res.text()
  if (!res.ok) {
    let msg = text || `HTTP ${res.status}`
    try {
      const j = JSON.parse(text)
      msg = typeof j === 'string' ? j : (j.message || j.code || msg)
    } catch (_) {
      /* mensaje plano */
    }
    throw new Error(msg)
  }
  if (!text) return null
  return JSON.parse(text)
}

export const api = {
  health: () => request('/health'),
  companies: () => request('/api/companies'),
  starTypes: () => request('/api/star-types'),

  // Challenges (Misiones)
  challenges: () => request('/api/challenges'),
  createChallenge: (name) => request('/api/challenges', { method: 'POST', body: JSON.stringify({ name }) }),
  updateChallenge: (id, name) => request(`/api/challenges/${id}`, { method: 'PUT', body: JSON.stringify({ name }) }),
  deleteChallenge: (id) => request(`/api/challenges/${id}`, { method: 'DELETE' }),

  // Employees
  employees: (params = {}) => {
    const qs = new URLSearchParams()
    if (params.query) qs.set('query', params.query)
    if (params.companyId) qs.set('companyId', params.companyId)
    if (params.activeOnly) qs.set('activeOnly', 'true')
    const q = qs.toString()
    return request(`/api/employees${q ? `?${q}` : ''}`)
  },
  createEmployee: (payload) => request('/api/employees', { method: 'POST', body: JSON.stringify(payload) }),
  updateEmployee: (id, payload) => request(`/api/employees/${id}`, { method: 'PUT', body: JSON.stringify(payload) }),
  deleteEmployee: (id) => request(`/api/employees/${id}`, { method: 'DELETE' }),

  // Star awards
  createStarAward: (payload) => request('/api/star-awards', { method: 'POST', body: JSON.stringify(payload) }),

  starAwards: (params = {}) => {
    const qs = new URLSearchParams()
    for (const [k, v] of Object.entries(params)) {
      if (v === undefined || v === null || v === '') continue
      qs.set(k, String(v))
    }
    const q = qs.toString()
    return request(`/api/star-awards${q ? `?${q}` : ''}`)
  },

  starCodes: (starCode, params = {}) => {
    const qs = new URLSearchParams()
    qs.set('starCode', starCode)
    if (params.availableOnly) qs.set('availableOnly', 'true')
    if (params.q) qs.set('q', params.q)
    if (params.limit) qs.set('limit', String(params.limit))
    return request(`/api/star-codes?${qs.toString()}`)
  },

  // ✅ Editar tipo + codigo (endpoint nuevo)
  editStarAward: (id, payload) =>
  request(`/api/star-awards/${id}/edit`, { method: 'PUT', body: JSON.stringify(payload) }),

  deleteStarAward: (id) => request(`/api/star-awards/${id}`, { method: 'DELETE' }),

  // Stats
  statsEmployee: (id, params = {}) => {
    const qs = new URLSearchParams()
    if (params.from) qs.set('from', params.from)
    if (params.to) qs.set('to', params.to)
    const q = qs.toString()
    return request(`/api/stats/employee/${id}${q ? `?${q}` : ''}`)
  },
  statsStarType: (code, params = {}) => {
    const qs = new URLSearchParams()
    if (params.companyId) qs.set('companyId', params.companyId)
    if (params.from) qs.set('from', params.from)
    if (params.to) qs.set('to', params.to)
    const q = qs.toString()
    return request(`/api/stats/star-type/${code}${q ? `?${q}` : ''}`)
  },
  ranking: (params = {}) => {
    const qs = new URLSearchParams()
    if (params.companyId) qs.set('companyId', params.companyId)
    if (params.from) qs.set('from', params.from)
    if (params.to) qs.set('to', params.to)
    const q = qs.toString()
    return request(`/api/stats/ranking${q ? `?${q}` : ''}`)
  },
  backupExport: () => request('/api/backup/export'),
  backupImport: (payload) => request('/api/backup/import', { method: 'POST', body: JSON.stringify(payload) }),
}
