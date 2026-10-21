function AdminDashboard({ error, notice, onCreateUser, onLogout, user }) {
  return (
    <main className="min-h-screen bg-slate-100 text-slate-900">
      <div className="flex min-h-screen">
        <aside className="hidden w-64 shrink-0 flex-col bg-emerald-950 px-5 py-7 text-white lg:flex">
          <a className="flex items-center gap-3 px-2" href="/admin">
            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10">
              <svg aria-hidden="true" className="h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path d="M3 9.5 12 4l9 5.5v1.75H3V9.5Zm2 2.5v6m4-6v6m6-6v6m4-6v6M3 20h18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.6" />
              </svg>
            </span>
            <span>
              <span className="block text-xs font-semibold uppercase tracking-[0.16em] text-emerald-200">LGU Trinidad</span>
              <span className="mt-0.5 block text-sm font-semibold">Asset Tracking</span>
            </span>
          </a>

          <p className="mb-3 mt-12 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-300/70">Workspace</p>
          <div className="flex items-center gap-3 rounded-xl bg-white/10 px-3 py-3 text-sm font-semibold">
            <span className="h-2 w-2 rounded-full bg-emerald-300" />
            Admin overview
          </div>
          <div className="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4">
            <p className="text-xs font-semibold text-emerald-200">Administrator access</p>
            <p className="mt-2 text-xs leading-5 text-emerald-50/65">Manage system users and oversee municipal assets.</p>
          </div>
        </aside>

        <div className="flex min-w-0 flex-1 flex-col">
          <header className="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4 sm:px-8">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.13em] text-emerald-800">Property and Equipment Tracking</p>
              <p className="mt-1 text-sm text-slate-500">Administration workspace</p>
            </div>
            <div className="flex items-center gap-3">
              <div className="hidden text-right sm:block">
                <p className="text-sm font-semibold text-slate-800">{user.name}</p>
                <p className="text-xs text-slate-500">Administrator</p>
              </div>
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-900">
                {user.name.slice(0, 1).toUpperCase()}
              </div>
              <button className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-emerald-700/10" onClick={onLogout} type="button">
                Sign out
              </button>
            </div>
          </header>

          <div className="mx-auto w-full max-w-6xl flex-1 px-5 py-8 sm:px-8 sm:py-10">
            {error && <p className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
            {notice && <p className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{notice}</p>}

            <div className="flex flex-wrap items-end justify-between gap-4">
              <div>
                <p className="text-sm font-medium text-emerald-800">Good day, {user.name}</p>
                <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-900">Admin overview</h1>
                <p className="mt-2 text-sm text-slate-500">Manage access and coordinate property tracking.</p>
              </div>
              <span className="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800">ADMINISTRATOR</span>
            </div>

            <section className="mt-8 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.85fr)]">
              <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                <div className="flex items-start gap-4">
                  <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-800">
                    <svg aria-hidden="true" className="h-6 w-6" fill="none" viewBox="0 0 24 24">
                      <path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-5A4.5 4.5 0 0 0 2 18.5V20m18 0v-1.5a4.5 4.5 0 0 0-3-4.243M14 4.243a4.5 4.5 0 0 1 0 8.514M14 4.243a4.5 4.5 0 0 0-5.5 7.01" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
                      <circle cx="9" cy="7.5" r="3.5" stroke="currentColor" strokeWidth="1.7" />
                    </svg>
                  </span>
                  <div>
                    <h2 className="text-lg font-semibold text-slate-900">Create a maintenance account</h2>
                    <p className="mt-1 text-sm leading-6 text-slate-500">Provide a staff member with secure access to maintenance tasks.</p>
                  </div>
                </div>

                <form className="mt-6 space-y-4" onSubmit={onCreateUser}>
                  <label className="block text-sm font-semibold text-slate-700">
                    Full name
                    <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10" name="name" autoComplete="name" required />
                  </label>
                  <label className="block text-sm font-semibold text-slate-700">
                    Username
                    <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10" name="username" autoComplete="username" required />
                  </label>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-semibold text-slate-700">
                      Temporary password
                      <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10" type="password" name="password" autoComplete="new-password" minLength={8} required />
                    </label>
                    <label className="block text-sm font-semibold text-slate-700">
                      Confirm password
                      <input className="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10" type="password" name="password_confirmation" autoComplete="new-password" minLength={8} required />
                    </label>
                  </div>
                  <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
                    <p className="text-xs text-slate-500">The role is set to maintenance automatically.</p>
                    <button className="rounded-xl bg-emerald-800 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-900 focus:outline-none focus:ring-4 focus:ring-emerald-700/15" type="submit">
                      Create account
                    </button>
                  </div>
                </form>
              </div>

              <div className="space-y-5">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                  <p className="text-xs font-bold uppercase tracking-[0.15em] text-slate-400">Your account</p>
                  <h2 className="mt-3 text-xl font-semibold text-slate-900">{user.name}</h2>
                  <p className="mt-1 text-sm text-slate-500">@{user.username}</p>
                  <div className="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-sm">
                    <span className="text-slate-500">Access level</span>
                    <span className="font-semibold text-emerald-800">Administrator</span>
                  </div>
                  <div className="mt-3 flex items-center justify-between text-sm">
                    <span className="text-slate-500">Account status</span>
                    <span className="inline-flex items-center gap-2 font-semibold text-emerald-800">
                      <span className="h-2 w-2 rounded-full bg-emerald-500" /> Active
                    </span>
                  </div>
                </section>
                <section className="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                  <p className="text-sm font-semibold text-amber-950">System setup</p>
                  <p className="mt-2 text-sm leading-6 text-amber-900/75">Asset records and reports will appear here as those workspace modules are connected.</p>
                </section>
              </div>
            </section>
          </div>
        </div>
      </div>
    </main>
  );
}

export default AdminDashboard;
