import { useState } from 'react';
import AssetManagement from './AssetManagement';
import NotificationBell from '../components/NotificationBell';

function MaintenanceDashboard({ error, notice, onLogout, user }) {
  const [activeSection, setActiveSection] = useState('maintenance-tasks');
  const [assetsExpanded, setAssetsExpanded] = useState(true);
  const [navigationRequest, setNavigationRequest] = useState({ id: 0, section: 'maintenance-tasks' });

  function navigateTo(section, taskId = null) {
    setActiveSection(section);
    setNavigationRequest((current) => ({ id: current.id + 1, section, taskId }));
  }

  return (
    <main className="min-h-screen bg-[#f4f7f8] text-slate-900">
      <header className="border-b border-slate-200 bg-white">
        <div className="flex items-center justify-between px-5 py-4 sm:px-8 lg:px-10">
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
            <NotificationBell
              onOpenTask={(taskId) => navigateTo('maintenance-tasks', taskId)}
            />
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

      <div className="w-full px-5 py-6 sm:px-8 sm:py-8 lg:px-10">
        {error && <p className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
        {notice && <p className="mb-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status">{notice}</p>}

        <div className="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
          <aside className="self-start rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-6">
            <p className="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Workspace</p>
            <nav aria-label="Maintenance navigation" className="space-y-2">
              <button
                aria-expanded={assetsExpanded}
                aria-current={activeSection === 'maintenance-tasks' ? 'page' : undefined}
                className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                  activeSection === 'maintenance-tasks' ? 'bg-sky-900 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                }`}
                onClick={() => setAssetsExpanded((expanded) => !expanded)}
                type="button"
              >
                Assets
              </button>
              {assetsExpanded && (
                <div className="ml-4 space-y-1 border-l border-slate-200 pl-3">
                  <button
                    aria-current={activeSection === 'maintenance-tasks' ? 'page' : undefined}
                    className={`w-full rounded-lg px-3 py-2 text-left text-sm transition ${
                      activeSection === 'maintenance-tasks' ? 'bg-sky-50 font-semibold text-sky-900' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                    }`}
                    onClick={() => navigateTo('maintenance-tasks')}
                    type="button"
                  >
                    Maintenance Tasks
                  </button>
                </div>
              )}
            </nav>
          </aside>

          <div className="min-w-0">
            <AssetManagement
              key={navigationRequest.id}
              navigationRequest={navigationRequest}
              onSectionChange={setActiveSection}
              user={user}
            />
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
