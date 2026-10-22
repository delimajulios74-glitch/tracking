import { useEffect, useState } from 'react';
import { getUsers, updateManagedUser } from '../api';

const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal text-slate-800 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10';

function MaintenanceUserManagement({ currentUser, onCreateUser }) {
  const [users, setUsers] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [page, setPage] = useState(1);
  const [modalOpen, setModalOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  async function loadUsers(nextPage = page) {
    try {
      const response = await getUsers(nextPage);
      setUsers(response.data);
      setMeta(response.meta);
      setPage(response.meta.current_page);
    } catch (err) {
      setError(err.message || 'Unable to load users.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let isMounted = true;

    async function fetchUsers() {
      try {
        const response = await getUsers(page);
        if (!isMounted) return;
        setUsers(response.data);
        setMeta(response.meta);
      } catch (err) {
        if (isMounted) setError(err.message || 'Unable to load users.');
      } finally {
        if (isMounted) setLoading(false);
      }
    }

    fetchUsers();
    return () => {
      isMounted = false;
    };
  }, [page]);

  async function submitAccount(event) {
    setCreating(true);
    try {
      const created = await onCreateUser(event);
      if (created) {
        setModalOpen(false);
        setLoading(true);
        setError('');
        await loadUsers(1);
      }
    } finally {
      setCreating(false);
    }
  }

  async function saveUser(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    setLoading(true);
    const values = Object.fromEntries(new FormData(event.currentTarget));
    try {
      await updateManagedUser(editingUser.id, values);
      setEditingUser(null);
      await loadUsers(page);
    } catch (err) {
      setError(err.message || 'Unable to update this user.');
    } finally {
      setSaving(false);
    }
  }

  async function toggleActive(user) {
    const nextActive = !user.is_active;
    const action = nextActive ? 'reactivate' : 'deactivate';
    if (!window.confirm(`Are you sure you want to ${action} ${user.name}'s account?`)) return;

    setError('');
    setLoading(true);
    try {
      await updateManagedUser(user.id, { is_active: nextActive });
      await loadUsers(page);
    } catch (err) {
      setError(err.message || `Unable to ${action} this account.`);
    }
  }

  return (
    <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.15em] text-emerald-800">User management</p>
          <h3 className="mt-2 text-2xl font-semibold text-slate-900">System users</h3>
          <p className="mt-2 max-w-xl text-sm leading-6 text-slate-500">
            Review roles and account status. Deactivate accounts to remove access while keeping their history.
          </p>
        </div>
        <button
          className="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-900"
          onClick={() => setModalOpen(true)}
          type="button"
        >
          Create maintenance account
        </button>
      </div>

      {error && <p className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}

      {loading ? (
        <p className="py-12 text-center text-sm text-slate-500" role="status">Loading users...</p>
      ) : users.length === 0 ? (
        <p className="mt-5 rounded-xl border border-dashed border-slate-300 px-5 py-10 text-center text-sm text-slate-500">No users found.</p>
      ) : (
        <div className="mt-5 overflow-x-auto rounded-xl border border-slate-200">
          <table className="w-full min-w-[720px] divide-y divide-slate-200 text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-4 py-3 font-semibold">User</th>
                <th className="px-4 py-3 font-semibold">Role</th>
                <th className="px-4 py-3 font-semibold">Status</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {users.map((item) => (
                <tr key={item.id}>
                  <td className="px-4 py-3">
                    <p className="font-semibold text-slate-900">{item.name}</p>
                    <p className="mt-0.5 text-xs text-slate-500">@{item.username}</p>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                      item.role === 'admin' ? 'bg-emerald-50 text-emerald-800' : 'bg-sky-50 text-sky-800'
                    }`}>
                      {item.role === 'admin' ? 'Property Custodian' : 'Maintenance'}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                      item.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'
                    }`}>
                      {item.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td className="whitespace-nowrap px-4 py-3 text-right">
                    <button
                      className="font-semibold text-emerald-800 hover:text-emerald-950"
                      onClick={() => setEditingUser(item)}
                      type="button"
                    >
                      Edit
                    </button>
                    {currentUser.id !== item.id && (
                      <button
                        className={`ml-4 font-semibold ${item.is_active ? 'text-rose-700 hover:text-rose-900' : 'text-sky-800 hover:text-sky-950'}`}
                        onClick={() => toggleActive(item)}
                        type="button"
                      >
                        {item.is_active ? 'Deactivate' : 'Reactivate'}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {!loading && meta.total > 0 && (
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
          <span>Page {meta.current_page} of {meta.last_page} · {meta.total} total users</span>
          <div className="flex gap-2">
            <button className="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 disabled:opacity-40" disabled={page <= 1} onClick={() => { setLoading(true); setPage(page - 1); }} type="button">Previous</button>
            <button className="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 disabled:opacity-40" disabled={page >= meta.last_page} onClick={() => { setLoading(true); setPage(page + 1); }} type="button">Next</button>
          </div>
        </div>
      )}

      {(modalOpen || editingUser) && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget && !saving && !creating) {
              setModalOpen(false);
              setEditingUser(null);
            }
          }}
        >
          <section
            aria-labelledby="user-dialog-title"
            aria-modal="true"
            className="my-auto w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl sm:p-8"
            role="dialog"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <h4 className="text-xl font-semibold text-slate-900" id="user-dialog-title">
                  {editingUser ? 'Edit user' : 'Create a maintenance account'}
                </h4>
                <p className="mt-1 text-sm text-slate-500">
                  {editingUser ? 'Update the user details and system role.' : 'New accounts receive the maintenance role automatically.'}
                </p>
              </div>
              <button
                aria-label="Close dialog"
                className="rounded-lg px-2 py-1 text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                disabled={saving || creating}
                onClick={() => { setModalOpen(false); setEditingUser(null); }}
                type="button"
              >
                ×
              </button>
            </div>

            {editingUser ? (
              <form className="mt-6 space-y-4" key={editingUser.id} onSubmit={saveUser}>
                <label className="block text-sm font-semibold text-slate-700">
                  Full name
                  <input className={inputClass} defaultValue={editingUser.name} name="name" required />
                </label>
                <label className="block text-sm font-semibold text-slate-700">
                  Username
                  <input className={inputClass} autoComplete="username" defaultValue={editingUser.username} name="username" required />
                </label>
                <label className="block text-sm font-semibold text-slate-700">
                  Role
                  <select className={inputClass} defaultValue={editingUser.role} name="role" required>
                    <option value="admin">Property Custodian</option>
                    <option value="maintenance">Maintenance</option>
                  </select>
                </label>
                <div className="flex justify-end gap-2 pt-2">
                  <button className="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50" disabled={saving} onClick={() => setEditingUser(null)} type="button">Cancel</button>
                  <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-50" disabled={saving} type="submit">{saving ? 'Saving...' : 'Save changes'}</button>
                </div>
              </form>
            ) : (
              <form className="mt-6 space-y-4" onSubmit={submitAccount}>
                <label className="block text-sm font-semibold text-slate-700">
                  Full name
                  <input className={inputClass} autoComplete="name" name="name" required />
                </label>
                <label className="block text-sm font-semibold text-slate-700">
                  Username
                  <input className={inputClass} autoComplete="username" name="username" required />
                </label>
                <div className="grid gap-4 sm:grid-cols-2">
                  <label className="block text-sm font-semibold text-slate-700">
                    Temporary password
                    <input className={inputClass} autoComplete="new-password" minLength={8} name="password" required type="password" />
                  </label>
                  <label className="block text-sm font-semibold text-slate-700">
                    Confirm password
                    <input className={inputClass} autoComplete="new-password" minLength={8} name="password_confirmation" required type="password" />
                  </label>
                </div>
                <div className="flex justify-end gap-2 pt-2">
                  <button className="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50" disabled={creating} onClick={() => setModalOpen(false)} type="button">Cancel</button>
                  <button className="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-50" disabled={creating} type="submit">{creating ? 'Creating...' : 'Create account'}</button>
                </div>
              </form>
            )}
          </section>
        </div>
      )}
    </section>
  );
}

export default MaintenanceUserManagement;
