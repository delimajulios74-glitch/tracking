import { useEffect, useState } from 'react';
import {
  changePassword,
  getAccount,
  getEndUserCategories,
  getEndUserDashboard,
  getEndUserEquipment,
  getEndUserEquipmentDetails,
  getEndUserLocations,
  getEndUserReport,
  getEndUserReportPhoto,
  getEndUserReports,
  submitEndUserReport,
  updateAccount,
} from '../services/endUserApi';
import { ApiError } from '../api';

const navigation = [
  ['dashboard', 'Dashboard', 'dashboard'],
  ['equipment', 'Equipment list', 'equipment'],
  ['report', 'Report Damage Equipment', 'report'],
  ['reports', 'My Reports', 'reports'],
  ['account', 'My Account', 'account'],
];

const inputClass = 'mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10';

function NavigationIcon({ name, className = 'h-5 w-5' }) {
  const common = {
    className,
    fill: 'none',
    stroke: 'currentColor',
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    strokeWidth: 1.7,
    viewBox: '0 0 24 24',
    'aria-hidden': true,
  };

  if (name === 'dashboard') {
    return <svg {...common}><path d="m3.5 10 8.5-7 8.5 7v9a2 2 0 0 1-2 2h-4.75v-6h-3.5v6H5.5a2 2 0 0 1-2-2v-9Z" /></svg>;
  }
  if (name === 'equipment') {
    return <svg {...common}><path d="M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 18V6A1.5 1.5 0 0 1 5 4.5Z" /><path d="M8 8h8M8 12h8M8 16h4" /><path d="m5.5 8 .5.5 1-1" /></svg>;
  }
  if (name === 'report') {
    return <svg {...common}><path d="M7 3.5h7l4 4V20a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 20V5a1.5 1.5 0 0 1 1-1.5Z" /><path d="M14 3.5V8h4M9 12h6M9 16h3" /><path d="m15.5 17.5 1.25 1.25 2.75-3" /></svg>;
  }
  if (name === 'reports') {
    return <svg {...common}><path d="M7 3.5h7l4 4V20a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 20V5a1.5 1.5 0 0 1 1-1.5Z" /><path d="M14 3.5V8h4M9 12h6M9 15.5h6M9 19h4" /></svg>;
  }
  return <svg {...common}><circle cx="12" cy="8" r="3.5" /><path d="M4.5 20a7.5 7.5 0 0 1 15 0" /><circle cx="12" cy="12" r="10" /></svg>;
}

function statusStyle(status) {
  const value = String(status || '').toLowerCase().replaceAll('_', ' ');
  if (['under maintenance', 'under repair', 'in review', 'in progress'].includes(value)) {
    return 'bg-amber-50 text-amber-800';
  }
  if (['out of service', 'lost', 'rejected', 'disposed'].includes(value)) {
    return 'bg-rose-50 text-rose-800';
  }
  if (['resolved', 'completed'].includes(value)) {
    return 'bg-emerald-50 text-emerald-800';
  }
  return 'bg-emerald-50 text-emerald-800';
}

