import { useState } from 'react';
import AssetManagement from './AssetManagement';

function AdminDashboard({ error, notice, onCreateUser, onLogout, user }) {
  const [activeSection, setActiveSection] = useState('assets-list');
  const [assetsExpanded, setAssetsExpanded] = useState(true);
  const [navigationRequest, setNavigationRequest] = useState({ id: 0, section: 'assets-list' });

  function navigateTo(section) {
    setActiveSection(section);
    setNavigationRequest((current) => ({ id: current.id + 1, section }));
  }

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
          <nav aria-label="Admin navigation" className="space-y-2">
            <button
              aria-expanded={assetsExpanded}
              aria-current={activeSection === 'assets-list' || activeSection === 'maintenance-tasks' ? 'page' : undefined}
              className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                activeSection === 'assets-list' || activeSection === 'maintenance-tasks' ? 'bg-white/10 text-white' : 'text-emerald-100/75 hover:bg-white/5 hover:text-white'
              }`}
              onClick={() => setAssetsExpanded((expanded) => !expanded)}
              type="button"
            >
              <span aria-hidden="true" className={`h-2 w-2 rounded-full ${activeSection === 'assets-list' || activeSection === 'maintenance-tasks' ? 'bg-emerald-300' : 'bg-transparent'}`} />
              Assets
            </button>
            {assetsExpanded && (
              <div className="ml-4 space-y-1 border-l border-white/10 pl-3">
                <button
                  aria-current={activeSection === 'assets-list' ? 'page' : undefined}
                  className={`w-full rounded-lg px-3 py-2 text-left text-sm transition ${
                    activeSection === 'assets-list' ? 'bg-white/10 font-semibold text-white' : 'text-emerald-100/70 hover:bg-white/5 hover:text-white'
                  }`}
                  onClick={() => navigateTo('assets-list')}
                  type="button"
                >
                  Asset List
                </button>
                <button
                  aria-current={activeSection === 'maintenance-tasks' ? 'page' : undefined}
                  className={`w-full rounded-lg px-3 py-2 text-left text-sm transition ${
                    activeSection === 'maintenance-tasks' ? 'bg-white/10 font-semibold text-white' : 'text-emerald-100/70 hover:bg-white/5 hover:text-white'
                  }`}
                  onClick={() => navigateTo('maintenance-tasks')}
                  type="button"
                >
                  Maintenance Tasks
                </button>
              </div>
            )}
            <button
              aria-current={activeSection === 'maintenance' ? 'page' : undefined}
              className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                activeSection === 'maintenance' ? 'bg-white/10 text-white' : 'text-emerald-100/75 hover:bg-white/5 hover:text-white'
              }`}
              onClick={() => navigateTo('maintenance')}
              type="button"
            >
              <span aria-hidden="true" className={`h-2 w-2 rounded-full ${activeSection === 'maintenance' ? 'bg-emerald-300' : 'bg-transparent'}`} />
              Maintenance logs
            </button>
            <button
              aria-current={activeSection === 'users' ? 'page' : undefined}
              className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                activeSection === 'users' ? 'bg-white/10 text-white' : 'text-emerald-100/75 hover:bg-white/5 hover:text-white'
              }`}
              onClick={() => navigateTo('users')}
              type="button"
            >
              <span aria-hidden="true" className={`h-2 w-2 rounded-full ${activeSection === 'users' ? 'bg-emerald-300' : 'bg-transparent'}`} />
              Users
            </button>
          </nav>
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

            <AssetManagement
              key={navigationRequest.id}
              navigationRequest={navigationRequest}
              onCreateUser={onCreateUser}
              onSectionChange={setActiveSection}
              user={user}
            />
          </div>
        </div>
      </div>
    </main>
  );
}

export default AdminDashboard;
