import { useEffect, useState } from 'react';
import {
  createMaintenanceTask,
  deleteAssetRequest,
  getAssets,
  getLocations,
  getAssetRequestPhoto,
  getAssetRequests,
  getMaintenanceUsers,
  updateAssetRequest,
} from '../api';

const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10';
const statusLabels = {
  pending: 'Pending review',
  in_review: 'In review',
  resolved: 'Resolved',
  rejected: 'Rejected',
};

function displayDate(value) {
  return value ? new Date(value).toLocaleString() : '—';
}

function DamageReportsPanel() {
  const [reports, setReports] = useState([]);
  const [users, setUsers] = useState([]);
  const [filterStatus, setFilterStatus] = useState('');
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [selectedReport, setSelectedReport] = useState(null);
  const [editingReport, setEditingReport] = useState(null);
  const [editAssets, setEditAssets] = useState([]);
  const [editLocations, setEditLocations] = useState([]);
  const [editLoading, setEditLoading] = useState(false);
  const [editError, setEditError] = useState('');
  const [reportToDelete, setReportToDelete] = useState(null);
  const [deleteSaving, setDeleteSaving] = useState(false);
  const [deleteError, setDeleteError] = useState('');
  const [formError, setFormError] = useState('');
  const [photoReport, setPhotoReport] = useState(null);
  const [photoPreview, setPhotoPreview] = useState(null);

  useEffect(() => {
    if (!photoReport) return undefined;
    let active = true;
    let objectUrl = '';
    setPhotoPreview({ id: photoReport.id, loading: true });

    getAssetRequestPhoto(photoReport.id)
      .then((blob) => {
        if (!active) return;
        objectUrl = URL.createObjectURL(blob);
        setPhotoPreview({ id: photoReport.id, url: objectUrl });
      })
      .catch((requestError) => {
        if (active) {
          setPhotoPreview({
            id: photoReport.id,
            error: requestError.message || 'Unable to load the damage photo.',
          });
        }
      });

    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [photoReport]);

  async function loadReports(currentPage = page, status = filterStatus) {
    setLoading(true);
    setError('');
    try {
      const response = await getAssetRequests({
        page: currentPage,
        per_page: 15,
        ...(status ? { status } : {}),
      });
      setReports(response.data);
      setMeta(response.meta);
    } catch (requestError) {
      setError(requestError.message || 'Unable to load end-user damage reports.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let active = true;
    Promise.all([getMaintenanceUsers(), getAssetRequests({ page, per_page: 15, ...(filterStatus ? { status: filterStatus } : {}) })])
      .then(([userResponse, reportResponse]) => {
        if (!active) return;
        setUsers(userResponse.data.filter((user) => user.is_active));
        setReports(reportResponse.data);
        setMeta(reportResponse.meta);
        setError('');
      })
      .catch((requestError) => {
        if (active) setError(requestError.message || 'Unable to load reports and maintenance users.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [page, filterStatus]);

  function changeFilter(event) {
    setPage(1);
    setFilterStatus(event.target.value);
  }

  async function openEdit(report) {
    setEditingReport(report);
    setEditLoading(true);
    setEditError('');
    try {
      const [assetResponse, locationResponse] = await Promise.all([
        getAssets({ page: 1, per_page: 100 }),
        getLocations(),
      ]);
      const assetPages = await Promise.all(
        Array.from({ length: Math.max(0, assetResponse.meta.last_page - 1) }, (_, index) =>
          getAssets({ page: index + 2, per_page: 100 })),
      );
      setEditAssets([assetResponse, ...assetPages].flatMap((response) => response.data));
      setEditLocations(locationResponse);
    } catch (requestError) {
      setEditError(requestError.message || 'Unable to load report editing options.');
    } finally {
      setEditLoading(false);
    }
  }

  async function saveReport(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const photo = formData.get('photo');
    if (!photo?.name) formData.delete('photo');

    setSaving(true);
    setEditError('');
    try {
      await updateAssetRequest(editingReport.id, Object.fromEntries(formData));
      setEditingReport(null);
      await loadReports();
    } catch (requestError) {
      setEditError(requestError.message || 'Unable to update this report.');
    } finally {
      setSaving(false);
    }
  }

  async function deleteReport() {
    if (!reportToDelete) return;
    setDeleteSaving(true);
    setDeleteError('');
    try {
      await deleteAssetRequest(reportToDelete.id);
      setReportToDelete(null);
      if (reports.length === 1 && page > 1) setPage((current) => current - 1);
      else await loadReports();
    } catch (requestError) {
      setDeleteError(requestError.message || 'Unable to delete this report.');
    } finally {
      setDeleteSaving(false);
    }
  }

  async function assignReport(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const assignedTo = Number(formData.get('assigned_to'));
    if (!selectedReport || !assignedTo) {
      setFormError('Choose an active maintenance user.');
      return;
    }

    setSaving(true);
    setFormError('');
    try {
      await createMaintenanceTask({
        asset_id: selectedReport.asset.id,
        assigned_to: assignedTo,
        report_reference_no: selectedReport.reference_no,
        title: formData.get('title'),
        priority: formData.get('priority'),
        due_date: formData.get('due_date') || null,
        instructions: formData.get('instructions') || null,
      });
      setSelectedReport(null);
      setPage(1);
      await loadReports(1, filterStatus);
    } catch (requestError) {
      setFormError(requestError.message || 'Unable to forward this report to maintenance.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="text-xl font-semibold text-slate-900">End-user damage reports</h2>
          <p className="mt-1 text-sm text-slate-500">Review reports and forward pending items to maintenance.</p>
        </div>
        <label className="text-sm font-semibold text-slate-700">
          Filter by status
          <select className={inputClass} onChange={changeFilter} value={filterStatus}>
            <option value="">All reports</option>
            {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
        </label>
      </div>

      {error && <p className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
      {loading ? (
        <p className="py-8 text-center text-sm text-slate-500" role="status">Loading damage reports...</p>
      ) : reports.length === 0 ? (
        <p className="mt-4 rounded-xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500">No end-user reports found.</p>
      ) : (
        <div className="mt-5 overflow-x-auto rounded-xl border border-slate-200">
          <table className="w-full min-w-[980px] divide-y divide-slate-200 text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3 font-semibold">Report</th>
                <th className="px-4 py-3 font-semibold">Reported by</th>
                <th className="px-4 py-3 font-semibold">Asset / location</th>
                <th className="px-4 py-3 font-semibold">Problem</th>
                <th className="px-4 py-3 font-semibold">Status</th>
                <th className="px-4 py-3 font-semibold">Maintenance task</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {reports.map((report) => (
                <tr key={report.id} className="align-top hover:bg-slate-50/70">
                  <td className="px-4 py-3">
                    <p className="font-semibold text-slate-900">{report.reference_no}</p>
                    <p className="mt-1 text-xs text-slate-500">{displayDate(report.created_at)}</p>
                  </td>
                  <td className="px-4 py-3 text-slate-700">{report.reporter?.name ?? 'Unknown user'}</td>
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-800">{report.asset?.asset_name ?? 'Asset unavailable'}</p>
                    <p className="text-xs text-slate-500">{report.asset?.asset_tag ?? '—'} · {report.location ?? 'No location'}</p>
                  </td>
                  <td className="max-w-sm whitespace-pre-wrap px-4 py-3 text-slate-700">
                    {report.description}
                    {report.photo_url && (
                      <button
                        className="mt-2 block text-xs font-semibold text-emerald-800 underline"
                        onClick={() => {
                          setPhotoPreview(null);
                          setPhotoReport(report);
                        }}
                        type="button"
                      >
                        View damage photo
                      </button>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-700">
                      {statusLabels[report.status] ?? report.status.replaceAll('_', ' ')}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    {report.maintenance_task ? (
                      <>
                        <p className="font-medium text-slate-800">{report.maintenance_task.title}</p>
                        <p className="mt-1 text-xs text-slate-500">
                          {report.maintenance_task.assigned_to ?? 'Unassigned'} · {report.maintenance_task.status}
                        </p>
                      </>
                    ) : <span className="text-slate-400">Not assigned</span>}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex flex-col items-end gap-2">
                      <button
                        className="whitespace-nowrap rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        onClick={() => openEdit(report)}
                        type="button"
                      >
                        Edit
                      </button>
                      <button
                        className="whitespace-nowrap rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50"
                        onClick={() => { setReportToDelete(report); setDeleteError(''); }}
                        type="button"
                      >
                        Delete
                      </button>
                      {report.status === 'pending' && (
                      <button
                        className="whitespace-nowrap rounded-lg bg-emerald-800 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-900"
                        onClick={() => { setSelectedReport(report); setFormError(''); }}
                        type="button"
                      >
                        Forward to maintenance
                      </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {!loading && meta.total > 0 && (
        <div className="mt-4 flex items-center justify-between text-sm text-slate-500">
          <span>Page {meta.current_page} of {meta.last_page} · {meta.total} report{meta.total === 1 ? '' : 's'}</span>
          <div className="flex gap-2">
            <button className="rounded-lg border border-slate-200 px-3 py-2 font-semibold disabled:opacity-40" disabled={page <= 1} onClick={() => setPage((current) => current - 1)} type="button">Previous</button>
            <button className="rounded-lg border border-slate-200 px-3 py-2 font-semibold disabled:opacity-40" disabled={page >= meta.last_page} onClick={() => setPage((current) => current + 1)} type="button">Next</button>
          </div>
        </div>
      )}

      {selectedReport && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" role="presentation">
          <form className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl" onSubmit={assignReport}>
            <h3 className="text-xl font-semibold text-slate-900">Forward report to maintenance</h3>
            <p className="mt-1 text-sm text-slate-600">{selectedReport.reference_no} · {selectedReport.asset?.asset_tag} · {selectedReport.asset?.asset_name}</p>
            <p className="mt-3 whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{selectedReport.description}</p>
            <label className="mt-4 block text-sm font-semibold text-slate-700">
              Maintenance personnel *
              <select className={inputClass} defaultValue="" name="assigned_to" required>
                <option disabled value="">Select a maintenance user</option>
                {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
              </select>
            </label>
            <label className="mt-4 block text-sm font-semibold text-slate-700">
              Task title *
              <input className={inputClass} defaultValue={`Inspect ${selectedReport.asset?.asset_name ?? 'asset'} - ${selectedReport.reference_no}`} maxLength={255} name="title" required />
            </label>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <label className="block text-sm font-semibold text-slate-700">
                Priority *
                <select className={inputClass} defaultValue="High" name="priority" required>
                  {['Low', 'Medium', 'High', 'Urgent'].map((priority) => <option key={priority} value={priority}>{priority}</option>)}
                </select>
              </label>
              <label className="block text-sm font-semibold text-slate-700">
                Due date
                <input className={inputClass} min={new Date().toISOString().slice(0, 10)} name="due_date" type="date" />
              </label>
            </div>
            <label className="mt-4 block text-sm font-semibold text-slate-700">
              Instructions
              <textarea className={inputClass} defaultValue={`Review end-user report ${selectedReport.reference_no}: ${selectedReport.description}`} name="instructions" rows={3} />
            </label>
            {formError && <p className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{formError}</p>}
            {!users.length && <p className="mt-4 text-sm text-amber-800">No active maintenance users are available to assign.</p>}
            <div className="mt-6 flex justify-end gap-2">
              <button className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" disabled={saving} onClick={() => setSelectedReport(null)} type="button">Cancel</button>
              <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" disabled={saving || !users.length} type="submit">{saving ? 'Forwarding...' : 'Create maintenance task'}</button>
            </div>
          </form>
        </div>
      )}

      {editingReport && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" role="presentation">
          <form className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl" onSubmit={saveReport}>
            <h3 className="text-xl font-semibold text-slate-900">Edit damage report</h3>
            <p className="mt-1 text-sm text-slate-600">{editingReport.reference_no} · {editingReport.reporter?.name ?? 'Unknown user'}</p>
            {editLoading ? <p className="py-8 text-center text-sm text-slate-500" role="status">Loading report options...</p> : (
              <>
                <label className="mt-4 block text-sm font-semibold text-slate-700">
                  Asset
                  <select className={inputClass} defaultValue={editingReport.asset?.id ?? ''} disabled={Boolean(editingReport.maintenance_task)} name="asset_id" required>
                    {editAssets.map((asset) => <option key={asset.id} value={asset.id}>{asset.asset_tag} — {asset.asset_name}</option>)}
                  </select>
                </label>
                {editingReport.maintenance_task && <p className="mt-1 text-xs text-slate-500">The asset cannot be changed after this report has been linked to a maintenance task.</p>}
                <label className="mt-4 block text-sm font-semibold text-slate-700">
                  Location
                  <select className={inputClass} defaultValue={editingReport.location_id ?? editLocations.find((item) => item.name === editingReport.location)?.id ?? ''} name="location_id" required>
                    {editLocations.map((location) => <option key={location.id} value={location.id}>{location.name}</option>)}
                  </select>
                </label>
                <label className="mt-4 block text-sm font-semibold text-slate-700">
                  Description
                  <textarea className={inputClass} defaultValue={editingReport.description} maxLength={5000} name="description" required rows={4} />
                </label>
                <label className="mt-4 block text-sm font-semibold text-slate-700">
                  Status
                  <select className={inputClass} defaultValue={editingReport.status} name="status" required>
                    {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                  </select>
                </label>
                <label className="mt-4 block text-sm font-semibold text-slate-700">
                  Replace photo (JPG or PNG, up to 2 MB)
                  <input accept="image/jpeg,image/png" className={inputClass} name="photo" type="file" />
                </label>
                {editingReport.photo_url && (
                  <label className="mt-3 flex items-center gap-2 text-sm text-slate-700">
                    <input name="remove_photo" type="checkbox" value="1" />
                    Remove current photo
                  </label>
                )}
              </>
            )}
            {editError && <p className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{editError}</p>}
            <div className="mt-6 flex justify-end gap-2">
              <button className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" disabled={saving} onClick={() => setEditingReport(null)} type="button">Cancel</button>
              <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" disabled={saving || editLoading} type="submit">{saving ? 'Saving...' : 'Save changes'}</button>
            </div>
          </form>
        </div>
      )}

      {reportToDelete && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" role="presentation">
          <section aria-labelledby="delete-report-title" aria-modal="true" className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog">
            <h3 className="text-lg font-semibold text-slate-900" id="delete-report-title">Delete damage report?</h3>
            <p className="mt-2 text-sm leading-6 text-slate-600">This permanently deletes report {reportToDelete.reference_no} and its uploaded photo. Any maintenance task and history already created will be kept.</p>
            {deleteError && <p className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{deleteError}</p>}
            <div className="mt-6 flex justify-end gap-2">
              <button className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" disabled={deleteSaving} onClick={() => setReportToDelete(null)} type="button">Cancel</button>
              <button className="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" disabled={deleteSaving} onClick={deleteReport} type="button">{deleteSaving ? 'Deleting...' : 'Delete report'}</button>
            </div>
          </section>
        </div>
      )}

      {photoReport && (
        <div
          className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/75 p-4"
          onClick={() => setPhotoReport(null)}
          role="presentation"
        >
          <section
            aria-labelledby="damage-photo-title"
            aria-modal="true"
            className="w-full max-w-4xl rounded-2xl bg-white p-5 shadow-2xl"
            onClick={(event) => event.stopPropagation()}
            role="dialog"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-slate-900" id="damage-photo-title">Damage photo</h3>
                <p className="mt-1 text-sm text-slate-500">{photoReport.reference_no}</p>
              </div>
              <button
                aria-label="Close damage photo"
                className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                onClick={() => setPhotoReport(null)}
                type="button"
              >
                Close
              </button>
            </div>
            {photoPreview?.id !== photoReport.id || photoPreview.loading ? (
              <p className="py-12 text-center text-sm text-slate-500" role="status">Loading damage photo...</p>
            ) : photoPreview.error ? (
              <p className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{photoPreview.error}</p>
            ) : (
              <div className="mt-4 flex min-h-48 items-center justify-center rounded-xl bg-slate-100 p-3">
                <img
                  alt={`Damage reported in ${photoReport.reference_no}`}
                  className="max-h-[70vh] max-w-full rounded-lg object-contain"
                  onError={() => setPhotoPreview({
                    id: photoReport.id,
                    error: 'The damage photo could not be decoded by the browser.',
                  })}
                  src={photoPreview.url}
                />
              </div>
            )}
          </section>
        </div>
      )}
    </section>
  );
}

export default DamageReportsPanel;