function formatStatus(status) {
  return String(status || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function EmptyState({ children }) {
  return <p className="rounded-xl border border-dashed border-slate-300 px-5 py-10 text-center text-sm text-slate-500">{children}</p>;
}

function EndUserDashboard({ onAccountUpdate, onLogout, user }) {
  const [section, setSection] = useState('dashboard');
  const [dashboard, setDashboard] = useState(null);
  const [dashboardLoading, setDashboardLoading] = useState(true);
  const [dashboardError, setDashboardError] = useState('');
  const [equipment, setEquipment] = useState([]);
  const [equipmentMeta, setEquipmentMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [equipmentLoading, setEquipmentLoading] = useState(true);
  const [equipmentError, setEquipmentError] = useState('');
  const [equipmentSearchDraft, setEquipmentSearchDraft] = useState('');
  const [equipmentFilters, setEquipmentFilters] = useState({ q: '', category_id: '', status: '' });
  const [equipmentPage, setEquipmentPage] = useState(1);
  const [categories, setCategories] = useState([]);
  const [selectedEquipment, setSelectedEquipment] = useState(null);
  const [pickerSearch, setPickerSearch] = useState('');
  const [pickerOptions, setPickerOptions] = useState([]);
  const [reportAssetId, setReportAssetId] = useState('');
  const [pickerLoading, setPickerLoading] = useState(false);
  const [locations, setLocations] = useState([]);
  const [reportLocationId, setReportLocationId] = useState('');
  const [preferredLocationName, setPreferredLocationName] = useState('');
  const [reports, setReports] = useState([]);
  const [reportsMeta, setReportsMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [reportsPage, setReportsPage] = useState(1);
  const [reportsLoading, setReportsLoading] = useState(true);
  const [reportsError, setReportsError] = useState('');
  const [selectedReport, setSelectedReport] = useState(null);
  const [reportLoading, setReportLoading] = useState(false);
  const [reportPhoto, setReportPhoto] = useState(null);
  const [reportSubmitting, setReportSubmitting] = useState(false);
  const [reportErrors, setReportErrors] = useState({});
  const [account, setAccount] = useState(user);
  const [accountLoading, setAccountLoading] = useState(true);
  const [accountError, setAccountError] = useState('');
  const [accountNotice, setAccountNotice] = useState('');
  const [accountSaving, setAccountSaving] = useState(false);
  const [notice, setNotice] = useState('');

  useEffect(() => {
    let active = true;
    getEndUserDashboard()
      .then((response) => {
        if (active) setDashboard(response.data);
      })
      .catch((error) => {
        if (active) setDashboardError(error.message || 'Unable to load your dashboard.');
      })
      .finally(() => {
        if (active) setDashboardLoading(false);
      });

    getEndUserCategories()
      .then((response) => {
        if (active) setCategories(response.data);
      })
      .catch((error) => {
        if (active) setEquipmentError(error.message || 'Unable to load equipment categories.');
      });

    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    if (section !== 'equipment') return undefined;
    let active = true;
    getEndUserEquipment({ ...equipmentFilters, page: equipmentPage })
      .then((response) => {
        if (!active) return;
        setEquipment(response.data);
        setEquipmentMeta(response.meta);
      })
      .catch((error) => {
        if (active) setEquipmentError(error.message || 'Unable to load equipment.');
      })
      .finally(() => {
        if (active) setEquipmentLoading(false);
      });

    return () => {
      active = false;
    };
  }, [equipmentFilters, equipmentPage, section]);

  useEffect(() => {
    if (section !== 'report') return undefined;
    let active = true;
    getEndUserLocations()
      .then((response) => {
        if (!active) return;
        setLocations(response.data);
        const defaultLocation = response.data.find((item) => item.name === preferredLocationName);
        if (defaultLocation) setReportLocationId(String(defaultLocation.id));
      })
      .catch((error) => {
        if (active) setEquipmentError(error.message || 'Unable to load report locations.');
      });

    return () => {
      active = false;
    };
  }, [preferredLocationName, section]);

  useEffect(() => {
    if (section !== 'report') return undefined;
    let active = true;
    getEndUserEquipment({ q: pickerSearch, per_page: 100 })
      .then((response) => {
        if (active) setPickerOptions(response.data);
      })
      .catch((error) => {
        if (active) setEquipmentError(error.message || 'Unable to search equipment.');
      })
      .finally(() => {
        if (active) setPickerLoading(false);
      });

    return () => {
      active = false;
    };
  }, [pickerSearch, section]);

  useEffect(() => {
    if (section !== 'reports' || selectedReport) return undefined;
    let active = true;
    getEndUserReports(reportsPage)
      .then((response) => {
        if (!active) return;
        setReports(response.data);
        setReportsMeta(response.meta);
      })
      .catch((error) => {
        if (active) setReportsError(error.message || 'Unable to load your reports.');
      })
      .finally(() => {
        if (active) setReportsLoading(false);
      });

    return () => {
      active = false;
    };
  }, [reportsPage, section, selectedReport]);

  useEffect(() => {
    if (section !== 'account') return undefined;
    let active = true;
    getAccount()
      .then((response) => {
        if (active) setAccount(response.data);
      })
      .catch((error) => {
        if (active) setAccountError(error.message || 'Unable to load your account.');
      })
      .finally(() => {
        if (active) setAccountLoading(false);
      });

    return () => {
      active = false;
    };
  }, [section]);

  useEffect(() => {
    let active = true;
    let objectUrl = '';
    if (!selectedReport?.photo_url) return undefined;

    getEndUserReportPhoto(selectedReport.id)
      .then((blob) => {
        if (!active) return;
        objectUrl = URL.createObjectURL(blob);
        setReportPhoto({ id: selectedReport.id, url: objectUrl });
      })
      .catch((error) => {
        if (active) setReportPhoto({ id: selectedReport.id, error: error.message || 'Unable to load the report photo.' });
      });

    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [selectedReport]);

  function navigate(nextSection) {
    setNotice('');
    setSection(nextSection);
    setSelectedEquipment(null);
    setSelectedReport(null);
    setEquipmentError('');
    setReportsError('');
    setEquipmentPage(1);
    if (nextSection === 'equipment') setEquipmentLoading(true);
    if (nextSection === 'report') setPickerLoading(true);
    if (nextSection === 'reports') setReportsLoading(true);
    if (nextSection === 'account') setAccountLoading(true);
  }

  async function openEquipment(id) {
    setEquipmentError('');
    setEquipmentLoading(true);
    try {
      const response = await getEndUserEquipmentDetails(id);
      setSelectedEquipment(response.data);
    } catch (error) {
      setEquipmentError(error.message || 'Unable to load equipment details.');
    } finally {
      setEquipmentLoading(false);
    }
  }

  async function openReport(id) {
    setReportsError('');
    setReportLoading(true);
    try {
      const response = await getEndUserReport(id);
      setSelectedReport(response.data);
    } catch (error) {
      setReportsError(error.message || 'Unable to load report details.');
    } finally {
      setReportLoading(false);
    }
  }

  function startReport(asset = null) {
    setSelectedEquipment(null);
    setSelectedReport(null);
    setReportErrors({});
    setEquipmentError('');
    setPickerSearch('');
    setReportLocationId('');
    setReportAssetId(asset ? String(asset.id) : '');
    setPreferredLocationName(asset?.location || '');
    setPickerLoading(true);
    if (asset) setReportLocationId('');
    setSection('report');
    if (asset) {
      setPickerOptions((current) => current.some((item) => item.id === asset.id) ? current : [asset, ...current]);
    }
  }

  async function submitReport(event) {
    event.preventDefault();
    setReportErrors({});
    setEquipmentError('');
    const form = event.currentTarget;
    const photo = form.elements.photo.files[0];
    if (photo) {
      const isAllowedType = ['image/jpeg', 'image/png'].includes(photo.type);
      if (!isAllowedType || photo.size > 2 * 1024 * 1024) {
        setReportErrors({
          photo: [!isAllowedType ? 'Choose a JPG or PNG image.' : 'The image must be 2 MB or smaller.'],
        });
        return;
      }
    }

    setReportSubmitting(true);
    try {
      const response = await submitEndUserReport(new FormData(form));
      setNotice(`${response.message} Reference number: ${response.data.reference_no}`);
      setReportsPage(1);
      setReportsLoading(true);
      setSection('reports');
      setSelectedReport(null);
    } catch (error) {
      setEquipmentError(error.message || 'Unable to submit your report.');
      if (error instanceof ApiError) setReportErrors(error.errors);
    } finally {
      setReportSubmitting(false);
    }
  }

  async function saveAccount(event) {
    event.preventDefault();
    setAccountSaving(true);
    setAccountError('');
    setAccountNotice('');
    try {
      const response = await updateAccount({ name: new FormData(event.currentTarget).get('name') });
      setAccount(response.data);
      onAccountUpdate(response.data);
      setAccountNotice('Your personal information has been updated.');
    } catch (error) {
      setAccountError(error.message || 'Unable to update your account.');
    } finally {
      setAccountSaving(false);
    }
  }

  async function savePassword(event) {
    event.preventDefault();
    const formElement = event.currentTarget;
    setAccountSaving(true);
    setAccountError('');
    setAccountNotice('');
    try {
      const form = new FormData(formElement);
      await changePassword({
        current_password: form.get('current_password'),
        password: form.get('password'),
        password_confirmation: form.get('password_confirmation'),
      });
      formElement.reset();
      setAccountNotice('Your password has been changed.');
    } catch (error) {
      setAccountError(error.message || 'Unable to change your password.');
    } finally {
      setAccountSaving(false);
    }
  }

  const equipmentStatuses = dashboard?.equipment_by_status?.map((item) => item.status) || [];

  return (
    <main className="flex min-h-screen bg-slate-100 text-slate-900">
      <aside className="hidden w-64 shrink-0 flex-col bg-emerald-950 px-5 py-7 text-white md:flex">
        <a className="flex items-center gap-3 px-2" href="/end-user">
          <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10" aria-hidden="true">
            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24">
              <path d="M3 9.5 12 4l9 5.5v1.75H3V9.5Zm2 2.5v6m4-6v6m6-6v6m4-6v6M3 20h18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.6" />
            </svg>
          </span>
          <span>
            <span className="block text-xs font-semibold uppercase tracking-[0.16em] text-emerald-200">LGU Trinidad</span>
            <span className="mt-0.5 block text-sm font-semibold">Asset Tracking</span>
          </span>
        </a>
        <p className="mb-3 mt-12 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-300/70">Workspace</p>
        <nav aria-label="End User navigation" className="space-y-2">
          {navigation.map(([key, label, icon]) => (
            <button
              aria-current={section === key ? 'page' : undefined}
              className={`group flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-200 ${
                section === key ? 'bg-white text-emerald-950 shadow-sm' : 'text-emerald-100/80 hover:bg-white/10 hover:text-white'
              }`}
              key={key}
              onClick={() => navigate(key)}
              type="button"
            >
              <NavigationIcon className={`h-5 w-5 shrink-0 ${section === key ? 'text-emerald-800' : 'text-emerald-200 group-hover:text-white'}`} name={icon} />
              <span>{label}</span>
            </button>
          ))}
        </nav>
        <div className="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4">
          <p className="text-xs font-semibold text-emerald-200">End User access</p>
          <p className="mt-2 text-xs leading-5 text-emerald-50/65">Browse equipment and track the reports you submit.</p>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4 sm:px-8">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.13em] text-emerald-800">Property and Equipment Tracking</p>
            <p className="mt-1 text-sm text-slate-500">End User workspace</p>
          </div>
          <div className="flex items-center gap-3">
            <span aria-label="Notifications" className="flex h-9 w-9 items-center justify-center rounded-full text-slate-500" title="Notifications">
              <svg aria-hidden="true" className="h-5 w-5" fill="none" viewBox="0 0 24 24">
                <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.7" />
              </svg>
            </span>
            <div className="hidden text-right sm:block">
              <p className="text-sm font-semibold text-slate-800">{account?.name || user.name}</p>
              <p className="text-xs text-slate-500">End User</p>
            </div>
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-900">
              {(account?.name || user.name).slice(0, 1).toUpperCase()}
            </div>
            <button className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50" onClick={onLogout} type="button">
              Sign out
            </button>
          </div>
        </header>

        <nav aria-label="End User navigation" className="flex gap-2 overflow-x-auto border-b border-slate-200 bg-white px-5 py-3 md:hidden">
          {navigation.map(([key, label, icon]) => (
            <button
              aria-current={section === key ? 'page' : undefined}
              className={`flex shrink-0 items-center gap-2 rounded-lg px-3 py-2.5 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 ${
                section === key ? 'bg-emerald-800 text-white' : 'bg-slate-100 text-slate-700 hover:bg-emerald-50'
              }`}
              key={key}
              onClick={() => navigate(key)}
              type="button"
            >
              <NavigationIcon className="h-4 w-4 shrink-0" name={icon} />
              {label}
            </button>
          ))}
        </nav>

        <div className="mx-auto w-full max-w-6xl flex-1 px-5 py-8 sm:px-8 sm:py-10">
          {notice && <p className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{notice}</p>}

          {section === 'dashboard' && (
            <section>
              <div className="mb-7">
                <p className="text-xs font-bold uppercase tracking-[0.15em] text-emerald-800">End User dashboard</p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Good day, {account?.name || user.name}</h1>
                <p className="mt-2 text-sm text-slate-500">Browse equipment and keep track of the issues you have reported.</p>
              </div>
              {dashboardError && <p className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{dashboardError}</p>}
              {dashboardLoading ? (
                <p className="py-12 text-center text-sm text-slate-500" role="status">Loading your dashboard...</p>
              ) : (
                <>
                  <h2 className="mb-3 text-sm font-semibold text-slate-700">Equipment summary</h2>
                  {dashboard?.equipment_by_status?.length ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                      {dashboard.equipment_by_status.map((item) => (
                        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" key={item.status}>
                          <p className="text-sm text-slate-500">{formatStatus(item.status)}</p>
                          <p className="mt-2 text-3xl font-semibold text-slate-900">{item.count}</p>
                        </article>
                      ))}
                    </div>
                  ) : <EmptyState>No equipment is available.</EmptyState>}
                  <div className="mt-7 grid gap-6 lg:grid-cols-2">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                      <h2 className="text-lg font-semibold">My reports</h2>
                      {dashboard?.reports_by_status?.length ? (
                        <div className="mt-4 space-y-3">
                          {dashboard.reports_by_status.map((item) => (
                            <div className="flex items-center justify-between border-b border-slate-100 pb-3 last:border-0" key={item.status}>
                              <span className="text-sm text-slate-600">{formatStatus(item.status)}</span>
                              <span className="font-semibold text-slate-900">{item.count}</span>
                            </div>
                          ))}
                        </div>
                      ) : <p className="mt-4 text-sm text-slate-500">You have not submitted any reports.</p>}
                      <button className="mt-4 text-sm font-semibold text-emerald-800 hover:text-emerald-950" onClick={() => navigate('reports')} type="button">View my reports</button>
                    </section>
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                      <h2 className="text-lg font-semibold">Latest reports</h2>
                      {dashboard?.latest_reports?.length ? (
                        <div className="mt-4 space-y-3">
                          {dashboard.latest_reports.map((report) => (
                            <button className="flex w-full items-center justify-between gap-3 border-b border-slate-100 pb-3 text-left last:border-0" key={report.id} onClick={() => { setSection('reports'); openReport(report.id); }} type="button">
                              <span className="min-w-0">
                                <span className="block truncate text-sm font-semibold text-slate-800">{report.reference_no}</span>
                                <span className="mt-1 block truncate text-xs text-slate-500">{report.asset?.asset_name || 'Equipment'}</span>
                              </span>
                              <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyle(report.status)}`}>{formatStatus(report.status)}</span>
                            </button>
                          ))}
                        </div>
                      ) : <p className="mt-4 text-sm text-slate-500">Your submitted reports will appear here.</p>}
                    </section>
                  </div>
                </>
              )}
            </section>
          )}

          {section === 'equipment' && (
            <section>
              <div className="mb-6">
                <p className="text-xs font-bold uppercase tracking-[0.15em] text-emerald-800">Equipment</p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight">Equipment List</h1>
                <p className="mt-2 text-sm text-slate-500">Browse and search for property and equipment records</p>
              </div>
              {selectedEquipment ? (
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                  <button className="text-sm font-semibold text-emerald-800 hover:text-emerald-950" onClick={() => setSelectedEquipment(null)} type="button">← Back to equipment</button>
                  <div className="mt-5 flex flex-wrap items-start justify-between gap-4">
                    <div>
                      <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{selectedEquipment.asset_tag}</p>
                      <h2 className="mt-1 text-2xl font-semibold">{selectedEquipment.asset_name}</h2>
                    </div>
                    <span className={`rounded-full px-3 py-1.5 text-xs font-semibold ${statusStyle(selectedEquipment.status)}`}>{formatStatus(selectedEquipment.status)}</span>
                  </div>
                  <dl className="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                    {[
                      ['Category', selectedEquipment.category],
                      ['Location', selectedEquipment.location],
                      ['Room / Area', selectedEquipment.room_area],
                      ['Condition', selectedEquipment.condition],
                      ['Brand', selectedEquipment.brand],
                      ['Model', selectedEquipment.model],
                      ['Serial Number', selectedEquipment.serial_number],
                      ['Description', selectedEquipment.description],
                    ].map(([label, value]) => (
                      <div key={label}>
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
                        <dd className="mt-1 text-sm text-slate-800">{value || '—'}</dd>
                      </div>
                    ))}
                  </dl>
                  <button className="mt-6 rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900" onClick={() => startReport(selectedEquipment)} type="button">Report a problem</button>
                </section>
              ) : (
                <>
                  <form
                    className="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_180px_180px_auto]"
                    onSubmit={(event) => {
                      event.preventDefault();
                      setEquipmentPage(1);
                      setEquipmentLoading(true);
                      setEquipmentFilters({ ...equipmentFilters, q: equipmentSearchDraft });
                    }}
                  >
                    <input aria-label="Search equipment" className={inputClass.replace('mt-1.5 ', '')} onChange={(event) => setEquipmentSearchDraft(event.target.value)} placeholder="Search by name, property number, or category..." value={equipmentSearchDraft} />
                    <select aria-label="Filter by category" className={inputClass.replace('mt-1.5 ', '')} onChange={(event) => { setEquipmentLoading(true); setEquipmentPage(1); setEquipmentFilters({ ...equipmentFilters, category_id: event.target.value }); }} value={equipmentFilters.category_id}>
                      <option value="">All Categories</option>
                      {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                    </select>
                    <select aria-label="Filter by status" className={inputClass.replace('mt-1.5 ', '')} onChange={(event) => { setEquipmentLoading(true); setEquipmentPage(1); setEquipmentFilters({ ...equipmentFilters, status: event.target.value }); }} value={equipmentFilters.status}>
                      <option value="">All Status</option>
                      {equipmentStatuses.map((status) => <option key={status} value={status}>{formatStatus(status)}</option>)}
                    </select>
                    <button className="rounded-lg bg-emerald-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900" type="submit">Search</button>
                  </form>
                  {equipmentError && <p className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{equipmentError}</p>}
                  {equipmentLoading ? (
                    <p className="py-12 text-center text-sm text-slate-500" role="status">Loading equipment...</p>
                  ) : equipment.length === 0 ? <EmptyState>No equipment matches your search.</EmptyState> : (
                    <>
                      <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <table className="w-full min-w-[760px] divide-y divide-slate-200 text-left text-sm">
                          <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>{['Property No.', 'Equipment Name', 'Category', 'Location', 'Status', 'Actions'].map((column) => <th className="px-4 py-3 font-semibold" key={column}>{column}</th>)}</tr>
                          </thead>
                          <tbody className="divide-y divide-slate-100">
                            {equipment.map((item) => (
                              <tr key={item.id}>
                                <td className="whitespace-nowrap px-4 py-3 font-semibold text-slate-800">{item.asset_tag}</td>
                                <td className="px-4 py-3">{item.asset_name}</td>
                                <td className="px-4 py-3">{item.category}</td>
                                <td className="px-4 py-3">{item.location}</td>
                                <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyle(item.status)}`}>{formatStatus(item.status)}</span></td>
                                <td className="px-4 py-3"><button className="font-semibold text-emerald-800 hover:text-emerald-950" onClick={() => openEquipment(item.id)} type="button">View</button></td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                      <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                        <span>Page {equipmentMeta.current_page} of {equipmentMeta.last_page} · {equipmentMeta.total} total records</span>
                        <div className="flex gap-2">
                          <button className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 disabled:opacity-40" disabled={equipmentPage <= 1} onClick={() => { setEquipmentLoading(true); setEquipmentPage(equipmentPage - 1); }} type="button">Previous</button>
                          <button className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 disabled:opacity-40" disabled={equipmentPage >= equipmentMeta.last_page} onClick={() => { setEquipmentLoading(true); setEquipmentPage(equipmentPage + 1); }} type="button">Next</button>
                        </div>
                      </div>
                    </>
                  )}
                </>
              )}
            </section>
          )}

          {section === 'report' && (
            <section className="max-w-3xl">
              <div className="mb-6">
                <p className="text-xs font-bold uppercase tracking-[0.15em] text-emerald-800">Equipment support</p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight">Report Damaged Equipment</h1>
                <p className="mt-2 text-sm text-slate-500">Tell the Property Custodian what needs attention.</p>
              </div>
              {equipmentError && <p className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{equipmentError}</p>}
              <form className="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" onSubmit={submitReport}>
                <label className="block text-sm font-semibold text-slate-700">
                  Select Equipment
                  <input className={inputClass} onChange={(event) => { setPickerLoading(true); setReportAssetId(''); setReportLocationId(''); setPreferredLocationName(''); setPickerSearch(event.target.value); }} placeholder="Search equipment by name or property number" value={pickerSearch} />
                  <select className={inputClass} onChange={(event) => { setReportAssetId(event.target.value); const asset = pickerOptions.find((item) => String(item.id) === event.target.value); const location = locations.find((item) => item.name === asset?.location); setPreferredLocationName(asset?.location || ''); setReportLocationId(location ? String(location.id) : ''); }} value={reportAssetId} name="asset_id" required>
                    <option value="">Select equipment</option>
                    {pickerOptions.map((item) => <option key={item.id} value={item.id}>{item.asset_tag} — {item.asset_name}</option>)}
                  </select>
                  {pickerLoading && <span className="mt-1 block text-xs font-normal text-slate-500">Searching equipment...</span>}
                  {reportErrors.asset_id?.[0] && <span className="mt-1 block text-xs font-normal text-rose-700">{reportErrors.asset_id[0]}</span>}
                </label>
                <label className="block text-sm font-semibold text-slate-700">
                  Select Location
                  <select className={inputClass} name="location_id" required value={reportLocationId} onChange={(event) => setReportLocationId(event.target.value)}>
                    <option value="">Select location</option>
                    {locations.map((location) => <option key={location.id} value={location.id}>{location.name}</option>)}
                  </select>
                  {reportErrors.location_id?.[0] && <span className="mt-1 block text-xs font-normal text-rose-700">{reportErrors.location_id[0]}</span>}
                </label>
                <label className="block text-sm font-semibold text-slate-700">
                  Enter Description
                  <textarea className={inputClass} maxLength={5000} name="description" placeholder="Describe what is damaged or not working" required rows={5} />
                  {reportErrors.description?.[0] && <span className="mt-1 block text-xs font-normal text-rose-700">{reportErrors.description[0]}</span>}
                </label>
                <label className="block text-sm font-semibold text-slate-700">
                  Upload Photo <span className="font-normal text-slate-500">(optional, JPG or PNG, up to 2 MB)</span>
                  <input accept=".jpg,.jpeg,.png,image/jpeg,image/png" className={inputClass} name="photo" type="file" />
                  {reportErrors.photo?.[0] && <span className="mt-1 block text-xs font-normal text-rose-700">{reportErrors.photo[0]}</span>}
                </label>
                <div className="flex justify-end">
                  <button className="rounded-xl bg-emerald-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-50" disabled={reportSubmitting} type="submit">
                    {reportSubmitting ? 'Submitting...' : 'Submit Report'}
                  </button>
                </div>
              </form>
            </section>
          )}

          {section === 'reports' && (
            <section>
              <div className="mb-6">
                <p className="text-xs font-bold uppercase tracking-[0.15em] text-emerald-800">Report tracking</p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight">My Reports</h1>
                <p className="mt-2 text-sm text-slate-500">Track the status of reports you have submitted.</p>
              </div>
              {reportsError && <p className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{reportsError}</p>}
              {selectedReport ? (
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                  <button className="text-sm font-semibold text-emerald-800 hover:text-emerald-950" onClick={() => setSelectedReport(null)} type="button">← Back to my reports</button>
                  {reportLoading ? <p className="py-12 text-center text-sm text-slate-500" role="status">Loading report...</p> : (
                    <>
                      <div className="mt-5 flex flex-wrap items-start justify-between gap-3">
                        <div>
                          <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{selectedReport.reference_no}</p>
                          <h2 className="mt-1 text-2xl font-semibold">{selectedReport.asset?.asset_name}</h2>
                        </div>
                        <span className={`rounded-full px-3 py-1.5 text-xs font-semibold ${statusStyle(selectedReport.status)}`}>{formatStatus(selectedReport.status)}</span>
                      </div>
                      <dl className="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                        <div><dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Equipment</dt><dd className="mt-1 text-sm">{selectedReport.asset?.asset_tag} — {selectedReport.asset?.asset_name}</dd></div>
                        <div><dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Location</dt><dd className="mt-1 text-sm">{selectedReport.location}</dd></div>
                        <div><dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Date submitted</dt><dd className="mt-1 text-sm">{new Date(selectedReport.created_at).toLocaleString()}</dd></div>
                        <div><dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt><dd className="mt-1 text-sm">{formatStatus(selectedReport.status)}</dd></div>
                        <div className="sm:col-span-2"><dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt><dd className="mt-1 whitespace-pre-wrap text-sm leading-6">{selectedReport.description}</dd></div>
                      </dl>
                      {selectedReport.photo_url && (
                        <div className="mt-5">
                          <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Photo</h3>
                          {reportPhoto?.id === selectedReport.id && reportPhoto.error && <p className="mt-2 text-sm text-rose-700" role="alert">{reportPhoto.error}</p>}
                          {reportPhoto?.id === selectedReport.id && reportPhoto.url && <img alt={`Photo for ${selectedReport.reference_no}`} className="mt-2 max-h-96 rounded-xl border border-slate-200 object-contain" src={reportPhoto.url} />}
                        </div>
                      )}
                      <p className="mt-5 border-t border-slate-100 pt-4 text-sm text-slate-500">Status history will appear here when updates are available.</p>
                    </>
                  )}
                </section>
              ) : reportsLoading ? (
                <p className="py-12 text-center text-sm text-slate-500" role="status">Loading your reports...</p>
              ) : reports.length === 0 ? <EmptyState>You have not submitted any reports.</EmptyState> : (
                <>
                  <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="w-full min-w-[680px] divide-y divide-slate-200 text-left text-sm">
                      <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr>{['Reference No.', 'Equipment', 'Date Submitted', 'Status'].map((column) => <th className="px-4 py-3 font-semibold" key={column}>{column}</th>)}</tr></thead>
                      <tbody className="divide-y divide-slate-100">
                        {reports.map((report) => (
                          <tr className="cursor-pointer hover:bg-slate-50" key={report.id} onClick={() => openReport(report.id)}>
                            <td className="whitespace-nowrap px-4 py-3 font-semibold text-emerald-800">{report.reference_no}</td>
                            <td className="px-4 py-3">{report.asset?.asset_name}</td>
                            <td className="whitespace-nowrap px-4 py-3">{new Date(report.created_at).toLocaleDateString()}</td>
                            <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyle(report.status)}`}>{formatStatus(report.status)}</span></td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                  <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                    <span>Page {reportsMeta.current_page} of {reportsMeta.last_page} · {reportsMeta.total} total reports</span>
                    <div className="flex gap-2">
                      <button className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold disabled:opacity-40" disabled={reportsPage <= 1} onClick={() => { setReportsLoading(true); setReportsPage(reportsPage - 1); }} type="button">Previous</button>
                      <button className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold disabled:opacity-40" disabled={reportsPage >= reportsMeta.last_page} onClick={() => { setReportsLoading(true); setReportsPage(reportsPage + 1); }} type="button">Next</button>
                    </div>
                  </div>
                </>
              )}
            </section>
          )}

          {section === 'account' && (
            <section className="max-w-3xl">
              <div className="mb-6">
                <p className="text-xs font-bold uppercase tracking-[0.15em] text-emerald-800">Profile settings</p>
                <h1 className="mt-2 text-3xl font-semibold tracking-tight">My Account</h1>
                <p className="mt-2 text-sm text-slate-500">Update your personal information and password.</p>
              </div>
              {accountError && <p className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{accountError}</p>}
              {accountNotice && <p className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{accountNotice}</p>}
              {accountLoading ? <p className="py-12 text-center text-sm text-slate-500" role="status">Loading account...</p> : (
                <div className="space-y-6">
                  <form className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" onSubmit={saveAccount}>
                    <h2 className="text-lg font-semibold">Personal information</h2>
                    <label className="block text-sm font-semibold text-slate-700">Full name<input className={inputClass} defaultValue={account?.name} name="name" required /></label>
                    <div className="grid gap-4 sm:grid-cols-2">
                      <div><p className="text-sm font-semibold text-slate-700">Username</p><p className="mt-2 text-sm text-slate-500">@{account?.username}</p></div>
                      <div><p className="text-sm font-semibold text-slate-700">Role</p><p className="mt-2 text-sm text-slate-500">End User</p></div>
                    </div>
                    <div className="flex justify-end"><button className="rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-50" disabled={accountSaving} type="submit">{accountSaving ? 'Saving...' : 'Save changes'}</button></div>
                  </form>
                  <form className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" onSubmit={savePassword}>
                    <h2 className="text-lg font-semibold">Change password</h2>
                    <label className="block text-sm font-semibold text-slate-700">Current password<input autoComplete="current-password" className={inputClass} name="current_password" required type="password" /></label>
                    <label className="block text-sm font-semibold text-slate-700">New password<input autoComplete="new-password" className={inputClass} minLength={8} name="password" required type="password" /></label>
                    <label className="block text-sm font-semibold text-slate-700">Confirm new password<input autoComplete="new-password" className={inputClass} minLength={8} name="password_confirmation" required type="password" /></label>
                    <div className="flex justify-end"><button className="rounded-xl border border-emerald-800 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-50 disabled:opacity-50" disabled={accountSaving} type="submit">{accountSaving ? 'Saving...' : 'Change password'}</button></div>
                  </form>
                </div>
              )}
            </section>
          )}

          <footer className="mt-10 border-t border-slate-200 py-5 text-xs text-slate-400">
            Property and Equipment Tracking System · Municipality of Trinidad
          </footer>
        </div>
      </div>
    </main>
  );
}

export default EndUserDashboard;
