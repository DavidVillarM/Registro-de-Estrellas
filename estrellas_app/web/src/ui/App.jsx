import React, { useEffect, useMemo, useRef, useState } from 'react'
import { api } from '../api.js'

function toCSV(rows, columns) {
  const esc = (v) => {
    const s = v === null || v === undefined ? '' : String(v)
    if (/[",\n\r]/.test(s)) return `"${s.replace(/"/g, '""')}"`
    return s
  }
  const header = columns.map(c => esc(c.header)).join(',')
  const body = rows.map(r => columns.map(c => esc(c.value(r))).join(',')).join('\n')
  return `${header}\n${body}\n`
}

function downloadCSV(filename, csvText) {
  const blob = new Blob(["\ufeff" + csvText], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

function downloadJSON(filename, data) {
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

function formatDateISO(d) {
  if (!d) return ''
  return new Date(d).toISOString().slice(0, 10)
}

const STAR_CODES = ['FUNNY', 'TEACHE', 'EARLY', 'BUDDY', 'SMARTY', 'BIRTHDAY']
const isCodeUsed = (v) => v === true || v === 1 || v === '1' || v === 'true'
const getAwardId = (award) => {
  const raw = award?.id ?? award?.awardId ?? award?.award_id ?? award?.starAwardId
  const id = Number(raw)
  return Number.isFinite(id) && id > 0 ? id : null
}

export default function App() {
  const isWordPress = typeof globalThis !== 'undefined' && !!globalThis.EstrellasNW
  const [tab, setTab] = useState('cargar') // cargar | funcionarios | misiones | reportes
  const [bootError, setBootError] = useState('')
  const [companies, setCompanies] = useState([])

  useEffect(() => {
    (async () => {
      try {
        await api.health()
        const c = await api.companies()
        setCompanies(c)
      } catch (e) {
        setBootError(e.message || String(e))
      }
    })()
  }, [])

  if (bootError) {
    return (
      <div className="container">
        <h1 className="title">Registro de Estrellas</h1>
        <div className="card danger">
          <b>No pude conectar con la API</b>
          <div className="muted" style={{ marginTop: 8 }}>{bootError}</div>
          <div className="muted" style={{ marginTop: 8 }}>
            Asegurate de levantar docker-compose y que la API esté en http://localhost:8080
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="container">
      <h1 className="title">Registro de Estrellas</h1>
      <p className="subtitle">Registro y consulta de estrellas (Newton / Crextar)</p>

      <div className="nav">
        <button className={"btn " + (tab === 'cargar' ? 'primary' : '')} onClick={() => setTab('cargar')}>Cargar estrella</button>
        <button className={"btn " + (tab === 'funcionarios' ? 'primary' : '')} onClick={() => setTab('funcionarios')}>Funcionarios</button>
        <button className={"btn " + (tab === 'misiones' ? 'primary' : '')} onClick={() => setTab('misiones')}>Misiones</button>
        <button className={"btn " + (tab === 'reportes' ? 'primary' : '')} onClick={() => setTab('reportes')}>Reportes</button>
        <button className={"btn " + (tab === 'respaldo' ? 'primary' : '')} onClick={() => setTab('respaldo')}>Respaldo</button>
        {!isWordPress && (
          <a className="btn" href="http://localhost:8080/swagger" target="_blank" rel="noreferrer">Swagger</a>
        )}
      </div>

      {tab === 'cargar' && <CargarEstrella companies={companies} />}
      {tab === 'funcionarios' && <Funcionarios companies={companies} />}
      {tab === 'misiones' && <Misiones />}
      {tab === 'reportes' && <Reportes companies={companies} />}
      {tab === 'respaldo' && <Respaldo />}
    </div>
  )
}

function Respaldo() {
  const [mode, setMode] = useState('merge')
  const [importText, setImportText] = useState('')
  const [msg, setMsg] = useState('')
  const [err, setErr] = useState('')
  const [busy, setBusy] = useState(false)
  const [fileMeta, setFileMeta] = useState(null)
  const importBufferRef = useRef('')
  const LARGE_JSON_THRESHOLD = 200_000

  async function onExport() {
    setMsg(''); setErr('')
    try {
      setBusy(true)
      const payload = await api.backupExport()
      const stamp = new Date().toISOString().replace(/[:.]/g, '-')
      downloadJSON(`estrellas-backup-${stamp}.json`, payload)
      setMsg('Exportación completada.')
    } catch (e) {
      setErr(e.message || String(e))
    } finally {
      setBusy(false)
    }
  }

  async function onImport() {
    setMsg(''); setErr('')
    try {
      const raw = (importText && importText.trim()) || importBufferRef.current || ''
      if (!raw.trim()) throw new Error('Pegá o cargá un JSON de respaldo')
      const payload = JSON.parse(raw)
      payload.mode = mode
      setBusy(true)
      const res = await api.backupImport(payload)
      setMsg(`Importación OK · Empresas: ${res.importedCompanies ?? 0}, Funcionarios: ${res.importedEmployees ?? 0}, Estrellas: ${res.importedAwards ?? 0}`)
    } catch (e) {
      setErr(e.message || String(e))
    } finally {
      setBusy(false)
    }
  }

  function onFile(file) {
    const reader = new FileReader()
    reader.onload = () => {
      const text = String(reader.result || '')
      importBufferRef.current = text
      setFileMeta({ name: file.name, bytes: text.length })
      if (text.length <= LARGE_JSON_THRESHOLD) {
        setImportText(text)
        setMsg('Archivo cargado en editor.')
      } else {
        setImportText('')
        setMsg(`Archivo grande cargado en memoria (${Math.round(text.length / 1024)} KB). Para evitar bloqueos, no se muestra completo en el editor.`)
      }
      setErr('')
    }
    reader.readAsText(file)
  }

  return (
    <div className="grid2">
      <div className="card">
        <h2 style={{ marginTop: 0 }}>Exportar respaldo</h2>
        <p className="muted" style={{ marginTop: 0 }}>
          Descarga todos los datos (empresas, funcionarios, misiones, estrellas y códigos) en un JSON portable.
        </p>
        <button className="btn primary" onClick={onExport} disabled={busy}>
          {busy ? 'Procesando...' : 'Exportar JSON'}
        </button>
      </div>

      <div className="card">
        <h2 style={{ marginTop: 0 }}>Importar respaldo</h2>
        <div className="row">
          <div style={{ flex: '1 1 240px' }}>
            <div className="muted" style={{ marginBottom: 6 }}>Modo de importación</div>
            <select value={mode} onChange={e => setMode(e.target.value)}>
              <option value="merge">Merge (fusionar sin borrar)</option>
              <option value="replace">Replace (reemplazar todo)</option>
            </select>
          </div>
          <div style={{ flex: '1 1 280px' }}>
            <div className="muted" style={{ marginBottom: 6 }}>Archivo JSON</div>
            <input type="file" accept=".json,application/json" onChange={e => e.target.files?.[0] && onFile(e.target.files[0])} />
          </div>
        </div>

        <div className="muted" style={{ marginTop: 10, marginBottom: 6 }}>Contenido JSON (editable)</div>
        {fileMeta && (
          <div className="muted" style={{ marginBottom: 8 }}>
            Archivo cargado: <b>{fileMeta.name}</b> ({Math.round(fileMeta.bytes / 1024)} KB)
          </div>
        )}
        {!importText && importBufferRef.current && (
          <div className="row" style={{ marginBottom: 8 }}>
            <button className="btn" onClick={() => setImportText(importBufferRef.current)}>
              Mostrar archivo en editor
            </button>
            <span className="muted">Solo recomendado para archivos chicos.</span>
          </div>
        )}
        <textarea
          value={importText}
          onChange={e => setImportText(e.target.value)}
          placeholder="Pegá aquí el backup JSON..."
          style={{ width: '100%', minHeight: 240, borderRadius: 10, border: '1px solid #dce3f4', padding: 10, fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace' }}
        />

        <div className="row" style={{ marginTop: 10 }}>
          <button className="btn primary" onClick={onImport} disabled={busy}>
            {busy ? 'Importando...' : 'Importar JSON'}
          </button>
        </div>

        {err && <div className="pill" style={{ marginTop: 10, borderColor: 'rgba(255,80,80,.45)', background: 'rgba(255,80,80,.15)' }}>{err}</div>}
        {msg && <div className="pill" style={{ marginTop: 10 }}>{msg}</div>}
      </div>
    </div>
  )
}

/* =========================
   TAB: CARGAR ESTRELLA
========================= */
function CargarEstrella({ companies }) {
  const [companyId, setCompanyId] = useState('')
  const [employeeQuery, setEmployeeQuery] = useState('')
  const [employeeId, setEmployeeId] = useState('')
  const [employeeList, setEmployeeList] = useState([])
  const [newEmployeeName, setNewEmployeeName] = useState('')
  const [awardDate, setAwardDate] = useState(formatDateISO(new Date()))
  const [starCode, setStarCode] = useState('FUNNY')

  const [codeQuery, setCodeQuery] = useState('')
  const [codes, setCodes] = useState([])
  const [uniqueCode, setUniqueCode] = useState('')
  const [showUsed, setShowUsed] = useState(false)

  const [challengeId, setChallengeId] = useState('')
  const [challengeName, setChallengeName] = useState('')
  const [challenges, setChallenges] = useState([])
  const [note, setNote] = useState('')
  const [msg, setMsg] = useState('')
  const [err, setErr] = useState('')
  const [reloadRecentKey, setReloadRecentKey] = useState(0)

  useEffect(() => {
    (async () => {
      try {
        const ch = await api.challenges()
        setChallenges(ch)
      } catch {}
    })()
  }, [])

  useEffect(() => {
    (async () => {
      try {
        const list = await api.starCodes(starCode, {
          q: codeQuery || undefined,
          availableOnly: showUsed ? false : true,
          limit: 300,
        })
        setCodes(list)
      } catch {}
    })()
  }, [starCode, codeQuery, showUsed])

  useEffect(() => {
    (async () => {
      try {
        const data = await api.employees({ query: employeeQuery, companyId: companyId || undefined, activeOnly: true })
        setEmployeeList(data)
      } catch {}
    })()
  }, [employeeQuery, companyId])

  const canCreateEmployee = useMemo(() => newEmployeeName.trim().length >= 3 && companyId, [newEmployeeName, companyId])

  async function onCreateEmployee() {
    setMsg(''); setErr('')
    try {
      const res = await api.createEmployee({ fullName: newEmployeeName.trim(), companyId: Number(companyId), isActive: true })
      setMsg('Funcionario creado ✔')
      setEmployeeId(String(res.id))
      setEmployeeQuery(newEmployeeName.trim())
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  async function onSaveAward() {
  setMsg(''); setErr('')
  try {
    if (!employeeId) throw new Error('Elegí un funcionario')

    const res = await api.createStarAward({
      employeeId: Number(employeeId),
      starCode,
      awardDate,
      challengeId: challengeId ? Number(challengeId) : null,
      challengeName: challengeName || null,
      note: note || null,
      uniqueCode: uniqueCode || null,   // ✅ manda el código manual si elegiste uno
    })

    setMsg(`Estrella guardada ✔ Código: ${res.uniqueCode || res.unique_code || '—'}`)
    setNote('')
    setUniqueCode('')
    setReloadRecentKey(k => k + 1)
    // opcional: refrescar lista de códigos para que se marque como usado
    // setCodeQuery('')
  } catch (e) {
    setErr(e.message || String(e))
  }
}

  return (
    <div className="grid2">
      <div className="card">
        <h2 style={{ marginTop: 0 }}>Cargar estrella</h2>

        <div className="row">
          <div style={{ flex: '1 1 240px' }}>
            <div className="muted" style={{ marginBottom: 6 }}>Empresa</div>
            <select value={companyId} onChange={e => { setCompanyId(e.target.value); setEmployeeId('') }}>
              <option value="">— Seleccionar —</option>
              {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>

          <div style={{ flex: '1 1 240px' }}>
            <div className="muted" style={{ marginBottom: 6 }}>Fecha</div>
            <input type="date" value={awardDate} onChange={e => setAwardDate(e.target.value)} />
          </div>
        </div>

        <div className="row" style={{ marginTop: 12 }}>
          <div style={{ flex: '1 1 340px' }}>
            <div className="muted" style={{ marginBottom: 6 }}>Buscar funcionario</div>
            <input placeholder="Escribí un nombre..." value={employeeQuery} onChange={e => setEmployeeQuery(e.target.value)} />
            <div className="muted" style={{ marginTop: 6 }}>Seleccionar</div>
            <select value={employeeId} onChange={e => setEmployeeId(e.target.value)}>
              <option value="">— Elegir —</option>
              {employeeList.map(e => (
                <option key={e.id} value={e.id}>{e.fullName} · {e.companyName}</option>
              ))}
            </select>
          </div>

          <div style={{ flex: '1 1 260px' }}>
            <div className="muted" style={{ marginBottom: 6 }}>Si no existe, crear</div>
            <input placeholder="Nombre completo" value={newEmployeeName} onChange={e => setNewEmployeeName(e.target.value)} />
            <button className={"btn " + (canCreateEmployee ? 'primary' : '')} disabled={!canCreateEmployee} onClick={onCreateEmployee} style={{ marginTop: 8 }}>
              Crear funcionario
            </button>
          </div>
        </div>

        <div className="row" style={{ marginTop: 12 }}>
          <div style={{ flex: '1 1 240px' }}>
            <div className="muted" style={{ marginBottom: 6 }}>Tipo de estrella</div>
            <select value={starCode} onChange={e => { setStarCode(e.target.value); setUniqueCode(''); setCodeQuery('') }}>
              {STAR_CODES.map(c => <option key={c} value={c}>{c}</option>)}
            </select>
          </div>

          <div style={{ flex: '1 1 240px' }}>
            <div className="muted" style={{ marginBottom: 6 }}>Código del sticker (opcional)</div>

            <input
              placeholder="Ej: F00 / FC02..."
              value={codeQuery}
              onChange={e => setCodeQuery(e.target.value)}
            />

            <div className="row" style={{ marginTop: 8, alignItems: 'center' }}>
              <label className="muted" style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                <input
                  type="checkbox"
                  checked={showUsed}
                  onChange={e => setShowUsed(e.target.checked)}
                />
                Mostrar usados (inhabilitados)
              </label>
            </div>

            <div className="muted" style={{ marginTop: 6 }}>Seleccionar</div>
            <select value={uniqueCode} onChange={e => setUniqueCode(e.target.value)}>
              <option value="">— Auto-asignar (primer disponible) —</option>
              {codes.map(c => (
                <option key={c.code} value={c.code} disabled={isCodeUsed(c.is_used) || isCodeUsed(c.isUsed)}>
                  {c.code} {(isCodeUsed(c.is_used) || isCodeUsed(c.isUsed)) ? ' (USADO)' : ' (DISPONIBLE)'}
                </option>
              ))}
            </select>

            {uniqueCode && (
              <div className="pill" style={{ marginTop: 8 }}>
                Código elegido: <b>{uniqueCode}</b>
              </div>
            )}
          </div>

          <div style={{ flex: '1 1 240px' }}>
            <div className="muted" style={{ marginBottom: 6 }}>Desafío (opcional)</div>
            <select value={challengeId} onChange={e => { setChallengeId(e.target.value); setChallengeName('') }}>
              <option value="">— Ninguno —</option>
              {challenges.map(ch => <option key={ch.id} value={ch.id}>{ch.name}</option>)}
            </select>
            <div className="muted" style={{ marginTop: 6 }}>o escribir nuevo</div>
            <input placeholder="Ej: Misión 00 - Fin de año" value={challengeName} onChange={e => { setChallengeName(e.target.value); setChallengeId('') }} />
          </div>
        </div>

        <div style={{ marginTop: 12 }}>
          <div className="muted" style={{ marginBottom: 6 }}>Nota (opcional)</div>
          <input placeholder="Observación..." value={note} onChange={e => setNote(e.target.value)} />
        </div>

        <div className="row" style={{ marginTop: 14 }}>
          <button className="btn primary" onClick={onSaveAward}>Guardar estrella</button>
          {msg && <span className="pill">{msg}</span>}
          {err && <span className="pill" style={{ borderColor: 'rgba(255,80,80,.45)', background: 'rgba(255,80,80,.15)' }}>{err}</span>}
        </div>
      </div>

      <UltimosRegistros companyId={companyId} reloadKey={reloadRecentKey} />
    </div>
  )
}

function UltimosRegistros({ companyId, reloadKey }) {
  const [rows, setRows] = useState([])
  const [err, setErr] = useState('')

  useEffect(() => {
    (async () => {
      try {
        const data = await api.starAwards({ companyId: companyId || undefined })
        setRows(data)
        setErr('')
      } catch (e) {
        setErr(e.message || String(e))
      }
    })()
  }, [companyId, reloadKey])

  return (
    <div className="card">
      <h3 style={{ marginTop: 0 }}>Últimos registros</h3>
      {err && <div className="pill" style={{ borderColor: 'rgba(255,80,80,.45)', background: 'rgba(255,80,80,.15)' }}>{err}</div>}
      <div style={{ maxHeight: 520, overflow: 'auto', marginTop: 10 }}>
        <table>
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Funcionario</th>
              <th>Empresa</th>
              <th>Estrella</th>
              <th>Código</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(r => (
              <tr key={r.id}>
                <td>{String(r.awardDate).slice(0, 10)}</td>
                <td>{r.fullName || r.full_name}</td>
                <td className="muted">{r.companyName || r.company_name}</td>
                <td><span className="pill">{r.starCode}</span></td>
                <td><span className="pill">{r.uniqueCode || r.unique_code}</span></td>
              </tr>
            ))}
            {rows.length === 0 && (
              <tr><td colSpan="5" className="muted">Sin datos</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}

/* =========================
   TAB: FUNCIONARIOS (CRUD + QUITAR + EDITAR ESTRELLA)
========================= */
function Funcionarios({ companies }) {
  const [uq, setUq] = useState('')
  const [uqStarCode, setUqStarCode] = useState('') // opcional
  const [uqRows, setUqRows] = useState([])
  const [uqLoading, setUqLoading] = useState(false)

  const [query, setQuery] = useState('')
  const [companyId, setCompanyId] = useState('')
  const [rows, setRows] = useState([])
  const [selected, setSelected] = useState(null)

  const [stats, setStats] = useState(null)
  const [awards, setAwards] = useState([])

  const [editName, setEditName] = useState('')
  const [editCompanyId, setEditCompanyId] = useState('')
  const [editActive, setEditActive] = useState(true)

  // ✅ edición de award
  const [editingAwardId, setEditingAwardId] = useState(null)
  const [editAwardStarCode, setEditAwardStarCode] = useState('FUNNY')
  const [editAwardCodeQuery, setEditAwardCodeQuery] = useState('')
  const [editAwardShowUsed, setEditAwardShowUsed] = useState(false)
  const [editAwardCodes, setEditAwardCodes] = useState([])
  const [editAwardUniqueCode, setEditAwardUniqueCode] = useState('')

  const [msg, setMsg] = useState('')
  const [err, setErr] = useState('')
  

  async function loadList() {
    try {
      const data = await api.employees({ query, companyId: companyId || undefined, activeOnly: false })
      setRows(data)
      setErr('')
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  async function refreshSelected(employee = selected) {
    if (!employee) return
    const [st, list] = await Promise.all([
      api.statsEmployee(employee.id),
      api.starAwards({ employeeId: employee.id }),
    ])
    setStats(st)
    setAwards(list)
  }

  useEffect(() => { loadList() }, [query, companyId])

  useEffect(() => {
    if (!editingAwardId) return
    ;(async () => {
      try {
        const list = await api.starCodes(editAwardStarCode, {
          q: editAwardCodeQuery || undefined,
          availableOnly: editAwardShowUsed ? false : true,
          limit: 400,
        })
        setEditAwardCodes(list)
      } catch {}
    })()
  }, [editingAwardId, editAwardStarCode, editAwardCodeQuery, editAwardShowUsed])

  async function openEmployee(e) {
    setSelected(e)
    setMsg(''); setErr('')
    setStats(null); setAwards([])

    setEditName(e.fullName)
    setEditCompanyId(String(e.companyId))
    setEditActive(Boolean(e.isActive))

    setEditingAwardId(null)

    try {
      await refreshSelected(e)
    } catch (er) {
      setErr(er.message || String(er))
    }
  }

  async function onSaveEmployee() {
    if (!selected) return
    setMsg(''); setErr('')
    try {
      await api.updateEmployee(selected.id, {
        fullName: editName.trim(),
        companyId: Number(editCompanyId),
        isActive: Boolean(editActive),
      })
      setMsg('Funcionario actualizado ✔')
      await loadList()
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  async function onDeleteEmployee() {
    if (!selected) return
    if (!confirm('¿Eliminar funcionario? Esto puede fallar si tiene estrellas (FK).')) return
    setMsg(''); setErr('')
    try {
      await api.deleteEmployee(selected.id)
      setMsg('Funcionario eliminado ✔')
      setSelected(null)
      setStats(null)
      setAwards([])
      await loadList()
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  async function onRemoveAward(award) {
    if (!selected) return
    const awardId = getAwardId(award)
    if (!awardId) {
      setErr('No se pudo identificar el ID de la estrella para eliminar.')
      return
    }
    if (!confirm('¿Quitar esta estrella? (el código debería volver a estar disponible)')) return
    setMsg(''); setErr('')
    try {
      await api.deleteStarAward(awardId)
      await refreshSelected()
      setMsg('Estrella quitada ✔')
      await loadList()
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  async function searchByCode() {
    setErr(''); setMsg('')
    setUqLoading(true)
    try {
      const data = await api.starAwards({
        uniqueCode: uq.trim(),
        starCode: uqStarCode || undefined, // opcional
        limit: undefined, // tu endpoint no usa limit, ignora
      })
      setUqRows(data)
      if (data.length === 0) setMsg('No encontré registros para ese código.')
    } catch (e) {
      setErr(e.message || String(e))
    } finally {
      setUqLoading(false)
    }
  }


  function startEditAward(a) {
    const awardId = getAwardId(a)
    if (!awardId) {
      setErr('No se pudo identificar el ID de la estrella para editar.')
      return
    }
    setEditingAwardId(awardId)
    setEditAwardStarCode(a.starCode)
    setEditAwardUniqueCode(a.uniqueCode || a.unique_code || '')
    setEditAwardCodeQuery('')
    setEditAwardShowUsed(false)
    setMsg(''); setErr('')
  }

  async function onSaveAwardEdit() {
    if (!editingAwardId) return
    setMsg(''); setErr('')
    try {
      const res = await api.editStarAward(editingAwardId, {
        starCode: editAwardStarCode,
        uniqueCode: editAwardUniqueCode || null, // si querés auto-asignar, poné null
      })
      setMsg(`Estrella actualizada ✔ Nuevo código: ${res.uniqueCode || res.unique_code || '—'}`)
      setEditingAwardId(null)
      await refreshSelected(selected)
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  return (
    <div className="grid2">
      <div className="card">
        <h2 style={{ marginTop: 0 }}>Funcionarios</h2>
        <div className="card" style={{ marginBottom: 12 }}>
          <div className="muted" style={{ marginBottom: 6 }}>Buscar estrella por código</div>

          <div className="row">
            <input
              style={{ flex: '1 1 240px' }}
              placeholder="Ej: F0101, FC0253..."
              value={uq}
              onChange={e => setUq(e.target.value)}
            />

            <select value={uqStarCode} onChange={e => setUqStarCode(e.target.value)}>
              <option value="">Todos los tipos</option>
              {['FUNNY','TEACHE','EARLY','BUDDY','SMARTY','BIRTHDAY'].map(c => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>

            <button className="btn primary" onClick={searchByCode} disabled={!uq.trim() || uqLoading}>
              {uqLoading ? 'Buscando...' : 'Buscar'}
            </button>

            <button
              className="btn"
              onClick={() => { setUq(''); setUqStarCode(''); setUqRows([]) }}
              disabled={uqLoading}
            >
              Limpiar
            </button>
          </div>

          {uqRows.length > 0 && (
            <div style={{ marginTop: 10, maxHeight: 260, overflow: 'auto' }}>
              <table>
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Código</th>
                    <th>Estrella</th>
                    <th>Funcionario</th>
                    <th>Empresa</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {uqRows.map(r => (
                    <tr key={r.id}>
                      <td>{String(r.awardDate).slice(0,10)}</td>
                      <td><span className="pill">{r.uniqueCode || r.unique_code}</span></td>
                      <td><span className="pill">{r.starCode}</span></td>
                      <td>{r.fullName || r.full_name}</td>
                      <td className="muted">{r.companyName || r.company_name}</td>
                      <td style={{ textAlign: 'right' }}>
                        <button
                          className="btn"
                          onClick={async () => {
                            // abrir el funcionario al toque
                            const list = await api.employees({ query: r.fullName || r.full_name, activeOnly: false })
                            const real = list.find(x => x.id === (r.employeeId || r.employee_id))
                            if (real) await openEmployee(real)

                            }}
                        >
                          Ver funcionario
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <div className="row">
          <input style={{ flex: '1 1 320px' }} placeholder="Buscar por nombre..." value={query} onChange={e => setQuery(e.target.value)} />
          <select value={companyId} onChange={e => setCompanyId(e.target.value)}>
            <option value="">Todas las empresas</option>
            {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </div>

        <div style={{ marginTop: 12, maxHeight: 520, overflow: 'auto' }}>
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Empresa</th>
                <th>Activo</th>
              </tr>
            </thead>
            <tbody>
              {rows.map(e => (
                <tr key={e.id} style={{ cursor: 'pointer' }} onClick={() => openEmployee(e)}>
                  <td>{e.fullName}</td>
                  <td className="muted">{e.companyName}</td>
                  <td>{e.isActive ? 'Sí' : 'No'}</td>
                </tr>
              ))}
              {rows.length === 0 && <tr><td colSpan="3" className="muted">Sin resultados</td></tr>}
            </tbody>
          </table>
        </div>

        {err && <div className="pill" style={{ marginTop: 10, borderColor: 'rgba(255,80,80,.45)', background: 'rgba(255,80,80,.15)' }}>{err}</div>}
        {msg && <div className="pill" style={{ marginTop: 10 }}>{msg}</div>}
      </div>

      <div className="card">
        <h3 style={{ marginTop: 0 }}>Detalle</h3>
        {!selected && <div className="muted">Seleccioná un funcionario para ver detalles, editar o quitar estrellas.</div>}

        {selected && (
          <>
            <div style={{ fontSize: 18, fontWeight: 800 }}>{selected.fullName}</div>
            <div className="muted" style={{ marginBottom: 10 }}>{selected.companyName}</div>

            <div className="row" style={{ gap: 10 }}>
              <div style={{ flex: '1 1 280px' }}>
                <div className="muted" style={{ marginBottom: 6 }}>Nombre</div>
                <input value={editName} onChange={e => setEditName(e.target.value)} />
              </div>

              <div style={{ flex: '1 1 220px' }}>
                <div className="muted" style={{ marginBottom: 6 }}>Empresa</div>
                <select value={editCompanyId} onChange={e => setEditCompanyId(e.target.value)}>
                  {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </div>

              <div style={{ flex: '0 0 160px' }}>
                <div className="muted" style={{ marginBottom: 6 }}>Activo</div>
                <select value={editActive ? 'true' : 'false'} onChange={e => setEditActive(e.target.value === 'true')}>
                  <option value="true">Sí</option>
                  <option value="false">No</option>
                </select>
              </div>
            </div>

            <div className="row" style={{ marginTop: 10 }}>
              <button className="btn primary" onClick={onSaveEmployee}>Guardar cambios</button>
              <button className="btn" onClick={onDeleteEmployee}>Eliminar</button>
            </div>

            <div style={{ marginTop: 14 }}>
              {!stats && <div className="muted">Cargando conteo...</div>}
              {stats && (
                <div className="row" style={{ alignItems: 'center', flexWrap: 'wrap' }}>
                  <span className="pill">Total: {stats.total}</span>
                  {(stats.byStar || []).map(s => (
                    <span key={s.starCode} className="pill">{s.starCode}: {s.count}</span>
                  ))}
                </div>
              )}
            </div>

            <div style={{ marginTop: 14 }}>
              <div className="muted" style={{ marginBottom: 8 }}>Estrellas registradas (podés quitar o editar)</div>

              {editingAwardId && (
                <div className="card" style={{ marginBottom: 12 }}>
                  <div style={{ fontWeight: 800, marginBottom: 8 }}>Editar estrella #{editingAwardId}</div>

                  <div className="row">
                    <div style={{ flex: '1 1 220px' }}>
                      <div className="muted" style={{ marginBottom: 6 }}>Tipo</div>
                      <select value={editAwardStarCode} onChange={e => { setEditAwardStarCode(e.target.value); setEditAwardUniqueCode(''); setEditAwardCodeQuery('') }}>
                        {STAR_CODES.map(c => <option key={c} value={c}>{c}</option>)}
                      </select>
                    </div>

                    <div style={{ flex: '1 1 320px' }}>
                      <div className="muted" style={{ marginBottom: 6 }}>Código</div>
                      <input
                        placeholder="Filtrar códigos: F00 / FC02..."
                        value={editAwardCodeQuery}
                        onChange={e => setEditAwardCodeQuery(e.target.value)}
                      />

                      <label className="muted" style={{ display: 'flex', gap: 8, alignItems: 'center', marginTop: 8 }}>
                        <input
                          type="checkbox"
                          checked={editAwardShowUsed}
                          onChange={e => setEditAwardShowUsed(e.target.checked)}
                        />
                        Mostrar usados (inhabilitados)
                      </label>

                      <div className="muted" style={{ marginTop: 6 }}>Seleccionar</div>
                      <select value={editAwardUniqueCode} onChange={e => setEditAwardUniqueCode(e.target.value)}>
                        <option value="">— Auto-asignar (primer disponible) —</option>
                        {editAwardCodes.map(c => (
                          <option key={c.code} value={c.code} disabled={isCodeUsed(c.is_used) || isCodeUsed(c.isUsed)}>
                            {c.code} {(isCodeUsed(c.is_used) || isCodeUsed(c.isUsed)) ? ' (USADO)' : ' (DISPONIBLE)'}
                          </option>
                        ))}
                      </select>

                      {editAwardUniqueCode && (
                        <div className="pill" style={{ marginTop: 8 }}>
                          Nuevo código: <b>{editAwardUniqueCode}</b>
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="row" style={{ marginTop: 10 }}>
                    <button className="btn primary" onClick={onSaveAwardEdit}>Guardar</button>
                    <button className="btn" onClick={() => setEditingAwardId(null)}>Cancelar</button>
                  </div>
                </div>
              )}

              <div style={{ maxHeight: 280, overflow: 'auto' }}>
                <table>
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      <th>Estrella</th>
                      <th>Desafío</th>
                      <th>Código</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {awards.map((a, idx) => {
                      const awardId = getAwardId(a)
                      return (
                      <tr key={awardId ?? `${a.uniqueCode || a.unique_code || 'award'}-${idx}`}>
                        <td>{String(a.awardDate).slice(0, 10)}</td>
                        <td><span className="pill">{a.starCode}</span></td>
                        <td className="muted">{a.challengeName || '—'}</td>
                        <td><span className="pill">{a.uniqueCode || a.unique_code}</span></td>
                        <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                          <button className="btn" onClick={() => startEditAward(a)}>Editar</button>
                          <button className="btn" onClick={() => onRemoveAward(a)} style={{ marginLeft: 8 }}>Quitar</button>
                        </td>
                      </tr>
                    )})}
                    {awards.length === 0 && <tr><td colSpan="5" className="muted">Sin estrellas</td></tr>}
                  </tbody>
                </table>
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  )
}

/* =========================
   TAB: MISIONES (CRUD)
========================= */
function Misiones() {
  const [rows, setRows] = useState([])
  const [newName, setNewName] = useState('')
  const [editId, setEditId] = useState(null)
  const [editName, setEditName] = useState('')

  const [msg, setMsg] = useState('')
  const [err, setErr] = useState('')

  async function load() {
    setMsg(''); setErr('')
    try {
      const ch = await api.challenges()
      setRows(ch)
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  useEffect(() => { load() }, [])

  async function onCreate() {
    setMsg(''); setErr('')
    try {
      if (newName.trim().length < 3) throw new Error('Nombre muy corto')
      await api.createChallenge(newName.trim())
      setNewName('')
      setMsg('Misión creada ✔')
      await load()
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  function startEdit(ch) {
    setEditId(ch.id)
    setEditName(ch.name)
    setMsg(''); setErr('')
  }

  async function onSaveEdit() {
    setMsg(''); setErr('')
    try {
      if (!editId) return
      if (editName.trim().length < 3) throw new Error('Nombre muy corto')
      await api.updateChallenge(editId, editName.trim())
      setEditId(null)
      setEditName('')
      setMsg('Misión actualizada ✔')
      await load()
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  async function onDelete(id) {
    if (!confirm('¿Eliminar misión? Si está en uso por estrellas, no te va a dejar.')) return
    setMsg(''); setErr('')
    try {
      await api.deleteChallenge(id)
      setMsg('Misión eliminada ✔')
      await load()
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  return (
    <div className="grid2">
      <div className="card">
        <h2 style={{ marginTop: 0 }}>Misiones</h2>

        <div className="row">
          <input style={{ flex: '1 1 420px' }} placeholder="Nueva misión (ej: Misión 04 - ...)" value={newName} onChange={e => setNewName(e.target.value)} />
          <button className={"btn " + (newName.trim().length >= 3 ? 'primary' : '')} disabled={newName.trim().length < 3} onClick={onCreate}>
            Crear
          </button>
        </div>

        {err && <div className="pill" style={{ marginTop: 10, borderColor: 'rgba(255,80,80,.45)', background: 'rgba(255,80,80,.15)' }}>{err}</div>}
        {msg && <div className="pill" style={{ marginTop: 10 }}>{msg}</div>}

        <div style={{ marginTop: 12, maxHeight: 520, overflow: 'auto' }}>
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th style={{ width: 220 }}></th>
              </tr>
            </thead>
            <tbody>
              {rows.map(ch => (
                <tr key={ch.id}>
                  <td>{ch.name}</td>
                  <td style={{ textAlign: 'right' }}>
                    <button className="btn" onClick={() => startEdit(ch)}>Editar</button>
                    <button className="btn" onClick={() => onDelete(ch.id)} style={{ marginLeft: 8 }}>Eliminar</button>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && <tr><td colSpan="2" className="muted">Sin misiones</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      <div className="card">
        <h3 style={{ marginTop: 0 }}>Editar misión</h3>
        {!editId && <div className="muted">Elegí una misión y tocá “Editar”.</div>}

        {editId && (
          <>
            <div className="muted" style={{ marginBottom: 6 }}>Nombre</div>
            <input value={editName} onChange={e => setEditName(e.target.value)} />
            <div className="row" style={{ marginTop: 10 }}>
              <button className="btn primary" onClick={onSaveEdit}>Guardar</button>
              <button className="btn" onClick={() => { setEditId(null); setEditName('') }}>Cancelar</button>
            </div>
          </>
        )}
      </div>
    </div>
  )
}

/* =========================
   TAB: REPORTES (3 sub-tabs)
========================= */
function Reportes({ companies }) {
  const [subTab, setSubTab] = useState('funcionarios') // funcionarios | estrella | codigo

  return (
    <div className="card">
      <h2 style={{ marginTop: 0 }}>Reportes</h2>

      <div className="nav" style={{ marginBottom: 12 }}>
        <button className={'btn ' + (subTab === 'funcionarios' ? 'primary' : '')} onClick={() => setSubTab('funcionarios')}>
          Funcionarios
        </button>
        <button className={'btn ' + (subTab === 'estrella' ? 'primary' : '')} onClick={() => setSubTab('estrella')}>
          Por estrella
        </button>
        <button className={'btn ' + (subTab === 'codigo' ? 'primary' : '')} onClick={() => setSubTab('codigo')}>
          Por código
        </button>
      </div>

      {subTab === 'funcionarios' && <ReportesFuncionarios companies={companies} />}
      {subTab === 'estrella' && <ReportesPorEstrella companies={companies} />}
      {subTab === 'codigo' && <ReportesPorCodigo companies={companies} />}
    </div>
  )
}

/* =========================
   SUBTAB: REPORTES FUNCIONARIOS
========================= */
function ReportesFuncionarios({ companies }) {
  const [companyId, setCompanyId] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [ranking, setRanking] = useState([])
  const [err, setErr] = useState('')
  const [detalle, setDetalle] = useState([])

  async function load() {
    setErr('')
    try {
      const [r, d] = await Promise.all([
        api.ranking({ companyId: companyId || undefined, from: from || undefined, to: to || undefined }),
        api.starAwards({ companyId: companyId || undefined, from: from || undefined, to: to || undefined }),
      ])
      setRanking(r)
      setDetalle(d)
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  useEffect(() => { load() }, [])

  return (
    <>
      <div className="row">
        <select value={companyId} onChange={e => setCompanyId(e.target.value)}>
          <option value="">Todas las empresas</option>
          {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>

        <input type="date" value={from} onChange={e => setFrom(e.target.value)} />
        <input type="date" value={to} onChange={e => setTo(e.target.value)} />

        <button className="btn primary" onClick={load}>Aplicar</button>
        <button
          className="btn"
          onClick={() => {
            const csv = toCSV(detalle, [
              { header: 'Fecha', value: r => String(r.awardDate).slice(0, 10) },
              { header: 'Funcionario', value: r => r.fullName || r.full_name },
              { header: 'Empresa', value: r => r.companyName || r.company_name },
              { header: 'Tipo de estrella', value: r => r.starCode },
              { header: 'Mision', value: r => r.challengeName || '—' },
              { header: 'Código', value: r => r.uniqueCode || r.unique_code || '-' },
              { header: 'Nota', value: r => r.note || '-' },
            ])
            const fname = `funcionarios_detalle_${companyId || 'todas'}_${from || 'inicio'}_${to || 'hoy'}.csv`
            downloadCSV(fname, csv)
          }}
          disabled={!detalle || detalle.length === 0}
        >
          Exportar CSV (detalle)
        </button>
      </div>

      {err && (
        <div className="pill" style={{ marginTop: 10, borderColor: 'rgba(255,80,80,.45)', background: 'rgba(255,80,80,.15)' }}>
          {err}
        </div>
      )}

      <div style={{ marginTop: 14, maxHeight: 520, overflow: 'auto' }}>
        <table>
          <thead>
            <tr>
              <th>Funcionario</th>
              <th>Empresa</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            {ranking.map(r => (
              <tr key={r.employee_id}>
                <td>{r.full_name}</td>
                <td className="muted">{r.company_name}</td>
                <td><span className="pill">{r.total}</span></td>
              </tr>
            ))}
            {ranking.length === 0 && <tr><td colSpan="3" className="muted">Sin datos</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  )
}

/* =========================
   SUBTAB: REPORTES POR ESTRELLA
========================= */
function ReportesPorEstrella({ companies }) {
  const [companyId, setCompanyId] = useState('')
  const [starCode, setStarCode] = useState('FUNNY')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [byStar, setByStar] = useState([])
  const [err, setErr] = useState('')
  const [detalle, setDetalle] = useState([])

  async function load() {
    setErr('')
    try {
      const [s, d] = await Promise.all([
        api.statsStarType(starCode, { companyId: companyId || undefined, from: from || undefined, to: to || undefined }),
        api.starAwards({ starCode, companyId: companyId || undefined, from: from || undefined, to: to || undefined }),
      ])
      setByStar(s)
      setDetalle(d)
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  useEffect(() => { load() }, [])

  return (
    <>
      <div className="row">
        <select value={companyId} onChange={e => setCompanyId(e.target.value)}>
          <option value="">Todas las empresas</option>
          {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>

        <select value={starCode} onChange={e => setStarCode(e.target.value)}>
          {STAR_CODES.map(c => <option key={c} value={c}>{c}</option>)}
        </select>

        <input type="date" value={from} onChange={e => setFrom(e.target.value)} />
        <input type="date" value={to} onChange={e => setTo(e.target.value)} />

        <button className="btn primary" onClick={load}>Aplicar</button>
        <button
          className="btn"
          onClick={() => {
            const csv = toCSV(detalle, [
              { header: 'Fecha', value: r => String(r.awardDate).slice(0, 10) },
              { header: 'Funcionario', value: r => r.fullName || r.full_name },
              { header: 'Empresa', value: r => r.companyName || r.company_name },
              { header: 'Mision', value: r => r.challengeName || '—' },
              { header: 'Estrella', value: r => r.starCode },
              { header: 'Código', value: r => r.uniqueCode || r.unique_code || '-' },
              { header: 'Nota', value: r => r.note || '' },
            ])
            const fname = `estrella_${starCode}_detalle_${companyId || 'todas'}_${from || 'inicio'}_${to || 'hoy'}.csv`
            downloadCSV(fname, csv)
          }}
          disabled={!detalle || detalle.length === 0}
        >
          Exportar CSV (detalle)
        </button>
      </div>

      {err && (
        <div className="pill" style={{ marginTop: 10, borderColor: 'rgba(255,80,80,.45)', background: 'rgba(255,80,80,.15)' }}>
          {err}
        </div>
      )}

      <div style={{ marginTop: 14 }}>
        <div className="muted" style={{ marginBottom: 8 }}>
          Funcionarios que recibieron <b>{starCode}</b> (con conteo)
        </div>

        <div style={{ maxHeight: 520, overflow: 'auto' }}>
          <table>
            <thead>
              <tr>
                <th>Funcionario</th>
                <th>Empresa</th>
                <th>Cant.</th>
              </tr>
            </thead>
            <tbody>
              {byStar.map(r => (
                <tr key={r.employee_id}>
                  <td>{r.full_name}</td>
                  <td className="muted">{r.company_name}</td>
                  <td><span className="pill">{r.count}</span></td>
                </tr>
              ))}
              {byStar.length === 0 && <tr><td colSpan="3" className="muted">Sin datos</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}

/* =========================
   SUBTAB: REPORTES POR CÓDIGO
========================= */
function ReportesPorCodigo({ companies }) {
  const [companyId, setCompanyId] = useState('')
  const [starCode, setStarCode] = useState('')
  const [uniqueCode, setUniqueCode] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [rows, setRows] = useState([])
  const [err, setErr] = useState('')

  async function load() {
    setErr('')
    try {
      const data = await api.starAwards({
        companyId: companyId || undefined,
        starCode: starCode || undefined,
        uniqueCode: uniqueCode || undefined, // ✅ busca por código (prefijo)
        from: from || undefined,
        to: to || undefined,
      })
      setRows(data)
    } catch (e) {
      setErr(e.message || String(e))
    }
  }

  useEffect(() => { load() }, [])

  return (
    <>
      <div className="row">
        <select value={companyId} onChange={e => setCompanyId(e.target.value)}>
          <option value="">Todas las empresas</option>
          {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>

        <select value={starCode} onChange={e => setStarCode(e.target.value)}>
          <option value="">Todas las estrellas</option>
          {STAR_CODES.map(c => <option key={c} value={c}>{c}</option>)}
        </select>

        <input
          placeholder="Código (ej: F0123 o FC02...)"
          value={uniqueCode}
          onChange={e => setUniqueCode(e.target.value)}
          style={{ minWidth: 260 }}
        />

        <input type="date" value={from} onChange={e => setFrom(e.target.value)} />
        <input type="date" value={to} onChange={e => setTo(e.target.value)} />

        <button className="btn primary" onClick={load}>Buscar</button>

        <button
          className="btn"
          onClick={() => {
            const csv = toCSV(rows, [
              { header: 'Fecha', value: r => String(r.awardDate).slice(0, 10) },
              { header: 'Funcionario', value: r => r.fullName || r.full_name },
              { header: 'Empresa', value: r => r.companyName || r.company_name },
              { header: 'Estrella', value: r => r.starCode },
              { header: 'Mision', value: r => r.challengeName || '—' },
              { header: 'Código', value: r => r.uniqueCode || r.unique_code || '-' },
              { header: 'Nota', value: r => r.note || '' },
            ])
            const fname = `busqueda_codigo_${uniqueCode || 'todos'}_${from || 'inicio'}_${to || 'hoy'}.csv`
            downloadCSV(fname, csv)
          }}
          disabled={!rows || rows.length === 0}
        >
          Exportar CSV
        </button>
      </div>

      {err && (
        <div className="pill" style={{ marginTop: 10, borderColor: 'rgba(255,80,80,.45)', background: 'rgba(255,80,80,.15)' }}>
          {err}
        </div>
      )}

      <div style={{ marginTop: 14, maxHeight: 520, overflow: 'auto' }}>
        <table>
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Funcionario</th>
              <th>Empresa</th>
              <th>Estrella</th>
              <th>Misión</th>
              <th>Código</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(r => (
              <tr key={r.id}>
                <td>{String(r.awardDate).slice(0, 10)}</td>
                <td>{r.fullName || r.full_name}</td>
                <td className="muted">{r.companyName || r.company_name}</td>
                <td><span className="pill">{r.starCode}</span></td>
                <td className="muted">{r.challengeName || '—'}</td>
                <td><span className="pill">{r.uniqueCode || r.unique_code}</span></td>
              </tr>
            ))}
            {rows.length === 0 && <tr><td colSpan="6" className="muted">Sin resultados</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  )
}
