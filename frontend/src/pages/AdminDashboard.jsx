import { useState } from 'react';
import AssetManagement from './AssetManagement';
import AdminDashboardOverview from './AdminDashboardOverview';
import LocationManagement from './LocationManagement';
import ReportsManagement from './ReportsManagement';

function AdminDashboard({ error, notice, onCreateUser, onLogout, user }) {
  const [activeSection, setActiveSection] = useState('dashboard');
  const [assetsExpanded, setAssetsExpanded] = useState(false);
  const [maintenanceExpanded, setMaintenanceExpanded] = useState(false);
  const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);
  const [desktopNavigationVisible, setDesktopNavigationVisible] = useState(true);
  const [navigationRequest, setNavigationRequest] = useState({ id: 0, section: 'dashboard' });

  function navigateTo(section) {
    if (section === 'assets-list') setAssetsExpanded(true);
    if (section === 'maintenance' || section === 'maintenance-tasks') setMaintenanceExpanded(true);
    setActiveSection(section);
    setMobileNavigationOpen(false);
    setNavigationRequest((current) => ({ id: current.id + 1, section }));
  }

  return (
    <main className="min-h-screen bg-slate-100 text-slate-900">
      <div className="flex min-h-screen">
        {mobileNavigationOpen && (
          <button
            aria-label="Close navigation menu"
            className="fixed inset-0 z-30 bg-slate-950/40 lg:hidden"
            onClick={() => setMobileNavigationOpen(false)}
            type="button"
          />
        )}
        <aside className={`fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col bg-emerald-950 px-5 py-7 text-white transition-transform lg:static lg:z-auto lg:translate-x-0 ${
          mobileNavigationOpen ? 'translate-x-0' : '-translate-x-full'
        } ${
          desktopNavigationVisible ? 'lg:flex' : 'lg:hidden'
        }`}>
          <a className="flex items-center gap-3 px-2" href="/admin">
            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10">
              <svg aria-hidden="true" className="h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path d="M3 9.5 12 4l9 5.5v1.75H3V9.5Zm2 2.5v6m4-6v6m6-6v6m4-6v6M3 20h18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.6" />
              </svg>
            </span>
            <span>
              <span className="block text-xs font-semibold uppercase tracking-[0.16em] text-emerald-200">LGU-Trinidad</span>
              <span className="mt-0.5 block text-sm font-semibold">Asset Tracking</span>
            </span>
          </a>

          <p className="mb-3 mt-12 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-300/70">Workspace</p>
          <nav aria-label="Admin navigation" className="space-y-2">
            <button
              aria-current={activeSection === 'dashboard' ? 'page' : undefined}
              className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                activeSection === 'dashboard' ? 'bg-white/10 text-white' : 'text-emerald-100/75 hover:bg-white/5 hover:text-white'
              }`}
              onClick={() => navigateTo('dashboard')}
              type="button"
            >
              <svg aria-hidden="true" className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24">
                <path d="M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z" stroke="currentColor" strokeLinejoin="round" strokeWidth="1.7" />
              </svg>
              Dashboard
            </button>
            <button
              aria-controls="admin-assets-submenu"
              aria-expanded={assetsExpanded}
              aria-current={activeSection === 'assets-list' ? 'page' : undefined}
              className={`flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                activeSection === 'assets-list' ? 'bg-white/10 text-white' : 'text-emerald-100/75 hover:bg-white/5 hover:text-white'
              }`}
              onClick={() => setAssetsExpanded((expanded) => !expanded)}
              type="button"
            >
              <span className="flex items-center gap-3">
                <svg aria-hidden="true" className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24">
                  <path d="M8 4.5h8a2 2 0 0 1 2 2v14H6v-14a2 2 0 0 1 2-2Z" stroke="currentColor" strokeLinejoin="round" strokeWidth="1.7" />
                  <path d="M9 4.5V3h6v1.5M9 10h6M9 14h6m-6 4h3" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
                  <path d="m16 14 1.2 1.2 2.3-2.4" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" />
                </svg>
                Assets
              </span>
              <svg aria-hidden="true" className={`h-4 w-4 shrink-0 transition-transform ${assetsExpanded ? '' : '-rotate-90'}`} fill="none" viewBox="0 0 24 24">
                <path d="m6 9 6 6 6-6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" />
              </svg>
            </button>
            {assetsExpanded && (
              <div className="ml-4 space-y-1 border-l border-white/10 pl-3" id="admin-assets-submenu">
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
              </div>
            )}
            <button
              aria-controls="admin-maintenance-submenu"
              aria-expanded={maintenanceExpanded}
              aria-current={activeSection === 'maintenance' || activeSection === 'maintenance-tasks' ? 'page' : undefined}
              className={`flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                activeSection === 'maintenance' || activeSection === 'maintenance-tasks' ? 'bg-white/10 text-white' : 'text-emerald-100/75 hover:bg-white/5 hover:text-white'
              }`}
              onClick={() => setMaintenanceExpanded((expanded) => !expanded)}
              type="button"
            >
              <span className="flex items-center gap-3">
                <svg aria-hidden="true" className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24">
                  <path d="M14.8 5.2a4.5 4.5 0 0 0 5.5 5.5L10 21l-4-4L16.3 6.7a4.5 4.5 0 0 0-5.5-5.5l2.5 2.5-3.1 3.1-2.5-2.5a4.5 4.5 0 0 0 5.5 5.5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
                  <path d="m4.2 3.8 3.6 3.6m-5 1.5 3.5 3.5m-4.3-7.8 3.6 3.6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" />
                </svg>
                Maintenance logs
              </span>
              <svg aria-hidden="true" className={`h-4 w-4 shrink-0 transition-transform ${maintenanceExpanded ? '' : '-rotate-90'}`} fill="none" viewBox="0 0 24 24">
                <path d="m6 9 6 6 6-6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" />
              </svg>
            </button>
            {maintenanceExpanded && (
              <div className="ml-4 space-y-1 border-l border-white/10 pl-3" id="admin-maintenance-submenu">
                <button
                  aria-current={activeSection === 'maintenance' ? 'page' : undefined}
                  className={`w-full rounded-lg px-3 py-2 text-left text-sm transition ${
                    activeSection === 'maintenance' ? 'bg-white/10 font-semibold text-white' : 'text-emerald-100/70 hover:bg-white/5 hover:text-white'
                  }`}
                  onClick={() => navigateTo('maintenance')}
                  type="button"
                >
                  Maintenance Records
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
              aria-current={activeSection === 'reports' ? 'page' : undefined}
              className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                activeSection === 'reports' ? 'bg-white/10 text-white' : 'text-emerald-100/75 hover:bg-white/5 hover:text-white'
              }`}
              onClick={() => navigateTo('reports')}
              type="button"
            >
              <svg aria-hidden="true" className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24">
                <path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2Z" stroke="currentColor" strokeLinejoin="round" strokeWidth="1.7" />
                <path d="M14 4v4h4M8.5 12h6M8.5 15.5h6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
              </svg>
              Reports
            </button>
            <button
              aria-current={activeSection === 'locations' ? 'page' : undefined}
              className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                activeSection === 'locations' ? 'bg-white/10 text-white' : 'text-emerald-100/75 hover:bg-white/5 hover:text-white'
              }`}
              onClick={() => navigateTo('locations')}
              type="button"
            >
              <svg aria-hidden="true" className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24">
                <path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
                <circle cx="12" cy="10" r="2.25" stroke="currentColor" strokeWidth="1.7" />
              </svg>
              Locations
            </button>
            <button
              aria-current={activeSection === 'users' ? 'page' : undefined}
              className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition ${
                activeSection === 'users' ? 'bg-white/10 text-white' : 'text-emerald-100/75 hover:bg-white/5 hover:text-white'
              }`}
              onClick={() => navigateTo('users')}
              type="button"
            >
              <svg aria-hidden="true" className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24">
                <path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20m6-8a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm5-7.5a4 4 0 0 1 0 7.5m2 3h.5a3.5 3.5 0 0 1 3.5 3.5V20" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
              </svg>
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
            <div className="flex items-center gap-3">
              <button
                aria-expanded={mobileNavigationOpen}
                aria-label={mobileNavigationOpen ? 'Close navigation menu' : 'Open navigation menu'}
                className="rounded-lg p-2 text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-emerald-700/10 lg:hidden"
                onClick={() => setMobileNavigationOpen((open) => !open)}
                type="button"
              >
                <svg aria-hidden="true" className="h-5 w-5" fill="none" viewBox="0 0 24 24">
                  <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" strokeLinecap="round" strokeWidth="1.8" />
                </svg>
              </button>
              <button
                aria-expanded={desktopNavigationVisible}
                aria-label={desktopNavigationVisible ? 'Hide navigation sidebar' : 'Show navigation sidebar'}
                className="hidden rounded-lg p-2 text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-emerald-700/10 lg:inline-flex"
                onClick={() => setDesktopNavigationVisible((visible) => !visible)}
                type="button"
              >
                <svg aria-hidden="true" className="h-5 w-5" fill="none" viewBox="0 0 24 24">
                  <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" strokeLinecap="round" strokeWidth="1.8" />
                </svg>
              </button>
              <div>
              <p className="text-xs font-semibold uppercase tracking-[0.13em] text-emerald-800">Property and Equipment Tracking</p>
              <p className="mt-1 text-sm text-slate-500">Administration workspace</p>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <div className="hidden text-right sm:block">
                <p className="text-sm font-semibold text-slate-800">{user.name}</p>
                <p className="text-xs text-slate-500">Property Custodian</p>
              </div>
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-900">
                {user.name.slice(0, 1).toUpperCase()}
              </div>
              <button className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-emerald-700/10" onClick={onLogout} type="button">
                Sign out
              </button>
            </div>
          </header>

          <div className="mx-auto w-full max-w-7xl flex-1 px-5 py-8 sm:px-8 sm:py-10">
            {error && <p className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{error}</p>}
            {notice && <p className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{notice}</p>}

            {!['dashboard', 'locations', 'reports'].includes(activeSection) && <div className="flex flex-wrap items-end justify-between gap-4">
              <div>
                <p className="text-sm font-medium text-emerald-800">Good day, {user.name}</p>
                <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-900">Admin overview</h1>
                <p className="mt-2 text-sm text-slate-500">Manage access and coordinate property tracking.</p>
              </div>
              <span className="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800">ADMINISTRATOR</span>
            </div>}

            {activeSection === 'dashboard' ? (
              <AdminDashboardOverview onNavigate={navigateTo} user={user} />
            ) : activeSection === 'reports' ? (
              <ReportsManagement key={navigationRequest.id} />
            ) : activeSection === 'locations' ? (
              <LocationManagement key={navigationRequest.id} />
            ) : (
              <AssetManagement
                key={navigationRequest.id}
                navigationRequest={navigationRequest}
                onNavigate={navigateTo}
                onCreateUser={onCreateUser}
                onSectionChange={setActiveSection}
                user={user}
              />
            )}
          </div>
        </div>
      </div>
    </main>
  );
}

export default AdminDashboard;
