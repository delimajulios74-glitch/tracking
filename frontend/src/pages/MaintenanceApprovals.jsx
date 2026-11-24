import { useEffect, useState } from 'react';
import {
  approveMaintenanceTask,
  getPendingMaintenanceTasks,
  rejectMaintenanceTask,
} from '../api';

const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10';

function MaintenanceApprovals() {
  const [tasks, setTasks] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [page, setPage] = useState(1);
  const [refreshKey, setRefreshKey] = useState(0);
  const [loading, setLoading] = useState(true);
  const [savingTaskId, setSavingTaskId] = useState(null);
  const [rejectingTask, setRejectingTask] = useState(null);
  const [reason, setReason] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => {
    let active = true;

    getPendingMaintenanceTasks(page)
      .then((response) => {
        if (!active) return;
        setTasks(response.data);
        setMeta(response.meta);
        setError('');
      })
      .catch((err) => {
        if (!active) return;
        setTasks([]);
        setMeta({ current_page: 1, last_page: 1, total: 0 });
        setError(err.message || 'Unable to load pending approvals.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [page, refreshKey]);

  async function approveTask(task) {
    if (!window.confirm(`Approve "${task.title}" and add it to Maintenance Logs?`)) return;

    setSavingTaskId(task.id);
    setError('');
    setNotice('');
    try {
      await approveMaintenanceTask(task.id);
      setNotice(`"${task.title}" was approved and added to Maintenance Logs.`);
      setLoading(true);
      if (tasks.length === 1 && page > 1) {
        setPage((current) => current - 1);
      } else {
        setRefreshKey((current) => current + 1);
      }
    } catch (err) {
      setError(err.message || 'Unable to approve this maintenance task.');
    } finally {
      setSavingTaskId(null);
    }
  }

  function openReject(task) {
    setRejectingTask(task);
    setReason('');
    setFieldErrors({});
    setError('');
  }

  async function rejectTaskSubmit(event) {
    event.preventDefault();
    if (!rejectingTask) return;

    setSavingTaskId(rejectingTask.id);
    setError('');
    setFieldErrors({});
    try {
      await rejectMaintenanceTask(rejectingTask.id, reason.trim());
      setNotice(`"${rejectingTask.title}" was returned to the maintenance user.`);
      setRejectingTask(null);
      setLoading(true);
      if (tasks.length === 1 && page > 1) {
        setPage((current) => current - 1);
      } else {
        setRefreshKey((current) => current + 1);
      }
    } catch (err) {
      setError(err.message || 'Unable to reject this maintenance task.');
      setFieldErrors(err.errors || {});
    } finally {
      setSavingTaskId(null);
    }
  }

  return (
    <section className="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.15em] text-emerald-800">Maintenance review</p>
          <h2 className="mt-2 text-2xl font-semibold text-slate-900">Pending Approvals</h2>
          <p className="mt-1 text-sm text-slate-500">{meta.total} task{meta.total === 1 ? '' : 's'} awaiting review</p>
        </div>
        <button
          className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
          disabled={loading}
          onClick={() => {
            setLoading(true);
            setRefreshKey((current) => current + 1);
          }}
          type="button"
        >
          Refresh
        </button>
      </div>

      {error && <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
      {notice && <p className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{notice}</p>}

      {loading ? (
        <p className="py-10 text-center text-sm text-slate-500" role="status">Loading pending approvals...</p>
      ) : tasks.length === 0 ? (
        <p className="mt-5 rounded-xl border border-dashed border-slate-300 px-5 py-10 text-center text-sm text-slate-500">
          No maintenance tasks are waiting for approval.
        </p>
      ) : (
        <div className="mt-5 overflow-x-auto rounded-xl border border-slate-200">
          <table className="w-full min-w-[980px] divide-y divide-slate-200 text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3 font-semibold">Task / asset</th>
                <th className="px-4 py-3 font-semibold">Submitted by / date</th>
                <th className="px-4 py-3 font-semibold">Completion report</th>
                <th className="px-4 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {tasks.map((task) => (
                <tr key={task.id}>
                  <td className="px-4 py-4 align-top">
                    <p className="font-semibold text-slate-900">{task.title}</p>
                    <p className="mt-1 text-slate-700">{task.asset?.asset_name ?? 'Asset unavailable'}</p>
                    <p className="text-xs text-slate-500">{task.asset?.asset_tag ?? '—'}</p>
                  </td>
                  <td className="px-4 py-4 align-top text-slate-700">
                    <p>{task.assignee?.name ?? 'Unknown user'}</p>
                    <p className="mt-1 text-xs text-slate-500">
                      {task.submitted_at ? new Date(task.submitted_at).toLocaleString() : '—'}
                    </p>
                  </td>
                  <td className="max-w-lg px-4 py-4 align-top">
                    <p className="font-medium text-slate-800">Problem</p>
                    <p className="whitespace-pre-wrap text-slate-600">{task.problem || '—'}</p>
                    <p className="mt-2 font-medium text-slate-800">Completion notes</p>
                    <p className="whitespace-pre-wrap text-slate-600">{task.completion_notes || '—'}</p>
                    {task.maintenance_performed && (
                      <>
                        <p className="mt-2 font-medium text-slate-800">Work performed</p>
                        <p className="whitespace-pre-wrap text-slate-600">{task.maintenance_performed}</p>
                      </>
                    )}
                    {task.parts_replaced && (
                      <p className="mt-2 text-slate-600">Parts replaced: {task.parts_replaced}</p>
                    )}
                  </td>
                  <td className="whitespace-nowrap px-4 py-4 align-top">
                    <button
                      className="rounded-lg bg-emerald-800 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-900 disabled:opacity-50"
                      disabled={savingTaskId === task.id}
                      onClick={() => approveTask(task)}
                      type="button"
                    >
                      {savingTaskId === task.id ? 'Saving...' : 'Approve'}
                    </button>
                    <button
                      className="ml-2 rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                      disabled={savingTaskId === task.id}
                      onClick={() => openReject(task)}
                      type="button"
                    >
                      Reject
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {!loading && meta.total > 0 && (
        <div className="mt-4 flex items-center justify-between text-sm text-slate-500">
          <span>Page {meta.current_page} of {meta.last_page}</span>
          <div className="flex gap-2">
            <button
              className="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 disabled:opacity-40"
              disabled={page <= 1}
              onClick={() => {
                setLoading(true);
                setPage((current) => current - 1);
              }}
              type="button"
            >
              Previous
            </button>
            <button
              className="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 disabled:opacity-40"
              disabled={page >= meta.last_page}
              onClick={() => {
                setLoading(true);
                setPage((current) => current + 1);
              }}
              type="button"
            >
              Next
            </button>
          </div>
        </div>
      )}

      {rejectingTask && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
          <form
            aria-labelledby="reject-task-title"
            className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl"
            onSubmit={rejectTaskSubmit}
          >
            <h3 className="text-lg font-semibold text-slate-900" id="reject-task-title">Reject maintenance task</h3>
            <p className="mt-1 text-sm text-slate-600">{rejectingTask.title}</p>
            <label className="mt-5 block text-sm font-semibold text-slate-700">
              Reason for rejection
              <textarea
                autoFocus
                className={inputClass}
                maxLength={1000}
                minLength={5}
                onChange={(event) => setReason(event.target.value)}
                required
                rows={4}
                value={reason}
              />
            </label>
            {fieldErrors.reason?.[0] && (
              <p className="mt-1 text-xs font-medium text-rose-700">{fieldErrors.reason[0]}</p>
            )}
            {error && <p className="mt-3 text-sm text-rose-700" role="alert">{error}</p>}
            <div className="mt-5 flex justify-end gap-2">
              <button
                className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700"
                disabled={savingTaskId === rejectingTask.id}
                onClick={() => setRejectingTask(null)}
                type="button"
              >
                Cancel
              </button>
              <button
                className="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                disabled={savingTaskId === rejectingTask.id || reason.trim().length < 5}
                type="submit"
              >
                {savingTaskId === rejectingTask.id ? 'Rejecting...' : 'Reject task'}
              </button>
            </div>
          </form>
        </div>
      )}
    </section>
  );
}

export default MaintenanceApprovals;
