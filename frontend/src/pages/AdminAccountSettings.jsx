import { useEffect, useState } from 'react';
import { changePassword, getAccount, updateAccount } from '../services/endUserApi';

const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10';

function AdminAccountSettings({ onAccountUpdate }) {
  const [account, setAccount] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => {
    let active = true;
    getAccount()
      .then((response) => {
        if (active) setAccount(response.data);
      })
      .catch((requestError) => {
        if (active) setError(requestError.message || 'Unable to load your account.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  async function saveProfile(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    setNotice('');

    try {
      const response = await updateAccount({ name: new FormData(event.currentTarget).get('name') });
      setAccount(response.data);
      onAccountUpdate(response.data);
      setNotice('Your profile has been updated.');
    } catch (requestError) {
      setError(requestError.message || 'Unable to update your profile.');
    } finally {
      setSaving(false);
    }
  }

  async function savePassword(event) {
    event.preventDefault();
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    setSaving(true);
    setError('');
    setNotice('');

    try {
      await changePassword({
        current_password: form.get('current_password'),
        password: form.get('password'),
        password_confirmation: form.get('password_confirmation'),
      });
      formElement.reset();
      setNotice('Your password has been changed.');
    } catch (requestError) {
      setError(requestError.message || 'Unable to change your password.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="max-w-3xl">
      <div className="mb-6">
        <p className="text-xs font-bold uppercase tracking-[0.15em] text-emerald-800">Profile settings</p>
        <h1 className="mt-2 text-3xl font-semibold tracking-tight">My Account</h1>
        <p className="mt-2 text-sm text-slate-500">Update your name or change your password.</p>
      </div>
      {error && <p className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
      {notice && <p className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{notice}</p>}
      {loading ? <p className="py-12 text-center text-sm text-slate-500" role="status">Loading account...</p> : account && (
        <div className="space-y-6">
          <form className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" onSubmit={saveProfile}>
            <h2 className="text-lg font-semibold">Personal information</h2>
            <label className="block text-sm font-semibold text-slate-700">
              Full name
              <input className={inputClass} defaultValue={account.name} name="name" required />
            </label>
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <p className="text-sm font-semibold text-slate-700">Username</p>
                <p className="mt-2 text-sm text-slate-500">@{account.username}</p>
              </div>
              <div>
                <p className="text-sm font-semibold text-slate-700">Role</p>
                <p className="mt-2 text-sm text-slate-500">Administrator</p>
              </div>
            </div>
            <div className="flex justify-end">
              <button className="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-50" disabled={saving} type="submit">
                {saving ? 'Saving...' : 'Save changes'}
              </button>
            </div>
          </form>

          <form className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" onSubmit={savePassword}>
            <h2 className="text-lg font-semibold">Change password</h2>
            <label className="block text-sm font-semibold text-slate-700">
              Current password
              <input autoComplete="current-password" className={inputClass} name="current_password" required type="password" />
            </label>
            <label className="block text-sm font-semibold text-slate-700">
              New password
              <input autoComplete="new-password" className={inputClass} minLength={8} name="password" required type="password" />
            </label>
            <label className="block text-sm font-semibold text-slate-700">
              Confirm new password
              <input autoComplete="new-password" className={inputClass} minLength={8} name="password_confirmation" required type="password" />
            </label>
            <div className="flex justify-end">
              <button className="rounded-xl border border-emerald-800 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-50 disabled:opacity-50" disabled={saving} type="submit">
                {saving ? 'Saving...' : 'Change password'}
              </button>
            </div>
          </form>
        </div>
      )}
    </section>
  );
}

export default AdminAccountSettings;
