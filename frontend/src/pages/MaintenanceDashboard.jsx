import { useState } from 'react';
import AssetManagement from './AssetManagement';

function MaintenanceDashboard({ error, notice, onLogout, user }) {
  const [activeSection, setActiveSection] = useState('assets');
  const [navigationRequest, setNavigationRequest] = useState({ id: 0, section: 'assets' });

  function navigateTo(section) {
    setActiveSection(section);
    setNavigationRequest((current) => ({ id: current.id + 1, section }));
  }

  return (
    <main className="min-h-screen bg-[#f4f7f8] text-slate-900">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">
          <a className="flex items-center gap-3" href="/maintenance">
            <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-900 text-white">
              <svg aria-hidden="true" className="h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path d="M14.5 6.5a4 4 0 0 0 5.52 5.52l-8.24 8.24a2.12 2.12 0 0 1-3-3l8.24-8.24A4 4 0 0 0 14.5 6.5ZM6.5 17.5l-3-3 5-5 3 3-5 5Z" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
              </svg>
            </span>
            <span>
              <span className="block text-xs font-bold uppercase tracking-[0.15em] text-sky-900">LGU Trinidad</span>
              <span className="mt-0.5 block text-sm font-medium text-slate-500">Maintenance workspace</span>
            </span>
          </a>
          <div className="flex items-center gap-3">
            <div className="hidden text-right sm:block">
              <p className="text-sm font-semibold text-slate-800">{user.name}</p>
              <p className="text-xs text-slate-500">Maintenance staff</p>
            </div>
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-sky-100 text-sm font-bold text-sky-900">
              {user.name.slice(0, 1).toUpperCase()}
            </div>
            <button className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-sky-800/10" onClick={onLogout} type="button">
              Sign out
            </button>
          </div>
        </div>
      </header>

      <div className="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10">
        {error && <p className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
        {notice && <p className="mb-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status">{notice}</p>}

        <section className="overflow-hidden rounded-3xl bg-gradient-to-br from-sky-950 via-sky-900 to-cyan-800 px-6 py-8 text-white shadow-lg sm:px-9 sm:py-10">
          <div className="grid items-center gap-8 md:grid-cols-[minmax(0,1fr)_auto]">
            <div className="max-w-2xl">
              <p className="text-xs font-bold uppercase tracking-[0.18em] text-cyan-200">Maintenance portal</p>
              <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Good day, {user.name}</h1>
              <p className="mt-3 max-w-xl text-sm leading-6 text-sky-100/80 sm:text-base">
                Use your workspace to follow assigned equipment and keep maintenance information up to date.
              </p>
            </div>
            <div className="hidden h-28 w-28 items-center justify-center rounded-full border border-white/20 bg-white/10 md:flex">
              <svg aria-hidden="true" className="h-12 w-12 text-cyan-100" fill="none" viewBox="0 0 48 48">
                <path d="M28.5 12.5a9 9 0 0 0 12.42 12.42L22.38 43.46a4.77 4.77 0 0 1-6.75-6.75L34.17 18.17A9 9 0 0 0 28.5 12.5ZM10.5 37.5l-6-6 10-10 6 6-10 10Z" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" />
              </svg>
            </div>
          </div>
        </section>

        <div className="mt-7 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
          <aside className="self-start rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-6">
            <p className="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Workspace</p>
            <nav aria-label="Maintenance navigation" className="space-y-2">
              <button
                aria-current={activeSection === 'assets' ? 'page' : undefined}
                className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                  activeSection === 'assets' ? 'bg-sky-900 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                }`}
                onClick={() => navigateTo('assets')}
                type="button"
              >
                Assets
              </button>
              <button
                aria-current={activeSection === 'maintenance' ? 'page' : undefined}
                className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                  activeSection === 'maintenance' ? 'bg-sky-900 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                }`}
                onClick={() => navigateTo('maintenance')}
                type="button"
              >
                Maintenance logs
              </button>
            </nav>
          </aside>

          <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
          <AssetManagement
            key={navigationRequest.id}
            navigationRequest={navigationRequest}
            onSectionChange={setActiveSection}
            user={user}
          />

          <aside className="space-y-5">
            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
              <p className="text-xs font-bold uppercase tracking-[0.15em] text-slate-400">Account details</p>
              <h2 className="mt-3 text-lg font-semibold text-slate-900">{user.name}</h2>
              <p className="mt-1 text-sm text-slate-500">@{user.username}</p>
              <div className="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-sm">
                <span className="text-slate-500">Role</span>
                <span className="font-semibold text-sky-900">Maintenance</span>
              </div>
              <div className="mt-3 flex items-center justify-between text-sm">
                <span className="text-slate-500">Status</span>
                <span className="inline-flex items-center gap-2 font-semibold text-emerald-800">
                  <span className="h-2 w-2 rounded-full bg-emerald-500" /> Active
                </span>
              </div>
            </section>
            <section className="rounded-2xl border border-cyan-100 bg-cyan-50 p-6">
              <h2 className="text-sm font-semibold text-sky-950">Need account assistance?</h2>
              <p className="mt-2 text-sm leading-6 text-sky-900/75">Contact your property custodian for account or access support.</p>
            </section>
          </aside>
          </div>
        </div>

        <footer className="mt-10 border-t border-slate-200 py-5 text-xs text-slate-400">
          Property and Equipment Tracking System · Municipality of Trinidad
        </footer>
      </div>
    </main>
  );
}

export default MaintenanceDashboard;
