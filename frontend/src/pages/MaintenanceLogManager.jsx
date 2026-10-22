import { useEffect, useState } from 'react';
import {
  createMaintenanceLog,
  deleteMaintenanceLog,
  getAssetMaintenanceLogs,
  getAssets,
  getMaintenanceLogs,
  getMaintenanceTypes,
  updateMaintenanceLog,
} from '../api';

const EMPTY_FILTERS = {
  q: '',
  asset_id: '',
  maintenance_type: '',
  date_from: '',
  date_to: '',
  cost_min: '',
  cost_max: '',
};
const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal text-slate-800 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10';

function localToday() {
  const date = new Date();
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
  return date.toISOString().slice(0, 10);
}

function money(value) {
  return value === null || value === undefined
    ? '—'
    : Number(value).toLocaleString(undefined, { style: 'currency', currency: 'PHP' });
}

function MaintenanceLogManager({ asset, onOpenAsset, user }) {
  const isAdmin = user.role === 'admin';
  const [logs, setLogs] = useState([]);
  const [assets, setAssets] = useState([]);
  const [types, setTypes] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0, maintenance_cost_total: '0.00' });
  const [filters, setFilters] = useState(EMPTY_FILTERS);
  const [appliedFilters, setAppliedFilters] = useState(EMPTY_FILTERS);
  const [page, setPage] = useState(1);
  const [refreshKey, setRefreshKey] = useState(0);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [formOpen, setFormOpen] = useState(false);
  const [editingLog, setEditingLog] = useState(null);
  const assetId = asset?.id;

  useEffect(() => {
    let isMounted = true;

    async function fetchLogs() {
      try {
        const params = { ...appliedFilters, page, per_page: 15 };
        const response = assetId
          ? await getAssetMaintenanceLogs(assetId, params)
          : await getMaintenanceLogs(params);
        if (!isMounted) return;
        setLogs(response.data);
        setMeta(response.meta);
      } catch (err) {
        if (isMounted) setError(err.message || 'Unable to load maintenance logs.');
      } finally {
        if (isMounted) setLoading(false);
      }
    }

    fetchLogs();
    return () => {
      isMounted = false;
    };
  }, [assetId, appliedFilters, page, refreshKey]);

  useEffect(() => {
    let isMounted = true;
    getMaintenanceTypes()
      .then((response) => {
        if (isMounted) setTypes(response.data);
      })
      .catch((err) => {
        if (isMounted) setError(err.message || 'Unable to load maintenance types.');
      });
    return () => {
      isMounted = false;
    };
  }, []);

  useEffect(() => {
    if (assetId) return undefined;

    let isMounted = true;
    getAssets({ per_page: 100, sort_by: 'asset_tag', sort_direction: 'asc' })
      .then((response) => {
        if (isMounted) setAssets(response.data);
      })
      .catch((err) => {
        if (isMounted) setError(err.message || 'Unable to load assets for maintenance filters.');
      });
    return () => {
      isMounted = false;
    };
  }, [assetId]);

  function beginCreate() {
    setEditingLog(null);
    setFieldErrors({});
    setFormOpen(true);
  }

  function beginEdit(log) {
    setEditingLog(log);
    setFieldErrors({});
    setFormOpen(true);
  }

  async function saveLog(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    setFieldErrors({});
    const values = Object.fromEntries(new FormData(event.currentTarget));
    values.cost = values.cost === '' ? null : Number(values.cost);
    ['parts_replaced', 'remarks'].forEach((key) => {
      if (values[key] === '') values[key] = null;
    });

    try {
      if (editingLog) {
        await updateMaintenanceLog(editingLog.id, values);
      } else {
        await createMaintenanceLog(asset.id, values);
      }
      setFormOpen(false);
      setEditingLog(null);
      setLoading(true);
      setRefreshKey((current) => current + 1);
    } catch (err) {
      setError(err.message || 'Unable to save this maintenance log.');
      setFieldErrors(err.errors || {});
    } finally {
      setSaving(false);
    }
  }

  async function removeLog(log) {
    if (!window.confirm(`Delete the ${log.maintenance_type.toLowerCase()} log for ${log.date}?`)) return;
    setError('');
    try {
      await deleteMaintenanceLog(log.id);
      setLoading(true);
      if (logs.length === 1 && page > 1) {
        setPage(page - 1);
      } else {
        setRefreshKey((current) => current + 1);
      }
    } catch (err) {
      setError(err.message || 'Unable to delete this maintenance log.');
    }
  }

  function fieldError(name) {
    return fieldErrors[name]?.[0] ? (
      <span className="mt-1 block text-xs font-medium text-rose-700">{fieldErrors[name][0]}</span>
    ) : null;
  }

  const table = logs.length > 0 ? (
    <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200">
      <table className="w-full min-w-[820px] divide-y divide-slate-200 text-left text-sm">
        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            {!asset && <th className="px-4 py-3 font-semibold">Asset</th>}
            <th className="px-4 py-3 font-semibold">Date / type</th>
            <th className="px-4 py-3 font-semibold">Work performed</th>
            <th className="px-4 py-3 font-semibold">Performed by</th>
            <th className="px-4 py-3 font-semibold">Cost</th>
            {isAdmin && <th className="px-4 py-3" />}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {logs.map((log) => (
            <tr key={log.id}>
              {!asset && (
                <td className="px-4 py-3">
                  {log.asset && !log.asset.deleted_at ? (
                    <button
                      className="text-left font-semibold text-emerald-800 hover:text-emerald-950"
                      onClick={() => onOpenAsset(log.asset_id)}
                      type="button"
                    >
                      {log.asset.asset_name}
                      <span className="block text-xs font-normal text-slate-500">
                        {log.asset.asset_tag}{log.asset.deleted_at ? ' · deleted asset' : ''}
                      </span>
                    </button>
                  ) : log.asset ? (
                    <span className="text-slate-600">
                      {log.asset.asset_name}
                      <span className="block text-xs text-slate-500">{log.asset.asset_tag} · deleted asset</span>
                    </span>
                  ) : 'Asset unavailable'}
                </td>
              )}
              <td className="px-4 py-3">
                <p className="font-medium text-slate-800">{log.date}</p>
                <p className="mt-0.5 text-xs text-slate-500">{log.maintenance_type}</p>
              </td>
              <td className="max-w-sm px-4 py-3">
                <p className="whitespace-pre-wrap text-slate-700">{log.description_of_work}</p>
                {log.parts_replaced && <p className="mt-1 text-xs text-slate-500">Parts: {log.parts_replaced}</p>}
                {log.remarks && <p className="mt-1 text-xs text-slate-500">Remarks: {log.remarks}</p>}
              </td>
              <td className="px-4 py-3 text-slate-700">{log.performed_by}</td>
              <td className="px-4 py-3 font-medium text-slate-700">{money(log.cost)}</td>
              {isAdmin && (
                <td className="whitespace-nowrap px-4 py-3 text-right">
                  <button className="font-semibold text-emerald-800 hover:text-emerald-950" onClick={() => beginEdit(log)} type="button">Edit</button>
                  <button className="ml-3 font-semibold text-rose-700 hover:text-rose-900" onClick={() => removeLog(log)} type="button">Delete</button>
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  ) : (
    <p className="mt-4 rounded-xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500">
      No maintenance logs have been recorded{asset ? ' for this asset' : ''}.
    </p>
  );

  return (
    <section className={asset ? 'mt-7' : ''}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-lg font-semibold text-slate-900">{asset ? 'Maintenance history' : 'Maintenance logs'}</h3>
          {asset && (
            <p className="mt-1 text-sm text-slate-500">
              Total maintenance cost: <span className="font-semibold text-slate-800">{money(meta.maintenance_cost_total)}</span>
            </p>
          )}
          {!asset && <p className="mt-1 text-sm text-slate-500">{meta.total} maintenance log{meta.total === 1 ? '' : 's'}</p>}
        </div>
        {asset && isAdmin && (
          <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900" onClick={beginCreate} type="button">
            Add maintenance log
          </button>
        )}
      </div>

      {error && <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}

      {!asset && (
        <form
          className="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4"
          onSubmit={(event) => {
            event.preventDefault();
            setError('');
            if (page === 1 && JSON.stringify(filters) === JSON.stringify(appliedFilters)) {
              setLoading(true);
              setRefreshKey((current) => current + 1);
            } else {
              setLoading(true);
              setAppliedFilters(filters);
              setPage(1);
            }
          }}
        >
          <label className="text-sm font-semibold text-slate-700 sm:col-span-2">
            Search logs and assets
            <input className={inputClass} onChange={(event) => setFilters({ ...filters, q: event.target.value })} placeholder="Work, parts, performed by, asset tag or name" value={filters.q} />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Asset
            <select className={inputClass} onChange={(event) => setFilters({ ...filters, asset_id: event.target.value })} value={filters.asset_id}>
              <option value="">All assets</option>
              {assets.map((item) => <option key={item.id} value={item.id}>{item.asset_tag} · {item.asset_name}</option>)}
            </select>
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Type
            <select className={inputClass} onChange={(event) => setFilters({ ...filters, maintenance_type: event.target.value })} value={filters.maintenance_type}>
              <option value="">All types</option>
              {types.map((type) => <option key={type}>{type}</option>)}
            </select>
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Date from
            <input className={inputClass} onChange={(event) => setFilters({ ...filters, date_from: event.target.value })} type="date" value={filters.date_from} />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Date to
            <input className={inputClass} onChange={(event) => setFilters({ ...filters, date_to: event.target.value })} type="date" value={filters.date_to} />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Minimum cost
            <input className={inputClass} min="0" onChange={(event) => setFilters({ ...filters, cost_min: event.target.value })} step="0.01" type="number" value={filters.cost_min} />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Maximum cost
            <input className={inputClass} min="0" onChange={(event) => setFilters({ ...filters, cost_max: event.target.value })} step="0.01" type="number" value={filters.cost_max} />
          </label>
          <div className="flex items-end gap-2">
            <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900" type="submit">Apply filters</button>
            <button
              className="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white"
              onClick={() => {
                setFilters(EMPTY_FILTERS);
                setError('');
                if (page === 1 && JSON.stringify(appliedFilters) === JSON.stringify(EMPTY_FILTERS)) {
                  setLoading(true);
                  setRefreshKey((current) => current + 1);
                } else {
                  setLoading(true);
                  setAppliedFilters(EMPTY_FILTERS);
                  setPage(1);
                }
              }}
              type="button"
            >
              Reset
            </button>
          </div>
        </form>
      )}

      {formOpen && asset && (
        <form className="mt-5 grid gap-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 sm:grid-cols-2" key={editingLog?.id ?? 'new'} onSubmit={saveLog}>
          <label className="block text-sm font-semibold text-slate-700">
            Maintenance type
            <select className={inputClass} defaultValue={editingLog?.maintenance_type ?? ''} name="maintenance_type" required>
              <option value="">Select type</option>
              {types.map((type) => <option key={type}>{type}</option>)}
            </select>
            {fieldError('maintenance_type')}
          </label>
          <label className="block text-sm font-semibold text-slate-700">
            Date performed
            <input className={inputClass} defaultValue={editingLog?.date ?? localToday()} max={localToday()} name="date" required type="date" />
            {fieldError('date')}
          </label>
          <label className="block text-sm font-semibold text-slate-700">
            Performed by
            <input className={inputClass} defaultValue={editingLog?.performed_by ?? ''} maxLength={255} name="performed_by" required />
            {fieldError('performed_by')}
          </label>
          <label className="block text-sm font-semibold text-slate-700">
            Cost
            <input className={inputClass} defaultValue={editingLog?.cost ?? ''} min="0" name="cost" step="0.01" type="number" />
            {fieldError('cost')}
          </label>
          <label className="block text-sm font-semibold text-slate-700 sm:col-span-2">
            Description of work
            <textarea className={inputClass} defaultValue={editingLog?.description_of_work ?? ''} name="description_of_work" required rows={3} />
            {fieldError('description_of_work')}
          </label>
          <label className="block text-sm font-semibold text-slate-700 sm:col-span-2">
            Parts replaced
            <textarea className={inputClass} defaultValue={editingLog?.parts_replaced ?? ''} name="parts_replaced" rows={2} />
            {fieldError('parts_replaced')}
          </label>
          <label className="block text-sm font-semibold text-slate-700 sm:col-span-2">
            Remarks
            <textarea className={inputClass} defaultValue={editingLog?.remarks ?? ''} name="remarks" rows={2} />
            {fieldError('remarks')}
          </label>
          <div className="flex gap-2 sm:col-span-2">
            <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" disabled={saving} type="submit">
              {saving ? 'Saving...' : editingLog ? 'Save changes' : 'Save maintenance log'}
            </button>
            <button className="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600" onClick={() => setFormOpen(false)} type="button">
              Cancel
            </button>
          </div>
        </form>
      )}

      {loading ? <p className="py-8 text-center text-sm text-slate-500" role="status">Loading maintenance logs...</p> : table}

      {!loading && meta.total > 0 && (
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
          <span>Page {meta.current_page} of {meta.last_page}</span>
          <div className="flex gap-2">
            <button className="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 disabled:opacity-40" disabled={page <= 1} onClick={() => { setLoading(true); setPage(page - 1); }} type="button">Previous</button>
            <button className="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 disabled:opacity-40" disabled={page >= meta.last_page} onClick={() => { setLoading(true); setPage(page + 1); }} type="button">Next</button>
          </div>
        </div>
      )}
    </section>
  );
}

export default MaintenanceLogManager;
