import townHall from '../assets/Municipal-Town-Hall-Front-1.jpg';

function LandingPage({ error, notice, onSubmit, submitting }) {
  return (
    <main className="relative isolate min-h-screen overflow-hidden bg-slate-950">
      <div
        aria-hidden="true"
        className="absolute inset-0 -z-10 bg-cover bg-[center_42%]"
        style={{
          backgroundImage: `linear-gradient(90deg, rgba(8, 25, 24, 0.78) 0%, rgba(9, 30, 27, 0.58) 46%, rgba(10, 26, 35, 0.37) 100%), linear-gradient(0deg, rgba(8, 18, 23, 0.55), transparent 55%), url("${townHall}")`,
        }}
      />

      <div className="mx-auto flex min-h-screen w-full max-w-[1440px] flex-col px-5 sm:px-8 lg:px-12">
        <header className="flex items-center justify-between py-6 sm:py-8">
          <a aria-label="LGU-Trinidad home" className="flex items-center gap-3.5 text-white" href="/">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/30 bg-white/15 shadow-lg backdrop-blur-sm">
              <svg aria-hidden="true" className="h-7 w-7" fill="none" viewBox="0 0 24 24">
                <path d="M3 9.5 12 4l9 5.5v1.75H3V9.5Z" stroke="currentColor" strokeLinejoin="round" strokeWidth="1.6" />
                <path d="M5 12v6m4-6v6m6-6v6m4-6v6M3 20h18M2.5 22h19" stroke="currentColor" strokeLinecap="round" strokeWidth="1.6" />
              </svg>
            </div>
            <span>
              <span className="block text-sm font-bold uppercase tracking-[0.16em]">Municipality of</span>
              <span className="mt-0.5 block text-lg font-semibold tracking-wide">Trinidad</span>
            </span>
          </a>
          <div className="hidden items-center gap-2 rounded-full border border-white/20 bg-slate-950/20 px-4 py-2 text-xs font-medium tracking-wide text-white/90 backdrop-blur-md sm:flex">
            <span className="h-2 w-2 rounded-full bg-emerald-300" />
            LOCAL GOVERNMENT UNIT
          </div>
        </header>

        <div className="grid flex-1 items-center gap-10 pb-10 pt-6 lg:grid-cols-[minmax(0,1fr)_minmax(390px,460px)] lg:gap-16 lg:pb-16 lg:pt-2">
          <section className="max-w-2xl text-white">
            <div className="mb-6 inline-flex items-center gap-2.5 rounded-full border border-white/25 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-50 backdrop-blur-md">
              <span className="h-1.5 w-1.5 rounded-full bg-emerald-300" />
              Public service, managed with care
            </div>
            <h1 className="max-w-xl text-4xl font-semibold leading-[1.08] tracking-tight sm:text-5xl lg:text-6xl">
              Property and Equipment
              <span className="mt-2 block text-emerald-200">Tracking System</span>
            </h1>
            <p className="mt-6 max-w-lg text-base leading-7 text-white/80 sm:text-lg sm:leading-8">
              A central workspace for responsible asset stewardship, clear records, and dependable municipal operations.
            </p>
            <div className="mt-10 hidden items-center gap-4 border-l-2 border-emerald-300/80 pl-5 text-sm text-white/80 sm:flex">
              <span className="text-2xl text-emerald-200" aria-hidden="true">“</span>
              <p className="max-w-sm leading-6">Serving our community through transparent and accountable public service.</p>
            </div>
          </section>

          <section className="w-full rounded-[1.75rem] border border-white/55 bg-white/95 p-6 shadow-[0_24px_80px_rgba(0,0,0,0.25)] backdrop-blur-xl sm:p-9">
            <div className="mb-7">
              <p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-800">Secure access</p>
              <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900 sm:text-[1.75rem]">Welcome back</h2>
              <p className="mt-2 text-sm leading-6 text-slate-500">Sign in with the account provided by your administrator.</p>
            </div>

            <form className="space-y-5" onSubmit={onSubmit}>
              <label className="block text-sm font-semibold text-slate-700">
                Username
                <input className="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-normal text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10" name="username" placeholder="Enter your username" autoComplete="username" required />
              </label>
              <label className="block text-sm font-semibold text-slate-700">
                Password
                <input className="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-normal text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10" type="password" name="password" placeholder="Enter your password" autoComplete="current-password" required />
              </label>
              <button className="!mt-7 flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-800 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-900 focus:outline-none focus:ring-4 focus:ring-emerald-700/20 disabled:cursor-wait disabled:opacity-70" type="submit" disabled={submitting}>
                {submitting ? 'Signing in...' : 'Sign in to your account'}
                {!submitting && (
                  <svg aria-hidden="true" className="h-4 w-4" fill="none" viewBox="0 0 20 20">
                    <path d="M4.167 10h11.666m0 0-5-5m5 5-5 5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
                  </svg>
                )}
              </button>
            </form>

            {error && <p className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-5 text-red-800" role="alert">{error}</p>}
            {notice && <p className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-5 text-emerald-800" role="status">{notice}</p>}

            <p className="mt-6 text-center text-xs leading-5 text-slate-400">Account access is managed by your municipal administrator.</p>
          </section>
        </div>

        <footer className="flex flex-col gap-2 border-t border-white/20 py-5 text-xs text-white/65 sm:flex-row sm:items-center sm:justify-between">
          <p>© 2026 Municipality of Trinidad. All rights reserved.</p>
          <p>Property and Equipment Tracking System</p>
        </footer>
      </div>
    </main>
  );
}

export default LandingPage;
