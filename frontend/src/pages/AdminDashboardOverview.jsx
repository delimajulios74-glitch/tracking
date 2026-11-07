import { useEffect, useState } from 'react';
import { getAdminDashboard } from '../api';

const cards = [
  { key: 'total_assets', label: 'Total Assets', icon: 'assets', color: 'text-blue-700', tone: 'bg-blue-50' },
  { key: 'in_use', label: 'In Use', icon: 'in-use', color: 'text-emerald-700', tone: 'bg-emerald-50' },
  { key: 'under_maintenance', label: 'Under Maintenance', icon: 'maintenance', color: 'text-amber-700', tone: 'bg-amber-50' },
  { key: 'out_of_service', label: 'Out of Service', icon: 'service', color: 'text-rose-700', tone: 'bg-rose-50' },
];

const statusLegend = [
  { key: 'in_use', label: 'In Use', color: '#16a765' },
  { key: 'under_maintenance', label: 'Under Maintenance', color: '#f5a623' },
  { key: 'out_of_service', label: 'Out of Service', color: '#e94c45' },
  { key: 'available', label: 'Available', color: '#4385c5' },
];

function CardIcon({ name }) {
  return (
    <svg aria-hidden="true" className="h-6 w-6" fill="none" viewBox="0 0 24 24">
      {name === 'assets' && (
        <>
          <path d="M5 4.5h9l5 5v10H5v-15Z" stroke="currentColor" strokeLinejoin="round" strokeWidth="1.7" />
          <path d="M14 4.5v5h5M8 13h8M8 16.5h6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
        </>
      )}
      {name === 'in-use' && (
        <>
          <circle cx="12" cy="8" r="3" stroke="currentColor" strokeWidth="1.7" />
          <path d="M5.5 20a6.5 6.5 0 0 1 13 0" stroke="currentColor" strokeLinecap="round" strokeWidth="1.7" />
        </>
      )}
      {name === 'maintenance' && (
        <path d="M14.4 6.2a5 5 0 0 0 6.4 6.4L11 22.4 5.6 17 15.4 7.2a5 5 0 0 0-6.4-6.4l3.1 3.1-3.5 3.5-3.1-3.1a5 5 0 0 0 6.4 6.4" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
      )}
      {name === 'service' && (
        <>
          <path d="M12 3 20 7v5c0 4.7-3.4 7.8-8 9-4.6-1.2-8-4.3-8-9V7l8-4Z" stroke="currentColor" strokeLinejoin="round" strokeWidth="1.7" />
          <path d="m9 9 6 6m0-6-6 6" stroke="currentColor" strokeLinecap="round" strokeWidth="1.7" />
        </>
      )}
    </svg>
  );
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function AdminDashboardOverview({ onNavigate, user }) {
  const [dashboard, setDashboard] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showAllActivity, setShowAllActivity] = useState(false);

  useEffect(() => {
    let active = true;

    getAdminDashboard()
      .then((response) => {
        if (active) setDashboard(response.data);
      })
      .catch((requestError) => {
        if (active) setError(requestError.message || 'Unable to load the dashboard.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  const summary = dashboard?.summary;
  const chartTotal = summary
    ? statusLegend.reduce((total, item) => total + summary[item.key], 0)
    : 0;
  const chartSegments = statusLegend.map((item, itemIndex) => {
    const start = statusLegend
      .slice(0, itemIndex)
      .reduce((total, previous) => total + (summary?.[previous.key] || 0), 0);
    const end = start + (summary?.[item.key] || 0);
    return `${item.color} ${chartTotal ? (start / chartTotal) * 100 : 0}% ${chartTotal ? (end / chartTotal) * 100 : 0}%`;
  });
  const locations = dashboard?.locations || [];
  const maxLocationCount = Math.max(...locations.map((location) => location.total), 1);
  const activity = dashboard?.activity || [];

  return (
    <section aria-labelledby="admin-dashboard-title">
      <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-sm font-medium text-emerald-800">Good day, {user.name}</p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight text-slate-900" id="admin-dashboard-title">
            Dashboard
          </h1>
          <p className="mt-2 text-sm text-slate-500">A quick overview of municipal assets and recent activity.</p>
        </div>
        <span className="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold tracking-wide text-emerald-800">
          ADMINISTRATOR
        </span>
      </div>

      {error && (
        <p className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
          {error}
        </p>
      )}

      <div aria-busy={loading} className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => (
          <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" key={card.key}>
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-sm font-medium text-slate-500">{card.label}</p>
                <p className="mt-3 text-3xl font-bold tracking-tight text-slate-900">
                  {loading ? <span className="text-slate-300">—</span> : (summary?.[card.key] ?? 0).toLocaleString()}
                </p>
              </div>
              <span className={`flex h-11 w-11 items-center justify-center rounded-xl ${card.tone} ${card.color}`}>
                <CardIcon name={card.icon} />
              </span>
            </div>
            <button
              className="mt-4 text-sm font-semibold text-emerald-800 hover:text-emerald-950 focus:outline-none focus:underline"
              onClick={() => onNavigate('assets-list')}
              type="button"
            >
              View details <span aria-hidden="true">&gt;</span>
            </button>
          </article>
        ))}
      </div>

      <div className="mt-6 grid gap-6 xl:grid-cols-2">
        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
          <div>
            <h2 className="text-lg font-semibold text-slate-900">Asset Status Overview</h2>
            <p className="mt-1 text-sm text-slate-500">Current status of all active asset records.</p>
          </div>
          {loading ? (
            <p className="py-16 text-center text-sm text-slate-500" role="status">Loading asset status...</p>
          ) : chartTotal === 0 ? (
            <p className="py-16 text-center text-sm text-slate-500">No assets have been registered yet.</p>
          ) : (
            <div className="mt-6 flex flex-col items-center justify-center gap-8 sm:flex-row">
              <div
                aria-label={`${chartTotal} assets by status`}
                className="relative flex h-44 w-44 shrink-0 items-center justify-center rounded-full"
                role="img"
                style={{ background: `conic-gradient(${chartSegments.join(', ')})` }}
              >
                <div className="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-white">
                  <span className="text-3xl font-bold text-slate-900">{chartTotal.toLocaleString()}</span>
                  <span className="text-xs text-slate-500">Total assets</span>
                </div>
              </div>
              <ul className="w-full space-y-3 sm:w-auto">
                {statusLegend.map((item) => (
                  <li className="flex items-center justify-between gap-6 text-sm sm:justify-start" key={item.key}>
                    <span className="flex items-center gap-2 text-slate-600">
                      <span aria-hidden="true" className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: item.color }} />
                      {item.label}
                    </span>
                    <span className="font-semibold text-slate-900">
                      {summary[item.key].toLocaleString()} ({Math.round((summary[item.key] / chartTotal) * 100)}%)
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </article>

        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
          <div>
            <h2 className="text-lg font-semibold text-slate-900">Assets by Location</h2>
            <p className="mt-1 text-sm text-slate-500">Locations with the most registered assets.</p>
          </div>
          {loading ? (
            <p className="py-16 text-center text-sm text-slate-500" role="status">Loading locations...</p>
          ) : locations.length === 0 ? (
            <p className="py-16 text-center text-sm text-slate-500">No asset locations to display.</p>
          ) : (
            <div className="mt-7 flex h-48 items-end gap-3 border-b border-slate-200 px-1 sm:gap-5">
              {locations.map((location) => (
                <div className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2" key={location.id}>
                  <span className="text-xs font-semibold text-slate-700">{location.total}</span>
                  <div
                    aria-label={`${location.total} assets`}
                    className="w-full max-w-16 rounded-t-md bg-blue-600 transition-colors hover:bg-blue-700"
                    style={{ height: `${Math.max((location.total / maxLocationCount) * 76, 4)}%` }}
                  />
                  <span className="w-full truncate pb-1 text-center text-[11px] text-slate-500" title={location.name}>
                    {location.name}
                  </span>
                </div>
              ))}
            </div>
          )}
        </article>
      </div>

      <article className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-5 sm:px-6">
          <div>
            <h2 className="text-lg font-semibold text-slate-900">Recent Activity</h2>
            <p className="mt-1 text-sm text-slate-500">Latest updates made to the asset records.</p>
          </div>
          {activity.length > 5 && (
            <button
              aria-expanded={showAllActivity}
              className="text-sm font-medium text-blue-700 hover:text-blue-900 focus:outline-none focus:underline"
              onClick={() => setShowAllActivity((shown) => !shown)}
              type="button"
            >
              {showAllActivity ? 'Show less' : 'View all activity'}
            </button>
          )}
        </div>

        {loading ? (
          <p className="border-t border-slate-100 px-6 py-6 text-sm text-slate-500" role="status">Loading recent activity...</p>
        ) : activity.length === 0 ? (
          <p className="border-t border-slate-100 px-6 py-6 text-sm text-slate-500">No activity has been recorded yet.</p>
        ) : (
          <ul className="divide-y divide-slate-100 border-t border-slate-100">
            {activity.slice(0, showAllActivity ? 10 : 5).map((item) => (
              <li className="grid gap-2 px-5 py-4 text-sm sm:grid-cols-[minmax(0,1fr)_minmax(8rem,0.55fr)_auto] sm:items-center sm:px-6" key={item.id}>
                <div className="min-w-0">
                  <p className="truncate font-medium text-slate-800">{item.description}</p>
                  {item.asset_tag && <p className="mt-1 text-xs text-slate-500">{item.asset_tag}{item.asset_name ? ` · ${item.asset_name}` : ''}</p>}
                </div>
                <span className="text-slate-600">{item.user_name}</span>
                <time className="text-slate-500 sm:text-right" dateTime={item.created_at}>{formatDate(item.created_at)}</time>
              </li>
            ))}
          </ul>
        )}
      </article>
    </section>
  );
}

export default AdminDashboardOverview;
