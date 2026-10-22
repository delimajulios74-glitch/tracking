import { useEffect, useState } from 'react';
import {
  createAsset,
  deleteAsset,
  getAsset,
  getAssetLookups,
  getAssets,
  updateAsset,
  updateAssetCondition,
} from '../api';
import MaintenanceLogManager from './MaintenanceLogManager';
import MaintenanceUserManagement from './MaintenanceUserManagement';

const EMPTY_FILTERS = {
  q: '',
  category_id: '',
  location_id: '',
  status: '',
  condition: '',
  purchase_date_from: '',
  purchase_date_to: '',
};
const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal text-slate-800 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10';

function Badge({ children }) {
  const color = children === 'Good' || children === 'Available'
    ? 'bg-emerald-50 text-emerald-800'
    : children === 'Under Maintenance' || children === 'Fair'
      ? 'bg-amber-50 text-amber-800'
      : children === 'Disposed' || children === 'Unserviceable'
        ? 'bg-rose-50 text-rose-800'
        : 'bg-slate-100 text-slate-700';

  return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${color}`}>{children}</span>;
}

function AssetManagement({ navigationRequest, onCreateUser, onSectionChange, user }) {
  const isAdmin = user.role === 'admin';
  const [assets, setAssets] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [lookups, setLookups] = useState({ categories: [], locations: [], conditions: [], statuses: [] });
  const [filters, setFilters] = useState(EMPTY_FILTERS);
  const [page, setPage] = useState(1);
  const [screen, setScreen] = useState(() => ({
    type: navigationRequest?.section === 'maintenance'
      ? 'maintenance-list'
      : navigationRequest?.section === 'users'
        ? 'users'
        : 'list',
  }));
  const [detailTab, setDetailTab] = useState('overview');
  const [asset, setAsset] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  async function loadAssets(nextPage = page, nextFilters = filters) {
    setLoading(true);
    setError('');
    try {
      const response = await getAssets({
        ...nextFilters,
        sort_by: 'asset_tag',
        sort_direction: 'asc',
        page: nextPage,
        per_page: 10,
      });
      setAssets(response.data);
      setMeta(response.meta);
      setPage(response.meta.current_page);
    } catch (err) {
      setError(err.message || 'Unable to load assets.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let isMounted = true;
    Promise.all([getAssetLookups(), getAssets({ sort_by: 'asset_tag', sort_direction: 'asc', per_page: 10 })])
      .then(([lookupData, assetData]) => {
        if (!isMounted) return;
        setLookups(lookupData);
        setAssets(assetData.data);
        setMeta(assetData.meta);
      })
      .catch((err) => {
        if (isMounted) setError(err.message || 'Unable to load asset data.');
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  async function showAsset(id) {
    setLoading(true);
    setError('');
    try {
      const response = await getAsset(id);
      setAsset(response.data);
      setDetailTab('overview');
      onSectionChange?.('assets');
      setScreen({ type: 'detail' });
    } catch (err) {
      setError(err.message || 'Unable to load this asset.');
    } finally {
      setLoading(false);
    }
  }

  function beginCreate() {
    setAsset(null);
    onSectionChange?.('assets');
    setScreen({ type: 'form' });
    setError('');
    setNotice('');
  }

  async function beginEdit(item) {
    setLoading(true);
    setError('');
    try {
      const response = await getAsset(item.id);
      setAsset(response.data);
      setScreen({ type: 'form' });
    } catch (err) {
      setError(err.message || 'Unable to load this asset for editing.');
    } finally {
      setLoading(false);
    }
  }

  async function saveAsset(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    setNotice('');

    const values = Object.fromEntries(new FormData(event.currentTarget));
    values.category_id = Number(values.category_id);
    values.location_id = Number(values.location_id);
    values.purchase_cost = Number(values.purchase_cost);
    ['brand', 'model', 'serial_number', 'room_area', 'remarks'].forEach((key) => {
      if (values[key] === '') values[key] = null;
    });

    try {
      const response = asset
        ? await updateAsset(asset.id, values)
        : await createAsset(values);
      const detailResponse = await getAsset(response.data.id);
      setAsset(detailResponse.data);
      setDetailTab('overview');
      setNotice(asset ? 'Asset details updated.' : 'Asset registered successfully.');
      setScreen({ type: 'detail' });
      await loadAssets(page);
    } catch (err) {
      setError(err.message || 'Unable to save the asset.');
    } finally {
      setSaving(false);
    }
  }

  async function saveCondition(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    setNotice('');
    const condition = new FormData(event.currentTarget).get('condition');

    try {
      await updateAssetCondition(asset.id, condition);
      const response = await getAsset(asset.id);
      setAsset(response.data);
      setNotice('Asset condition updated.');
      await loadAssets(page);
    } catch (err) {
      setError(err.message || 'Unable to update the asset condition.');
    } finally {
      setSaving(false);
    }
  }

  async function removeAsset() {
    if (!window.confirm(`Soft-delete asset ${asset.asset_tag}?`)) return;
    setSaving(true);
    setError('');
    try {
      await deleteAsset(asset.id);
      setNotice(`Asset ${asset.asset_tag} was deleted.`);
      setScreen({ type: 'list' });
      setAsset(null);
      await loadAssets(page);
    } catch (err) {
      setError(err.message || 'Unable to delete the asset.');
    } finally {
      setSaving(false);
    }
  }

  function applyFilters(event) {
    event.preventDefault();
    setNotice('');
    setPage(1);
    loadAssets(1, filters);
  }

  function changeFilter(key, value) {
    setFilters((current) => ({ ...current, [key]: value }));
  }

  const field = (label, name, options = {}) => (
    <label className="block text-sm font-semibold text-slate-700" key={name}>
      {label}
      {options.select ? (
        <select className={inputClass} name={name} required={options.required} defaultValue={options.value ?? ''}>
          <option value="">Select {label.toLowerCase()}</option>
          {options.select.map((option) => (
            <option key={option.id ?? option} value={option.id ?? option}>
              {option.name ?? option}
            </option>
          ))}
        </select>
      ) : (
        <input
          className={inputClass}
          name={name}
          type={options.type || 'text'}
          required={options.required}
          min={options.min}
          step={options.step}
          defaultValue={options.value ?? ''}
        />
      )}
    </label>
  );

  return (
    <section className="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.15em] text-emerald-800">Asset management</p>
          <h2 className="mt-2 text-2xl font-semibold text-slate-900">
            {screen.type === 'detail'
              ? 'Asset details'
              : screen.type === 'form'
                ? (asset ? 'Edit asset' : 'Register asset')
                : screen.type === 'maintenance-list'
                  ? 'Maintenance logs'
                  : screen.type === 'users'
                    ? 'Users'
                    : 'Assets'}
          </h2>
          <p className="mt-1 text-sm text-slate-500">
            {screen.type === 'list'
              ? `${meta.total} asset${meta.total === 1 ? '' : 's'} in the inventory`
              : screen.type === 'maintenance-list'
                ? 'Search and review maintenance work across assets'
                : screen.type === 'users'
                  ? 'Create and manage access to the maintenance workspace'
                  : 'Property and equipment inventory'}
          </p>
        </div>
        {screen.type === 'list' && isAdmin && (
          <button className="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900" onClick={beginCreate} type="button">
            Add asset
          </button>
        )}
        {screen.type !== 'list' && (
          <button className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" onClick={() => { onSectionChange?.('assets'); setScreen({ type: 'list' }); setError(''); }} type="button">
            Back to assets
          </button>
        )}
      </div>

      {error && <p className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
      {notice && <p className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{notice}</p>}

      {screen.type === 'list' && (
        <>
          <form className="mt-6 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4" onSubmit={applyFilters}>
            <label className="text-sm font-semibold text-slate-700 sm:col-span-2">
              Search assets
              <input className={inputClass} onChange={(event) => changeFilter('q', event.target.value)} placeholder="Tag, name, brand, model, serial number" value={filters.q} />
            </label>
            <label className="text-sm font-semibold text-slate-700">
              Category
              <select className={inputClass} onChange={(event) => changeFilter('category_id', event.target.value)} value={filters.category_id}>
                <option value="">All categories</option>
                {lookups.categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </label>
            <label className="text-sm font-semibold text-slate-700">
              Location
              <select className={inputClass} onChange={(event) => changeFilter('location_id', event.target.value)} value={filters.location_id}>
                <option value="">All locations</option>
                {lookups.locations.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </label>
            <label className="text-sm font-semibold text-slate-700">
              Status
              <select className={inputClass} onChange={(event) => changeFilter('status', event.target.value)} value={filters.status}>
                <option value="">All statuses</option>
                {lookups.statuses.map((value) => <option key={value}>{value}</option>)}
              </select>
            </label>
            <label className="text-sm font-semibold text-slate-700">
              Condition
              <select className={inputClass} onChange={(event) => changeFilter('condition', event.target.value)} value={filters.condition}>
                <option value="">All conditions</option>
                {lookups.conditions.map((value) => <option key={value}>{value}</option>)}
              </select>
            </label>
            <label className="text-sm font-semibold text-slate-700">
              Purchased from
              <input className={inputClass} onChange={(event) => changeFilter('purchase_date_from', event.target.value)} type="date" value={filters.purchase_date_from} />
            </label>
            <label className="text-sm font-semibold text-slate-700">
              Purchased to
              <input className={inputClass} onChange={(event) => changeFilter('purchase_date_to', event.target.value)} type="date" value={filters.purchase_date_to} />
            </label>
            <div className="flex items-end gap-2">
              <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900" type="submit">Apply filters</button>
              <button className="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white" onClick={() => { setFilters(EMPTY_FILTERS); setPage(1); loadAssets(1, EMPTY_FILTERS); }} type="button">Reset</button>
            </div>
          </form>

          {loading ? (
            <p className="py-12 text-center text-sm text-slate-500" role="status">Loading assets...</p>
          ) : assets.length === 0 ? (
            <div className="mt-6 rounded-xl border border-dashed border-slate-300 px-5 py-12 text-center">
              <h3 className="font-semibold text-slate-800">No assets found</h3>
              <p className="mt-1 text-sm text-slate-500">Try changing your search or filters.</p>
            </div>
          ) : (
            <div className="mt-5 overflow-x-auto rounded-xl border border-slate-200">
              <table className="w-full min-w-[760px] divide-y divide-slate-200 text-left text-sm">
                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Asset</th>
                    <th className="px-4 py-3 font-semibold">Category / location</th>
                    <th className="px-4 py-3 font-semibold">Condition</th>
                    <th className="px-4 py-3 font-semibold">Status</th>
                    <th className="px-4 py-3 font-semibold">Acquisition value</th>
                    <th className="px-4 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {assets.map((item) => (
                    <tr className="hover:bg-slate-50/70" key={item.id}>
                      <td className="px-4 py-3">
                        <p className="font-semibold text-slate-900">{item.asset_name}</p>
                        <p className="mt-0.5 text-xs text-slate-500">{item.asset_tag}</p>
                      </td>
                      <td className="px-4 py-3 text-slate-600">
                        <p>{item.category?.name}</p>
                        <p className="mt-0.5 text-xs text-slate-500">{item.location?.name}</p>
                      </td>
                      <td className="px-4 py-3"><Badge>{item.condition}</Badge></td>
                      <td className="px-4 py-3"><Badge>{item.status}</Badge></td>
                      <td className="px-4 py-3 font-medium text-slate-700">{Number(item.purchase_cost).toLocaleString(undefined, { style: 'currency', currency: 'PHP' })}</td>
                      <td className="px-4 py-3 text-right">
                        <button className="font-semibold text-emerald-800 hover:text-emerald-950" onClick={() => showAsset(item.id)} type="button">View</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {!loading && meta.total > 0 && (
            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
              <span>Page {meta.current_page} of {meta.last_page} · {meta.total} total</span>
              <div className="flex gap-2">
                <button className="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" disabled={page <= 1} onClick={() => loadAssets(page - 1)} type="button">Previous</button>
                <button className="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" disabled={page >= meta.last_page} onClick={() => loadAssets(page + 1)} type="button">Next</button>
              </div>
            </div>
          )}
        </>
      )}

      {screen.type === 'form' && (
        <form className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" key={asset?.id || 'new-asset'} onSubmit={saveAsset}>
          {field('Asset tag', 'asset_tag', { required: true, value: asset?.asset_tag })}
          {field('Asset name', 'asset_name', { required: true, value: asset?.asset_name })}
          {field('Category', 'category_id', { required: true, select: lookups.categories, value: asset?.category_id ?? '' })}
          {field('Location', 'location_id', { required: true, select: lookups.locations, value: asset?.location_id ?? '' })}
          {field('Brand', 'brand', { value: asset?.brand })}
          {field('Model', 'model', { value: asset?.model })}
          {field('Serial number', 'serial_number', { value: asset?.serial_number })}
          {field('Room / area', 'room_area', { value: asset?.room_area })}
          {field('Purchase date', 'purchase_date', { required: true, type: 'date', value: asset?.purchase_date })}
          {field('Acquisition value', 'purchase_cost', { required: true, type: 'number', min: '0', step: '0.01', value: asset?.purchase_cost })}
          {field('Condition', 'condition', { required: true, select: lookups.conditions, value: asset?.condition ?? 'Good' })}
          {field('Status', 'status', { required: true, select: lookups.statuses, value: asset?.status ?? 'Available' })}
          <label className="block text-sm font-semibold text-slate-700 sm:col-span-2 lg:col-span-3">
            Description
            <textarea className={inputClass} name="description" required rows={3} defaultValue={asset?.description ?? ''} />
          </label>
          <label className="block text-sm font-semibold text-slate-700 sm:col-span-2 lg:col-span-3">
            Remarks
            <textarea className={inputClass} name="remarks" rows={2} defaultValue={asset?.remarks ?? ''} />
          </label>
          <div className="flex gap-2 sm:col-span-2 lg:col-span-3">
            <button className="rounded-xl bg-emerald-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-50" disabled={saving} type="submit">
              {saving ? 'Saving...' : asset ? 'Save changes' : 'Register asset'}
            </button>
            <button className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50" onClick={() => setScreen(asset ? { type: 'detail' } : { type: 'list' })} type="button">Cancel</button>
          </div>
        </form>
      )}

      {screen.type === 'maintenance-list' && (
        <MaintenanceLogManager
          onOpenAsset={showAsset}
          user={user}
        />
      )}

      {screen.type === 'users' && isAdmin && (
        <MaintenanceUserManagement currentUser={user} onCreateUser={onCreateUser} />
      )}

      {screen.type === 'detail' && asset && (
        <>
          <div aria-label="Asset detail sections" className="mt-6 flex gap-2 border-b border-slate-200" role="tablist">
            {[
              ['overview', 'Overview'],
              ['maintenance', 'Maintenance'],
            ].map(([tab, label]) => (
              <button
                aria-selected={detailTab === tab}
                className={`border-b-2 px-4 py-3 text-sm font-semibold transition ${
                  detailTab === tab
                    ? 'border-emerald-800 text-emerald-900'
                    : 'border-transparent text-slate-500 hover:text-slate-800'
                }`}
                id={`asset-tab-${tab}`}
                key={tab}
                onClick={() => setDetailTab(tab)}
                role="tab"
                type="button"
              >
                {label}
              </button>
            ))}
          </div>
          <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
          <div>
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-emerald-800">{asset.asset_tag}</p>
                <h3 className="mt-1 text-2xl font-semibold text-slate-900">{asset.asset_name}</h3>
                <p className="mt-1 text-sm text-slate-500">{asset.brand} {asset.model} {asset.serial_number && `· ${asset.serial_number}`}</p>
              </div>
              <div className="flex flex-wrap gap-2">
                <Badge>{asset.condition}</Badge>
                <Badge>{asset.status}</Badge>
              </div>
            </div>
            {detailTab === 'overview' && (
            <>
            <dl className="mt-5 grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-2">
              {[
                ['Category', asset.category?.name],
                ['Location', asset.location?.name],
                ['Room / area', asset.room_area || '—'],
                ['Purchase date', asset.purchase_date],
                ['Acquisition value', Number(asset.purchase_cost).toLocaleString(undefined, { style: 'currency', currency: 'PHP' })],
                ['Description', asset.description],
                ['Remarks', asset.remarks || '—'],
              ].map(([label, value]) => (
                <div className="bg-white p-4" key={label}>
                  <dt className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</dt>
                  <dd className="mt-1 break-words text-sm text-slate-800">{value}</dd>
                </div>
              ))}
            </dl>
            <section className="mt-7">
              <h3 className="text-lg font-semibold text-slate-900">Activity timeline</h3>
              {asset.activity_logs?.length ? (
                <ol className="mt-4 space-y-4 border-l border-slate-200 pl-5">
                  {asset.activity_logs.map((item) => (
                    <li className="relative" key={item.id}>
                      <span className="absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full bg-emerald-700 ring-4 ring-white" />
                      <p className="text-sm font-semibold text-slate-800">{item.description}</p>
                      <p className="mt-1 text-xs text-slate-500">
                        {item.user?.name} · {new Date(item.created_at).toLocaleString()}
                      </p>
                    </li>
                  ))}
                </ol>
              ) : <p className="mt-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">No activity has been recorded.</p>}
            </section>
            </>
            )}
            {detailTab === 'maintenance' && (
            <MaintenanceLogManager asset={asset} user={user} />
            )}
          </div>
          <aside className="space-y-4">
            {isAdmin && (
              <div className="flex flex-wrap gap-2">
                <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900" onClick={() => beginEdit(asset)} type="button">Edit asset</button>
                <button className="rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50" disabled={saving} onClick={removeAsset} type="button">Delete asset</button>
              </div>
            )}
            {!isAdmin && asset.status === 'Under Maintenance' && (
              <form className="rounded-xl border border-slate-200 bg-slate-50 p-4" onSubmit={saveCondition}>
                <h3 className="font-semibold text-slate-900">Update condition</h3>
                <label className="mt-3 block text-sm font-semibold text-slate-700">
                  Condition
                  <select className={inputClass} defaultValue={asset.condition} name="condition">
                    {lookups.conditions.map((value) => <option key={value}>{value}</option>)}
                  </select>
                </label>
                <button className="mt-3 w-full rounded-lg bg-sky-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-950 disabled:opacity-50" disabled={saving} type="submit">
                  {saving ? 'Saving...' : 'Save condition'}
                </button>
              </form>
            )}
          </aside>
          </div>
        </>
      )}
    </section>
  );
}

export default AssetManagement;
