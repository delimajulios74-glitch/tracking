import { useEffect, useState } from 'react';
import {
  createMaintenanceTask,
  getAssets,
  getMaintenanceTask,
  getMaintenanceTasks,
  getMaintenanceUsers,
  updateMaintenanceTask,
} from '../api';

const EMPTY_FILTERS = {
  q: '',
  status: '',
  priority: '',
  assigned_to: '',
  due_date_from: '',
  due_date_to: '',
};
const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];
const STATUSES = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
const MAINTENANCE_STATUSES = ['In Progress', 'Completed'];
const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal text-slate-800 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10';

function badgeClass(value) {
  if (value === 'Urgent' || value === 'Cancelled') return 'bg-rose-50 text-rose-800';
  if (value === 'High' || value === 'In Progress') return 'bg-amber-50 text-amber-800';
  if (value === 'Completed' || value === 'Low') return 'bg-emerald-50 text-emerald-800';
  return 'bg-slate-100 text-slate-700';
}

function MaintenanceTaskManager({ assetId, onAssetUpdated, user }) {
  const isAdmin = user.role === 'admin';
  const [tasks, setTasks] = useState([]);
  const [assets, setAssets] = useState([]);
  const [assignees, setAssignees] = useState([]);
  const [selectedTask, setSelectedTask] = useState(null);
  const [mode, setMode] = useState('list');
  const [filters, setFilters] = useState({ ...EMPTY_FILTERS, ...(assetId ? { asset_id: assetId } : {}) });
  const [appliedFilters, setAppliedFilters] = useState({ ...EMPTY_FILTERS, ...(assetId ? { asset_id: assetId } : {}) });
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [formKey, setFormKey] = useState(0);
  const [assetSelection, setAssetSelection] = useState(assetId ?? '');

  useEffect(() => {
    let active = true;
    getMaintenanceTasks({ ...appliedFilters, page, per_page: assetId ? 5 : 15 })
      .then((response) => {
        if (!active) return;
        setTasks(response.data);
        setMeta(response.meta);
      })
      .catch((err) => {
        if (active) setError(err.message || 'Unable to load maintenance tasks.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [appliedFilters, assetId, page, formKey]);

  useEffect(() => {
    if (!isAdmin) return undefined;
    let active = true;
    getMaintenanceUsers()
      .then((response) => {
        if (active) setAssignees(response.data.filter((item) => item.is_active));
      })
      .catch((err) => {
        if (active) setError(err.message || 'Unable to load maintenance users.');
      });

    return () => {
      active = false;
    };
  }, [isAdmin]);

  useEffect(() => {
    if (!isAdmin || mode !== 'form') return undefined;
    let active = true;

    getAssets({ per_page: 100, sort_by: 'asset_tag', sort_direction: 'asc' })
      .then((assetResponse) => {
        if (!active) return;
        const sortedAssets = [...assetResponse.data].sort((a, b) => {
          if (a.status === 'Under Maintenance' && b.status !== 'Under Maintenance') return -1;
          if (a.status !== 'Under Maintenance' && b.status === 'Under Maintenance') return 1;
          return a.asset_tag.localeCompare(b.asset_tag);
        });
        setAssets(sortedAssets.filter((item) => item.status !== 'Disposed'));
      })
      .catch((err) => {
        if (active) setError(err.message || 'Unable to load assets for task assignment.');
      });

    return () => {
      active = false;
    };
  }, [isAdmin, mode]);

  async function openTask(id) {
    setLoading(true);
    setError('');
    try {
      const response = await getMaintenanceTask(id);
      setSelectedTask(response.data);
      setAssetSelection(response.data.asset_id);
      setMode('detail');
    } catch (err) {
      setError(err.message || 'Unable to load this maintenance task.');
    } finally {
      setLoading(false);
    }
  }

  function beginCreate() {
    setSelectedTask(null);
    setAssetSelection(assetId ?? '');
    setFieldErrors({});
    setError('');
    setMode('form');
  }

  function beginEdit() {
    setAssetSelection(selectedTask?.asset_id ?? assetId ?? '');
    setFieldErrors({});
    setError('');
    setMode('form');
  }

  function returnToList() {
    setMode('list');
    setSelectedTask(null);
    setError('');
  }

  async function saveTask(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    setFieldErrors({});
    const form = event.currentTarget;
    const values = Object.fromEntries(new FormData(form));
    ['asset_id', 'assigned_to'].forEach((key) => {
      if (values[key] !== '') values[key] = Number(values[key]);
    });
    ['instructions', 'due_date', 'remarks'].forEach((key) => {
      if (values[key] === '') values[key] = null;
    });
    if (assetId && !selectedTask) values.asset_id = Number(assetId);

    try {
      if (selectedTask) {
        await updateMaintenanceTask(selectedTask.id, values);
        const response = await getMaintenanceTask(selectedTask.id);
        setSelectedTask(response.data);
        setMode('detail');
      } else {
        const response = await createMaintenanceTask(values);
        onAssetUpdated?.(response.data.asset);
        const taskResponse = await getMaintenanceTask(response.data.id);
        setSelectedTask(taskResponse.data);
        setMode('detail');
      }
      setFormKey((current) => current + 1);
    } catch (err) {
      setError(err.message || 'Unable to save this task.');
      setFieldErrors(err.errors || {});
    } finally {
      setSaving(false);
    }
  }

  async function cancelTask() {
    if (!selectedTask || !window.confirm(`Cancel task "${selectedTask.title}"?`)) return;
    setSaving(true);
    setError('');
    try {
      await updateMaintenanceTask(selectedTask.id, { status: 'Cancelled' });
      const response = await getMaintenanceTask(selectedTask.id);
      setSelectedTask(response.data);
      setFormKey((current) => current + 1);
    } catch (err) {
      setError(err.message || 'Unable to cancel this task.');
    } finally {
      setSaving(false);
    }
  }

  function applyFilters(event) {
    event.preventDefault();
    setLoading(true);
    setPage(1);
    setAppliedFilters(filters);
  }

  function resetFilters() {
    const reset = { ...EMPTY_FILTERS, ...(assetId ? { asset_id: assetId } : {}) };
    setLoading(true);
    setFilters(reset);
    setAppliedFilters(reset);
    setPage(1);
  }

  function fieldError(name) {
    return fieldErrors[name]?.[0]
      ? <span className="mt-1 block text-xs font-medium text-rose-700">{fieldErrors[name][0]}</span>
      : null;
  }

  function field(label, name, options = {}) {
    return (
      <label className={`block text-sm font-semibold text-slate-700 ${options.wide ? 'sm:col-span-2' : ''}`} key={name}>
        {label}{options.required && ' *'}
        {options.options ? (
          <select
            className={inputClass}
            defaultValue={options.value ?? ''}
            disabled={options.disabled}
            name={name}
            onChange={options.onChange}
            required={options.required}
          >
            <option value="">{options.placeholder || `Select ${label.toLowerCase()}`}</option>
            {options.options.map((option) => (
              <option key={option.value ?? option.id} value={option.value ?? option.id}>
                {option.label ?? option.name ?? option.value ?? option}
              </option>
            ))}
          </select>
        ) : options.textarea ? (
          <textarea
            className={inputClass}
            defaultValue={options.value ?? ''}
            name={name}
            placeholder={options.placeholder}
            required={options.required}
            rows={options.rows ?? 3}
          />
        ) : (
          <input
            className={inputClass}
            defaultValue={options.value ?? ''}
            maxLength={options.maxLength}
            name={name}
            required={options.required}
            type={options.type ?? 'text'}
          />
        )}
        {fieldError(name)}
      </label>
    );
  }

  const title = assetId
    ? 'Maintenance Tasks'
    : isAdmin
      ? 'Maintenance Tasks'
      : 'My Tasks';

  if (mode === 'list') {
    return (
      <section className={assetId ? 'mt-7' : ''}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
            {!assetId && <p className="mt-1 text-sm text-slate-500">{meta.total} task{meta.total === 1 ? '' : 's'}</p>}
          </div>
          {isAdmin && (
            <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900" onClick={beginCreate} type="button">
              Assign Task
            </button>
          )}
        </div>

        {!assetId && (
          <form className="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4" onSubmit={applyFilters}>
            <label className="text-sm font-semibold text-slate-700 sm:col-span-2">
              Search tasks
              <input className={inputClass} onChange={(event) => setFilters({ ...filters, q: event.target.value })} placeholder="Title, asset tag or name" value={filters.q} />
            </label>
            {field('Status', 'status', { options: STATUSES, value: filters.status })}
            {field('Priority', 'priority', { options: PRIORITIES, value: filters.priority })}
            {isAdmin && field('Assigned to', 'assigned_to', {
              options: assignees,
              value: filters.assigned_to,
              placeholder: 'All maintenance users',
            })}
            {field('Due from', 'due_date_from', { type: 'date', value: filters.due_date_from })}
            {field('Due to', 'due_date_to', { type: 'date', value: filters.due_date_to })}
            <div className="flex items-end gap-2">
              <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900" type="submit">Apply filters</button>
              <button className="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-white" onClick={resetFilters} type="button">Reset</button>
            </div>
          </form>
        )}

        {error && <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
        {loading ? (
          <p className="py-8 text-center text-sm text-slate-500" role="status">Loading maintenance tasks...</p>
        ) : tasks.length === 0 ? (
          <p className="mt-4 rounded-xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500">No maintenance tasks found.</p>
        ) : (
          <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200">
            <table className="w-full min-w-[760px] divide-y divide-slate-200 text-left text-sm">
              <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-3 font-semibold">Asset</th>
                  <th className="px-4 py-3 font-semibold">Task</th>
                  {!assetId && <th className="px-4 py-3 font-semibold">Assigned to</th>}
                  <th className="px-4 py-3 font-semibold">Priority</th>
                  <th className="px-4 py-3 font-semibold">Due date</th>
                  <th className="px-4 py-3 font-semibold">Status</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {tasks.map((task) => (
                  <tr className="hover:bg-slate-50/70" key={task.id}>
                    <td className="px-4 py-3">
                      <p className="font-semibold text-slate-900">{task.asset?.asset_name ?? 'Asset unavailable'}</p>
                      <p className="text-xs text-slate-500">{task.asset?.asset_tag ?? '—'}</p>
                    </td>
                    <td className="px-4 py-3 font-medium text-slate-800">{task.title}</td>
                    {!assetId && <td className="px-4 py-3 text-slate-700">{task.assignee?.name ?? '—'}</td>}
                    <td className="px-4 py-3"><span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${badgeClass(task.priority)}`}>{task.priority}</span></td>
                    <td className="px-4 py-3 text-slate-700">{task.due_date ?? '—'}</td>
                    <td className="px-4 py-3"><span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${badgeClass(task.status)}`}>{task.status}</span></td>
                    <td className="px-4 py-3 text-right">
                      <button className="font-semibold text-emerald-800 hover:text-emerald-950" onClick={() => openTask(task.id)} type="button">View</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {!loading && !assetId && meta.total > 0 && (
          <div className="mt-4 flex items-center justify-between text-sm text-slate-500">
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

  if (mode === 'form') {
    const task = selectedTask;
    const chosenAsset = task?.asset ?? assets.find((item) => item.id === Number(assetSelection));
    const shouldNotify = Boolean(chosenAsset && chosenAsset.status !== 'Under Maintenance');

    return (
      <section className="mt-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h3 className="text-xl font-semibold text-slate-900">{task ? 'Edit Maintenance Task' : 'Assign Maintenance Task'}</h3>
            <p className="mt-1 text-sm text-slate-500">Dashboard / Assets / Maintenance Tasks / {task ? 'Edit Task' : 'Assign Task'}</p>
          </div>
          <button className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" onClick={task ? beginEdit : returnToList} type="button">
            Back
          </button>
        </div>
        {shouldNotify && (
          <p className="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
            This asset is not currently marked Under Maintenance. It will be updated when the task is assigned.
          </p>
        )}
        {error && <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
        <form className="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2" key={`${task?.id ?? 'new'}-${formKey}`} onSubmit={saveTask}>
          {field('Asset', 'asset_id', {
            options: assets.map((item) => ({
              id: item.id,
              name: `${item.asset_tag} · ${item.asset_name}${item.status === 'Under Maintenance' ? ' · Under Maintenance' : ''}`,
            })),
            value: task?.asset_id ?? assetId ?? '',
            required: true,
            disabled: Boolean(assetId),
            onChange: (event) => setAssetSelection(event.target.value),
          })}
          {field('Maintenance User', 'assigned_to', {
            options: assignees,
            value: task?.assigned_to ?? '',
            required: true,
          })}
          {field('Title', 'title', { value: task?.title, required: true, maxLength: 255 })}
          {field('Priority', 'priority', { options: PRIORITIES, value: task?.priority ?? 'Medium', required: true })}
          {field('Due Date', 'due_date', { type: 'date', value: task?.due_date })}
          {field('Instructions', 'instructions', {
            textarea: true,
            rows: 4,
            value: task?.instructions,
            wide: true,
          })}
          {isAdmin && task && (
            <>
              {field('Status', 'status', { options: STATUSES, value: task.status, required: true })}
              {field('Problem Found', 'problem', { textarea: true, rows: 3, value: task.problem, wide: true })}
              {field('Remarks', 'remarks', { textarea: true, rows: 3, value: task.remarks, wide: true })}
            </>
          )}
          <div className="flex justify-end gap-2 sm:col-span-2">
            <button className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" onClick={returnToList} type="button">Cancel</button>
            <button className="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50" disabled={saving} type="submit">
              {saving ? 'Saving...' : 'Save Task'}
            </button>
          </div>
        </form>
      </section>
    );
  }

  return (
    <section className={assetId ? 'mt-6' : ''}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-xl font-semibold text-slate-900">{selectedTask?.title}</h3>
          <p className="mt-1 text-sm text-slate-500">Dashboard / Assets / Maintenance Tasks / Task Details</p>
        </div>
        <div className="flex gap-2">
          {isAdmin && (
            <>
              <button className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" onClick={beginEdit} type="button">Edit task</button>
              {selectedTask?.status !== 'Cancelled' && <button className="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50" disabled={saving} onClick={cancelTask} type="button">Cancel task</button>}
            </>
          )}
          <button className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" onClick={returnToList} type="button">
            {assetId ? 'Back to asset tasks' : 'Back to tasks'}
          </button>
        </div>
      </div>
      {error && <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
      {selectedTask && (
        <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
          <div className="space-y-5">
            <section className="rounded-xl border border-slate-200 bg-white p-5">
              <h4 className="font-semibold text-slate-900">Asset and task instructions</h4>
              <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt className="text-xs font-semibold uppercase text-slate-400">Asset</dt><dd className="mt-1 text-sm text-slate-800">{selectedTask.asset?.asset_name} · {selectedTask.asset?.asset_tag}</dd></div>
                <div><dt className="text-xs font-semibold uppercase text-slate-400">Location</dt><dd className="mt-1 text-sm text-slate-800">{selectedTask.asset?.location ?? '—'}</dd></div>
                <div><dt className="text-xs font-semibold uppercase text-slate-400">Assigned to</dt><dd className="mt-1 text-sm text-slate-800">{selectedTask.assignee?.name}</dd></div>
                <div><dt className="text-xs font-semibold uppercase text-slate-400">Priority / due date</dt><dd className="mt-1 text-sm text-slate-800">{selectedTask.priority} · {selectedTask.due_date ?? 'No due date'}</dd></div>
                <div className="sm:col-span-2"><dt className="text-xs font-semibold uppercase text-slate-400">Instructions</dt><dd className="mt-1 whitespace-pre-wrap text-sm text-slate-800">{selectedTask.instructions || 'No instructions provided.'}</dd></div>
              </dl>
            </section>
            {(!isAdmin && !['Completed', 'Cancelled'].includes(selectedTask.status)) ? (
              <form className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2" onSubmit={saveTask}>
                {field('Status', 'status', {
                  options: MAINTENANCE_STATUSES,
                  value: selectedTask.status === 'Pending' ? '' : selectedTask.status,
                  required: true,
                  placeholder: 'Select status',
                })}
                <div />
                {field('Problem Found', 'problem', {
                  textarea: true,
                  rows: 4,
                  value: selectedTask.problem,
                  required: true,
                  wide: true,
                })}
                {field('Remarks', 'remarks', {
                  textarea: true,
                  rows: 3,
                  value: selectedTask.remarks,
                  wide: true,
                })}
                <div className="flex justify-end sm:col-span-2">
                  <button className="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50" disabled={saving} type="submit">{saving ? 'Saving...' : 'Save Update'}</button>
                </div>
              </form>
            ) : (
              <section className="rounded-xl border border-slate-200 bg-white p-5">
                <h4 className="font-semibold text-slate-900">Maintenance report</h4>
                <p className="mt-4 text-xs font-semibold uppercase text-slate-400">Problem found</p>
                <p className="mt-1 whitespace-pre-wrap text-sm text-slate-800">{selectedTask.problem || 'No problem reported yet.'}</p>
                <p className="mt-4 text-xs font-semibold uppercase text-slate-400">Remarks</p>
                <p className="mt-1 whitespace-pre-wrap text-sm text-slate-800">{selectedTask.remarks || '—'}</p>
                {isAdmin && (
                  <button className="mt-5 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" onClick={beginEdit} type="button">Edit / reopen task</button>
                )}
              </section>
            )}
          </div>
          <aside className="space-y-5">
            <section className="rounded-xl border border-slate-200 bg-white p-5">
              <h4 className="font-semibold text-slate-900">Task status</h4>
              <p className="mt-3"><span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${badgeClass(selectedTask.status)}`}>{selectedTask.status}</span></p>
              <dl className="mt-4 space-y-3 text-sm">
                <div><dt className="text-slate-500">Started</dt><dd className="font-medium text-slate-800">{selectedTask.started_at ? new Date(selectedTask.started_at).toLocaleString() : '—'}</dd></div>
                <div><dt className="text-slate-500">Completed</dt><dd className="font-medium text-slate-800">{selectedTask.completed_at ? new Date(selectedTask.completed_at).toLocaleString() : '—'}</dd></div>
              </dl>
            </section>
            {isAdmin && (
              <section className="rounded-xl border border-slate-200 bg-white p-5">
                <h4 className="font-semibold text-slate-900">Activity timeline</h4>
                {selectedTask.activity_logs?.length ? (
                  <ol className="mt-4 space-y-4 border-l border-slate-200 pl-5">
                    {selectedTask.activity_logs.map((entry) => (
                      <li key={entry.id}>
                        <p className="text-sm font-medium text-slate-800">{entry.description}</p>
                        <p className="mt-1 text-xs text-slate-500">{entry.user?.name} · {new Date(entry.created_at).toLocaleString()}</p>
                      </li>
                    ))}
                  </ol>
                ) : <p className="mt-3 text-sm text-slate-500">No task activity yet.</p>}
              </section>
            )}
          </aside>
        </div>
      )}
    </section>
  );
}

export default MaintenanceTaskManager;
