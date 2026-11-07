import { useEffect, useState } from 'react';
import { createLocation, getLocations } from '../api';

const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10';

function LocationManagement() {
  const [locations, setLocations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [error, setError] = useState('');
  const [formError, setFormError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => {
    let active = true;
    getLocations()
      .then((response) => {
        if (active) setLocations(response);
      })
      .catch((requestError) => {
        if (active) setError(requestError.message || 'Unable to load locations.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  async function submitLocation(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = Object.fromEntries(new FormData(form));
    setSaving(true);
    setFormError('');
    setError('');
    setNotice('');

    try {
      const response = await createLocation(values);
      setLocations((current) => [...current, response.data].sort((a, b) => a.name.localeCompare(b.name)));
      setNotice(`Location "${response.data.name}" was added.`);
      form.reset();
      setModalOpen(false);
    } catch (requestError) {
      setFormError(requestError.message || 'Unable to add this location.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="mt-8">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-sm font-medium text-emerald-800">Asset directory</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-900">Locations</h1>
          <p className="mt-2 text-sm text-slate-500">Manage buildings and locations used for asset records.</p>
        </div>
        <button
          className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-4 focus:ring-emerald-700/10"
          onClick={() => { setFormError(''); setModalOpen(true); }}
          type="button"
        >
          + Add Location
        </button>
      </div>

      {error && <p className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
      {notice && <p className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{notice}</p>}

      <div className="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        {loading ? (
          <p className="px-5 py-12 text-center text-sm text-slate-500" role="status">Loading locations...</p>
        ) : locations.length === 0 ? (
          <p className="px-5 py-12 text-center text-sm text-slate-500">No locations yet. Add a location to get started.</p>
        ) : (
          <table className="w-full min-w-[560px] divide-y divide-slate-200 text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-5 py-3 font-semibold">Location</th>
                <th className="px-5 py-3 font-semibold">Building</th>
                <th className="px-5 py-3 text-right font-semibold">Assets assigned</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {locations.map((location) => (
                <tr key={location.id}>
                  <td className="px-5 py-3.5 font-medium text-slate-900">{location.name}</td>
                  <td className="px-5 py-3.5 text-slate-700">{location.building || '—'}</td>
                  <td className="px-5 py-3.5 text-right text-slate-700">{location.assets_count ?? 0}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {modalOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget && !saving) setModalOpen(false);
          }}
        >
          <section aria-labelledby="location-dialog-title" aria-modal="true" className="my-auto w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl sm:p-8" role="dialog">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h2 className="text-xl font-semibold text-slate-900" id="location-dialog-title">Add Location</h2>
                <p className="mt-1 text-sm text-slate-500">Add a location under a building.</p>
              </div>
              <button aria-label="Close dialog" className="rounded-lg px-2 py-1 text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700" disabled={saving} onClick={() => setModalOpen(false)} type="button">×</button>
            </div>

            {formError && <p className="mt-5 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800" role="alert">{formError}</p>}
            <form className="mt-5 space-y-4" onSubmit={submitLocation}>
              <label className="block text-sm font-semibold text-slate-700">
                Location
                <input autoFocus className={inputClass} maxLength={255} name="name" placeholder="e.g. Computer Lab" required />
              </label>
              <label className="block text-sm font-semibold text-slate-700">
                Building
                <input className={inputClass} maxLength={255} name="building" placeholder="e.g. Admin Building" required />
              </label>
              <div className="flex justify-end gap-2 pt-2">
                <button className="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50" disabled={saving} onClick={() => setModalOpen(false)} type="button">Cancel</button>
                <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-50" disabled={saving} type="submit">{saving ? 'Saving...' : 'Save Location'}</button>
              </div>
            </form>
          </section>
        </div>
      )}
    </section>
  );
}

export default LocationManagement;
