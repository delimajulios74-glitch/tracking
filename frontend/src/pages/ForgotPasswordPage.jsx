import { useState } from 'react';
import { ApiError, requestPasswordReset } from '../api';

function ForgotPasswordPage() {
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  async function submitRequest(event) {
    event.preventDefault();
    setSubmitting(true);
    setMessage('');
    setError('');

    const form = new FormData(event.currentTarget);
    try {
      const response = await requestPasswordReset(form.get('email'));
      setMessage(response.message);
    } catch (err) {
      setError(err instanceof ApiError && err.status === 429
        ? 'Too many requests. Please wait a minute and try again.'
        : err.message || 'Unable to request a reset link. Please try again.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-100 px-5 py-10">
      <section className="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-7 shadow-xl sm:p-9">
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-800">Account recovery</p>
        <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Forgot your password?</h1>
        <p className="mt-3 text-sm leading-6 text-slate-600">
          Enter the recovery email address associated with your account.
        </p>

        <form className="mt-6 space-y-4" onSubmit={submitRequest}>
          <label className="block text-sm font-semibold text-slate-700">
            Email address
            <input
              autoComplete="email"
              className="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-normal text-slate-900 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"
              name="email"
              required
              type="email"
            />
          </label>
          <p className="text-xs leading-5 text-slate-500">No email on your account? Contact your administrator.</p>
          <button
            className="flex w-full items-center justify-center rounded-xl bg-emerald-800 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-emerald-900 disabled:cursor-wait disabled:opacity-70"
            disabled={submitting}
            type="submit"
          >
            {submitting ? 'Sending request...' : 'Request reset link'}
          </button>
        </form>

        {error && <p className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
        {message && <p className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{message}</p>}

        <a className="mt-6 inline-block text-sm font-semibold text-emerald-800 hover:text-emerald-950" href="/">
          Back to sign in
        </a>
      </section>
    </main>
  );
}

export default ForgotPasswordPage;
