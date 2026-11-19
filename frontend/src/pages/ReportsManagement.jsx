import { useEffect, useState } from 'react';
import { getAssetSummaryReport } from '../api';
import DamageReportsPanel from './DamageReportsPanel';

const fieldClass = 'mt-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10';

function localDateValue(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function displayDate(value) {
  if (!value) return '';
  const [year, month, day] = value.split('-').map(Number);
  return new Date(year, month - 1, day).toLocaleDateString(undefined, {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
}

function percentage(value, total) {
  return total ? Math.round((value / total) * 100) : 0;
}

function csvCell(value) {
  return `"${String(value ?? '').replaceAll('"', '""')}"`;
}

function ReportsManagement() {
  const [defaultDates] = useState(() => {
    const now = new Date();
    return {
      today: localDateValue(now),
      monthStart: localDateValue(new Date(now.getFullYear(), now.getMonth(), 1)),
    };
  });
  const [dateFrom, setDateFrom] = useState(defaultDates.monthStart);
  const [dateTo, setDateTo] = useState(defaultDates.today);
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [exportOpen, setExportOpen] = useState(false);

  async function generateReport(from = dateFrom, to = dateTo) {
    try {
      const response = await getAssetSummaryReport({ date_from: from, date_to: to });
      setReport(response.data);
    } catch (requestError) {
      setReport(null);
      setError(requestError.message || 'Unable to generate the asset summary report.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let active = true;
    getAssetSummaryReport({
      date_from: defaultDates.monthStart,
      date_to: defaultDates.today,
    })
      .then((response) => {
        if (active) setReport(response.data);
      })
      .catch((requestError) => {
        if (active) setError(requestError.message || 'Unable to generate the asset summary report.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [defaultDates.monthStart, defaultDates.today]);

  function submitFilters(event) {
    event.preventDefault();
    setLoading(true);
    setError('');
    generateReport();
  }

  function exportCsv() {
    if (!report) return;
    const rows = [
      ['Asset Summary Report'],
      ['Date Range', displayDate(report.date_from), displayDate(report.date_to)],
      [],
      ['Summary', 'Count'],
      ['Total Assets', report.summary.total_assets],
      ['In Use', report.summary.in_use],
      ['Under Maintenance', report.summary.under_maintenance],
      ['Out of Service', report.summary.out_of_service],
      ['Available (not included in status buckets)', report.summary.available],
      [],
      ['Assets By Category'],
      ['Category', 'Total', 'In Use', 'Under Maintenance', 'Out of Service'],
      ...report.categories.map((row) => [
        row.category,
        row.total,
        row.in_use,
        row.under_maintenance,
        row.out_of_service,
      ]),
      [
        'Total',
        report.summary.total_assets,
        report.summary.in_use,
        report.summary.under_maintenance,
        report.summary.out_of_service,
      ],
    ];
    const csv = `\uFEFF${rows.map((row) => row.map(csvCell).join(',')).join('\r\n')}`;
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `asset-summary-${report.date_from}-to-${report.date_to}.csv`;
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    setExportOpen(false);
  }

  function printReport() {
    setExportOpen(false);
    window.print();
  }

  return (
    <section className="reports-page">
      <div className="mb-6 flex flex-wrap items-start justify-between gap-4 print:hidden">
        <div>
          <h1 className="text-3xl font-semibold tracking-tight text-slate-900">Reports</h1>
          <p className="mt-1 text-sm text-emerald-800">Dashboard / Reports</p>
        </div>
        <div className="text-right">
          <p className="text-sm font-semibold text-slate-800">Property Custodian</p>
          <p className="text-xs text-slate-500">Administrator report access</p>
        </div>
      </div>

      <DamageReportsPanel />

      <form className="mb-5 flex flex-wrap items-end gap-x-5 gap-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:hidden" onSubmit={submitFilters}>
        <label className="block min-w-52 text-sm font-semibold text-slate-700">
          Report Type
          <select className={`${fieldClass} w-full`} disabled>
            <option>Asset Summary Report</option>
          </select>
        </label>
        <label className="block text-sm font-semibold text-slate-700">
          Date From
          <input className={`${fieldClass} block`} max={dateTo || undefined} onChange={(event) => setDateFrom(event.target.value)} required type="date" value={dateFrom} />
        </label>
        <label className="block text-sm font-semibold text-slate-700">
          Date To
          <input className={`${fieldClass} block`} min={dateFrom || undefined} onChange={(event) => setDateTo(event.target.value)} required type="date" value={dateTo} />
        </label>
        <button className="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-50" disabled={loading} type="submit">
          {loading ? 'Generating...' : 'Generate Report'}
        </button>
        <div className="relative ml-auto">
          <button
            aria-expanded={exportOpen}
            className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 disabled:opacity-50"
            disabled={!report || loading}
            onClick={() => setExportOpen((open) => !open)}
            type="button"
          >
            Export <span aria-hidden="true" className="ml-1">⌄</span>
          </button>
          {exportOpen && (
            <div className="absolute right-0 z-10 mt-2 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
              <button className="block w-full px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50" onClick={exportCsv} type="button">Download CSV</button>
              <button className="block w-full px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50" onClick={printReport} type="button">Print / Save as PDF</button>
            </div>
          )}
        </div>
      </form>

      {error && <p className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 print:hidden" role="alert">{error}</p>}

      {loading && !report ? (
        <p className="py-12 text-center text-sm text-slate-500" role="status">Generating asset summary...</p>
      ) : report ? (
        <article className="rounded-2xl border border-slate-300 bg-white p-5 shadow-sm sm:p-7 print:rounded-none print:border-0 print:p-0 print:shadow-none">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h2 className="text-2xl font-bold tracking-tight text-slate-900">Asset Summary Report</h2>
              <p className="mt-2 text-sm text-slate-600">
                Date Range: {displayDate(report.date_from)} – {displayDate(report.date_to)}
              </p>
            </div>
            {loading && <span className="text-sm text-slate-500 print:hidden" role="status">Refreshing report...</span>}
          </div>

          <div className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {[
              { label: 'Total Assets', value: report.summary.total_assets, share: null },
              { label: 'In Use', value: report.summary.in_use, share: percentage(report.summary.in_use, report.summary.total_assets) },
              { label: 'Under Maintenance', value: report.summary.under_maintenance, share: percentage(report.summary.under_maintenance, report.summary.total_assets) },
              { label: 'Out of Service', value: report.summary.out_of_service, share: percentage(report.summary.out_of_service, report.summary.total_assets) },
            ].map((item) => (
              <div className="border border-slate-400 px-4 py-4 text-center" key={item.label}>
                <p className="text-sm font-semibold text-slate-700">{item.label}</p>
                <p className="mt-2 text-3xl font-bold text-slate-900">
                  {item.value}
                  {item.share !== null && <span className="ml-2 text-sm font-normal text-slate-700">({item.share}%)</span>}
                </p>
              </div>
            ))}
          </div>

          <p className="mt-3 text-xs text-slate-500">
            Available assets: {report.summary.available}. Included in Total Assets, but not in the three status buckets above.
          </p>

          <section className="mt-6">
            <h3 className="mb-3 text-base font-bold text-slate-900">Assets By Category</h3>
            {report.categories.length ? (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[560px] border-collapse text-left text-sm">
                  <thead>
                    <tr className="border-y border-slate-400 bg-slate-50">
                      <th className="px-3 py-2 font-semibold">Category</th>
                      <th className="px-3 py-2 text-right font-semibold">Total</th>
                      <th className="px-3 py-2 text-right font-semibold">In Use</th>
                      <th className="px-3 py-2 text-right font-semibold">Under Maintenance</th>
                      <th className="px-3 py-2 text-right font-semibold">Out of Service</th>
                    </tr>
                  </thead>
                  <tbody>
                    {report.categories.map((row) => (
                      <tr className="border-b border-slate-300" key={row.category}>
                        <td className="px-3 py-2.5">{row.category}</td>
                        <td className="px-3 py-2.5 text-right">{row.total}</td>
                        <td className="px-3 py-2.5 text-right">{row.in_use}</td>
                        <td className="px-3 py-2.5 text-right">{row.under_maintenance}</td>
                        <td className="px-3 py-2.5 text-right">{row.out_of_service}</td>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot>
                    <tr className="border-b border-slate-400 font-bold">
                      <td className="px-3 py-2.5">Total</td>
                      <td className="px-3 py-2.5 text-right">{report.summary.total_assets}</td>
                      <td className="px-3 py-2.5 text-right">{report.summary.in_use}</td>
                      <td className="px-3 py-2.5 text-right">{report.summary.under_maintenance}</td>
                      <td className="px-3 py-2.5 text-right">{report.summary.out_of_service}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            ) : (
              <p className="rounded-xl border border-dashed border-slate-300 px-5 py-9 text-center text-sm text-slate-500">
                No assets were acquired during this date range.
              </p>
            )}
          </section>
        </article>
      ) : !error ? (
        <p className="py-12 text-center text-sm text-slate-500">Select a date range and generate the report.</p>
      ) : null}
    </section>
  );
}

export default ReportsManagement;
