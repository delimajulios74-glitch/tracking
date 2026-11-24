import { useState } from 'react';
import { ApiError, resetPassword } from '../api';

function ResetPasswordPage({ credentials, onSuccess }) {
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [expired, setExpired] = useState(!credentials?.token || !credentials?.email);

  async function submitReset(event) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setExpired(false);

    const form = new FormData(event.currentTarget);
    try {
      await resetPassword({
        token: credentials.token,
        email: credentials.email,
        password: form.get('password'),
        password_confirmation: form.get('password_confirmation'),
      });
      onSuccess();
    } catch (err) {
      if (err instanceof ApiError && err.status === 429) {
        setError('Too many requests. Please wait a minute and try again.');
      } else if (err instanceof ApiError && err.status === 422 && !Object.keys(err.errors).length) {
        setExpired(true);
      } else {
        setError(err.message || 'Unable to reset your password. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-100 px-5 py-10">
      <section className="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-7 shadow-xl sm:p-9">
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-800">Account recovery</p>
        <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Set a new password</h1>

        {expired ? (
          <div className="mt-5">
            <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-5 text-amber-900" role="alert">
              This reset link is invalid or has expired.
            </p>
            <a className="mt-5 inline-block text-sm font-semibold text-emerald-800 hover:text-emerald-950" href="/forgot-password">
              Request a new reset link
            </a>
          </div>
        ) : (
          <>
            <p className="mt-3 text-sm leading-6 text-slate-600">Choose a password with at least 8 characters.</p>
            <form className="mt-6 space-y-4" onSubmit={submitReset}>
              <label className="block text-sm font-semibold text-slate-700">
                New password
                <input
                  autoComplete="new-password"
                  className="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-normal text-slate-900 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"
                  minLength={8}
                  name="password"
                  required
                  type="password"
                />
              </label>
              <label className="block text-sm font-semibold text-slate-700">
                Confirm new password
                <input
                  autoComplete="new-password"
                  className="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-normal text-slate-900 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"
                  minLength={8}
                  name="password_confirmation"
                  required
                  type="password"
                />
              </label>
              <button
                className="flex w-full items-center justify-center rounded-xl bg-emerald-800 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-emerald-900 disabled:cursor-wait disabled:opacity-70"
                disabled={submitting}
                type="submit"
              >
                {submitting ? 'Updating password...' : 'Reset password'}
              </button>
            </form>

            {error && <p className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
            <a className="mt-6 inline-block text-sm font-semibold text-slate-600 hover:text-slate-900" href="/">
              Back to sign in
            </a>
          </>
        )}
      </section>
    </main>
  );
}

export default ResetPasswordPage;
